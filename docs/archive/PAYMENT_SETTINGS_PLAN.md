# Payment Settings + Mayar Auto-Confirm — Implementation Plan

> Dua fitur pada branch `feat/orders-redesign`:
> **(A) Create→pay flow** — setelah submit, pesanan langsung `pending` dan drawer pembayaran terbuka otomatis.
> **(B) Pengaturan Pembayaran** — bank details + **QRIS statis** (gratis, selalu diutamakan) dan **Mayar.id gateway** sebagai fallback: kunci API per-tenant, link QRIS dinamis per pesanan, dan webhook yang otomatis menandai pesanan lunas.
>
> Melengkapi `docs/ORDERS_REDESIGN.md` (FE-only). Bagian B menambah backend baru: service HTTP pertama, endpoint webhook pertama, dan 1 migration.

---

## 0. Context & Decisions

- **Part A** (create→pay): order tetap `pending`, drawer pembayaran langsung terbuka setelah submit. ✅ approved.
- **Part B** (payment settings): QRIS statis (gambar) + bank account details — gratis, selalu jadi fallback utama.
- **Kedalaman Mayar**: **Full auto-confirm** — kunci API Mayar + link QRIS dinamis per pesanan + webhook yang otomatis menandai lunas. ✅ dipilih user.
  - Mayar hanya aktif ketika **tidak ada QRIS statis**. Hierarki: QRIS statis → Mayar link dinamis → pesan "belum dikonfigurasi".
- **Tanpa migration** untuk payment settings biasa (QRIS/bank) — pakai `TenantSettings` key/value yang sudah ada.
- **Satu migration** untuk Mayar: kolom link per-order di `orders` + kolom idempotensi di `payments`.

### "Firsts" di codebase ini

1. **Integrasi HTTP outbound pertama** — tidak ada `Http::`/`GuzzleHttp`/`->withHeaders` di codebase (Google pakai SDK). Mayar pakai Laravel `Http` facade.
2. **Endpoint webhook pertama** — hanya ada route service-to-service `/ops/backup` (`throttle:service-webhook`). Mayar webhook jadi webhook receiver pertama.

### Fakta API Mayar (verified via `mayar docs` + docs.mayar.id, 2026-08-12)

- Base URL prod: `https://api.mayar.id/hl/v2/...`. Sandbox: `https://api.mayar.io/hl/v2/...`.
- Auth: `Authorization: Bearer {API_KEY}` (per-merchant key dari dashboard Mayar).
- **Dynamic QRIS (primary, per-order):** `POST /hl/v2/qr-codes/create` body `{amount}` → `{statusCode, messages, data:{url, amount}}`. `data.url` = PNG QR langsung. No id returned.
- **Static QRIS:** `GET /hl/v2/qr-codes/static` → `{data:{url}}`. Merchant permanent QR. Bisa ganti fitur "upload QRIS sendiri".
- **Payment-link (tidak dipakai):** `POST /hl/v2/products/payment-link/create` body `{name,description,amount,redirectUrl,notes,expiredAt,...}` → `{data:{id, link}}`. Link = hosted page, no direct QR. Displaced by dynamic QRIS.
- **Webhook:** dashboard-config only (Integration → Webhook → URL). **No registration API.** Method POST, `application/json`.
- **Webhook payload:** `{event, data:{id, status, createdAt, updatedAt, merchantId, merchantEmail, merchantName, customerName, customerEmail, customerMobile, amount, productId, productName, productType, ...}}`.
- **Webhook events:** `payment.received` (lunas), `payment.reminder` (29 menit unpaid).
- **NO signature. NO auth header. NO verification field.** Plain POST JSON. (membunuh opsi "secret per-tenant" — Mayar kirim tidak ada yang bisa diverifikasi.)
- Webhook match: `data.amount` + tenant heuristic (karena dynamic QRIS tidak kembalikan id).

### Fakta codebase yang menentukan desain

| Fakta | Implikasi |
|---|---|
| `PaymentMethod::Qris` enum sudah ada (`app/Enums/PaymentMethod.php:9`) | Mayar cuma *delivery mechanism* untuk `qris` — tanpa ubah enum |
| `TenantSettings::setGoogleConnection()`/`googleRefreshToken()` (135–161) pakai `Crypt::encryptString()`/`decryptString()` | API key Mayar disimpan identik — tanpa migration khusus kunci |
| `BelongsToTenant` add `TenantScope` global scope | Webhook tanpa auth harus `Order::withoutGlobalScope(TenantScope::class)->where('mayar_link_id', ...)` lalu set konteks tenant `app()->instance('currentTenantId', $id)` |
| `PaymentService::addPayment(Order, User, array)` butuh `User` | Webhook tidak punya user → refactor jadi `?User`, set `tenant_id` eksplisit |
| `payments` tabel tak punya kolom external reference | Butuh kolom untuk idempotensi webhook (replay-safe) |
| `orders` tabel tak punya kolom JSON/meta | Butuh kolom untuk cache link Mayar per-order |
| `app/Support/PpnSettings.php` | Template untuk facade static baru `PaymentSettings.php` |
| `TenantSettingsController::uploadLogo()` | Template untuk upload gambar QRIS |
| Middleware: `auth:sanctum`, `auth.host`, `tenant`, `tenant.active`, `throttle:api`. Owner-only pakai `role:owner`. Service webhook pakai `throttle:service-webhook` | Webhook route di luar semua middleware auth/tenant (sejajar `/ops/backup`) |

---

## Part A — Create → Pay flow

**File:** `resources/js/pages/Orders.tsx` (3 edits)

1. **`submitAndSend()` (~lines 401–432)**
   - Setelah `api.create(...)`, fetch order segar: `const fresh = await api.get<Order>('orders', id);`
   - Ganti `setDrawerOpen(false)` → `openPayment(fresh);`
   - Pertahankan `load();` untuk refresh list.
   - Toast text → `'Pesanan dibuat'`.
   - Order tetap `pending` (tanpa mutasi status).

2. **Label tombol (~line 1014)**
   - `Kirim Pesanan` → **`Buat Pesanan`** (usulan; alternatif `Proses` — konfirmasi user).

3. **`save()`** — tidak diubah. Draft tetap menutup drawer.

**Verify:** `npm run build`.

---

## Part B — Payment Settings + Mayar Integration

### B1. Migration

**File baru:** `database/migrations/2026_08_12_000001_add_mayar_payment_columns.php`

- `orders`:
  - `mayar_link_id` — string, nullable, **indexed** (key lookup webhook).
  - `mayar_payment_url` — string, nullable.
  - `mayar_qr_url` — string, nullable.
  - `mayar_amount` — decimal(15,2), nullable (deteksi link stale saat amount berubah).
- `payments`:
  - `external_reference` — string, nullable, indexed (id transaksi Mayar untuk **idempotent** replay webhook).
- `down()` reverse keduanya.

### B2. `app/Support/TenantSettings.php` (extend)

Tambah payment key/value + enkripsi Mayar (mirror pattern Google 135–161):

- **Bank details** (plain string): `bankName()`, `bankAccountName()`, `bankAccountNumber()`.
- **QRIS statis** (path file, seperti logo): `qrisImagePath()`, `setQrisImage(string $path)`, `clearQrisImage()`.
- **Mayar API key** (encrypted): `mayarApiKey()` → `Crypt::decryptString(...) | null`; `setMayarApiKey(string $key)` → encrypt; `clearMayarApiKey()`; `mayarEnabled()` → key ada **dan** tidak ada QRIS statis.
- Aggregator: `paymentToArray()`, `updatePayment(array $data)`.
- Seed default di `seedDefaults()` (tambah `payment.*` = null).

Keys: `payment.bank_name`, `payment.bank_account_name`, `payment.bank_account_number`, `payment.qris_image`, `payment.mayar_api_key`.

### B3. `app/Support/PaymentSettings.php` (baru)

Facade static, **template persis = `app/Support/PpnSettings.php`**:

- `bank(): array` → `{ bank_name, bank_account_name, bank_account_number }`.
- `qrisImageUrl(): ?string` → URL absolut ke gambar QRIS (atau null).
- `mayarEnabled(): bool`.
- `toArray(): array`.
- `update(array $data): void`.
- Semua method delegasi ke `TenantSettings::tryFor()`, dengan fallback aman (match pattern `PpnSettings`).

### B4. `app/Services/MayarService.php` (baru)

Integrasi HTTP pertama di codebase — pakai Laravel `Http` facade.

- `createPaymentLink(Order $order): array`
  - Resolve tenant via `TenantSettings::for($order->tenant_id)`.
  - Jika `mayarEnabled()` false → throw `RuntimeException`.
  - **Reuse cache:** jika `$order->mayar_link_id` && `$order->mayar_amount == $order->remaining_amount` → return cached `{ link_id, payment_url, qr_url }`.
  - Else: `Http::withToken($apiKey)->post('https://api.mayar.id/hl/v1/data', [ 'name' => $order->invoice_no, 'amount' => $order->remaining_amount, ... ])`.
  - Sukses: persist `mayar_link_id`/`mayar_payment_url`/`mayar_qr_url`/`mayar_amount` di order; return payload link.
  - Gagal: log + throw dengan pesan error Mayar.
- `registerWebhook(): void`
  - `Http::withToken($apiKey)->post('https://api.mayar.id/hl/v2/webhooks/update', [ 'urlHook' => rtrim(config('app.url'), '/').'/api/webhooks/mayar' ])`.
  - Best-effort (dipanggil saat owner simpan kunci); log jika gagal, jangan block save.
- `verifySignature(Request $request): bool` — validasi vs `MAYAR_WEBHOOK_SECRET`. Header/HMAC eksak dikonfirmasi di live docs saat implementasi; `link_id` payload jadi validasi *tambahan*, bukan satu-satunya.
- `parseWebhook(Request): array` → `{ link_id, transaction_id, amount, status }`.
- *(Nama field request/response Mayar V1 — `data.link`, `data.qr_link`, `data.id` — dikonfirmasi vs sandbox saat implementasi karena docs 404. Kode defensif: handle camelCase dan snake_case.)*

### B5. `app/Http/Controllers/Api/MayarController.php` (baru)

- `link(Request $request, Order $order): JsonResponse`
  - `POST`, **authenticated** + `authorize('view', $order)`.
  - 403 jika Mayar tidak dikonfigurasi untuk tenant.
  - Return cached atau freshly-created Mayar link `{ link_id, payment_url, qr_url }`.
- `webhook(Request $request): JsonResponse`
  - `POST`, **unauthenticated**, `throttle:service-webhook`.
  - Verify signature → parse transaction id + link id.
  - `Order::withoutGlobalScope(TenantScope::class)->where('mayar_link_id', $linkId)->first()`.
  - Tidak ketemu → return `200 { ok: true }` (idempotent no-op; jangan bocor 404).
  - Set konteks tenant: `app()->instance('currentTenantId', $order->tenant_id)`.
  - Idempotency: `Payment::withoutGlobalScope(TenantScope::class)->where('external_reference', $txId)->exists()` → jika ada, return `200`.
  - Record payment: `PaymentService::addPayment($order, null, [ 'amount' => $amount, 'metode' => 'qris', 'external_reference' => $txId ])`.
  - Return `200 { ok: true }`.

### B6. `app/Services/PaymentService.php` (refactor)

- `addPayment(Order $order, ?User $user, array $data): Payment`
  - Signature: `?User` (sebelumnya `User`). `activity()->causedBy($user)` terima null.
  - `Payment::create([...])` set `'tenant_id' => $order->tenant_id` eksplisit (berfungsi di webhook tanpa auth).
  - Terima `external_reference` opsional di `$data`.
- Backward-compatible: `PaymentController::store` masih kirim user terautentikasi.

### B7. `app/Http/Controllers/Api/SettingsController.php` (extend)

- `payment(): JsonResponse` — **semua user** (staff ambil pembayaran). Return `PaymentSettings::toArray()` + `qris_image_url`.
- `updatePayment(Request): JsonResponse` — **owner-only** (`if (! $user->isOwner()) return 403`). Validasi field bank; panggil `PaymentSettings::update()`. Jika `mayar_api_key` berubah → re-register webhook.
- `uploadQris(Request): JsonResponse` — **owner-only**. Mirror `TenantSettingsController::uploadLogo()` (validasi file `qris`, mime allow-list png/jpg/webp, store di tenant-scoped path, persist path).
- `deleteQris(Request): JsonResponse` — **owner-only**. Hapus file + clear setting.
- `testMayar(): JsonResponse` — **owner-only**. Health check MayarService (mis. GET account balance) → `{ connected: bool, message }`.

### B8. `routes/api.php` (extend)

- Dalam group `role:owner`:
  - `GET  /settings/payment`
  - `PATCH /settings/payment`
  - `POST  /settings/payment/qris`
  - `DELETE /settings/payment/qris`
  - `GET  /settings/mayar/test`
- Dalam group auth (semua user terautentikasi — staff buat link):
  - `POST /orders/{order}/mayar/link` → `[MayarController, 'link']`
- **Di luar** semua middleware auth/tenant (sejajar `/ops/backup`, seperti baris 31–32):
  - `POST /webhooks/mayar` → `[MayarController, 'webhook']` → `throttle:service-webhook`

---

## Frontend (Part B lanjutan)

### B9. `resources/js/lib/types.ts` (extend)

```ts
export interface PaymentSettings {
  bank_name: string | null;
  bank_account_name: string | null;
  bank_account_number: string | null;
  qris_image_url: string | null;
  mayar_enabled: boolean;
}
```

Extend `Order` dengan `mayar_link_id?`, `mayar_payment_url?`, `mayar_qr_url?`.

### B10. `resources/js/lib/api.ts` (extend)

- `getPaymentSettings()` → GET `/settings/payment`
- `updatePaymentSettings(body)` → PATCH `/settings/payment`
- `uploadQris(file: File)` → POST multipart `/settings/payment/qris`
- `deleteQris()` → DELETE `/settings/payment/qris`
- `testMayar()` → GET `/settings/mayar/test`
- `createMayarLink(orderId)` → POST `/orders/{id}/mayar/link`

### B11. `resources/js/pages/settings/SettingsPayment.tsx` (baru)

Tiga section:

1. **Bank details** — input nama bank, nama pemilik, nomor rekening. Save → `updatePaymentSettings()`.
2. **QRIS image** — file upload + preview + delete (mirror UX upload logo `SettingsProfile`).
3. **Mayar** — input API key (type password) + "Simpan" + tombol "Test koneksi" (→ `testMayar()` tampil ✅/❌). Catatan: "QRIS statis dipakai jika ada; Mayar dipakai jika tidak ada QRIS statis."

### B12. `resources/js/pages/settings/SettingsLayout.tsx` (extend)

Tambah tab **Pembayaran** → `/settings/payment`.

### B13. `resources/js/App.tsx` (extend)

Register route `/settings/payment` → `SettingsPayment`.

### B14. `resources/js/pages/Orders.tsx` — panel QRIS di drawer pembayaran

Ketika `paymentForm.metode === 'qris'`:

- Fetch settings sekali saat drawer terbuka (memo di state komponen).
- **Jika `qris_image_url`** → tampilkan gambar statis (gratis, diutamakan).
- **Else jika `mayar_enabled`** → saat QRIS dipilih, panggil `createMayarLink(order.id)`, tampilkan loading spinner, lalu render gambar `qr_url` + tombol link "Buka halaman pembayaran" (`payment_url`).
- **Else** → info text "QRIS belum dikonfigurasi" + link ke `/settings/payment`.
- Tombol "Catat Pembayaran" manual tetap ada untuk staff yang melihat pembayaran langsung. Mayar auto-confirm reload list via webhook (atau tombol refresh manual).

---

## Config / env

Tambah ke `.env.example`:

```
MAYAR_WEBHOOK_SECRET=
```

(random string untuk verifikasi signature webhook)

Tidak ada config file lain (API key per-tenant, disimpan encrypted di `tenant_settings`).

---

## Verification (per part)

1. **Part A:** `npm run build` — tanpa TS error, flow jalan (create → drawer terbuka → toast 'Pesanan dibuat').
2. **Migration + TenantSettings + PaymentSettings:** `php artisan migrate:fresh` (clean apply). `php artisan test` — encrypt/decrypt round-trip Mayar key; fallback `PaymentSettings` jalan tanpa konteks tenant.
3. **MayarService + MayarController + PaymentService refactor + routes:** `php artisan test` — `createPaymentLink` mocked → persist kolom; webhook idempotent (replay txId sama → cuma 1 payment); webhook verify signature; unauthorized `/orders/{id}/mayar/link` → 403; link id tidak ada → 200 no-op.
4. **Settings frontend + Orders drawer:** `npm run build` — settings page render, panel QRIS render semua 3 state.
5. **End-to-end (manual):** expose app (ngrok/expose), set sandbox Mayar key di Pengaturan Pembayaran, buat order, buka drawer pembayaran → lihat QRIS dinamis, bayar di sandbox Mayar → webhook auto-mark lunas.

---

## Ringkasan file baru/edit

| Aksi | Path |
|---|---|
| edit | `resources/js/pages/Orders.tsx` (Part A: 3 edits; Part B14: panel QRIS) |
| baru | `database/migrations/2026_08_12_000001_add_mayar_payment_columns.php` |
| edit | `app/Support/TenantSettings.php` (payment + Mayar keys/methods) |
| baru | `app/Support/PaymentSettings.php` |
| baru | `app/Services/MayarService.php` |
| baru | `app/Http/Controllers/Api/MayarController.php` |
| edit | `app/Services/PaymentService.php` (refactor `?User` + `tenant_id` eksplisit) |
| edit | `app/Http/Controllers/Api/SettingsController.php` (payment/uploadQris/deleteQris/testMayar) |
| edit | `routes/api.php` (settings + Mayar link + webhook routes) |
| edit | `resources/js/lib/types.ts` (PaymentSettings + Order Mayar fields) |
| edit | `resources/js/lib/api.ts` (6 method baru) |
| baru | `resources/js/pages/settings/SettingsPayment.tsx` |
| edit | `resources/js/pages/settings/SettingsLayout.tsx` (tab Pembayaran) |
| edit | `resources/js/App.tsx` (route /settings/payment) |
| edit | `.env.example` (`MAYAR_WEBHOOK_SECRET`) |
| baru | test untuk PaymentSettings, MayarService, MayarController webhook |

## Urutan kerja

1. Part A (3 edits) → `npm run build`.
2. Migration + `TenantSettings` + `PaymentSettings` → `php artisan migrate` + `php artisan test`.
3. `MayarService` + `MayarController` + refactor `PaymentService` + routes → `php artisan test` (HTTP mocked).
4. Settings frontend + Orders QRIS drawer → `npm run build`.
5. End-to-end sandbox test.

---

## Item terbuka — RESOLVED (2026-08-12)

1. **Label tombol:** ✅ `Buat Pesanan`.
2. **Model keamanan webhook:** ✅ **IP allowlist + amount+tenant heuristic** (bukan secret per-tenant — Mayar webhook tidak punya signature, tidak ada yang bisa diverifikasi). Risk: spoof false-positive. Acceptable per user.
3. **Field Mayar:** ✅ Verified via `mayar docs`. Dynamic QRIS `POST /hl/v2/qr-codes/create` returns `data.url` (QR PNG) + `data.amount`, **no id**. QRIS strategy: **dynamic QRIS primary**, webhook best-effort match by `data.amount` + tenant. Payment-link endpoint displaced.

### Implikasi desain dari resolusi

- `MayarService::createQrCode(int $amount): array` — hit `qr-codes/create`, return `['url'=>..., 'amount'=>...]`. No link id to persist.
- Migration: kolom `orders.mayar_qr_url` (cache PNG URL) + `orders.mayar_amount_cached` (untuk webhook match). Drop kolom `mayar_link_id` dari rencana awal.
- Webhook controller: resolve tenant via `X-Tenant` header atau path `{tenant}` + IP allowlist, lalu cari 1 pending order dengan `total == data.amount` di tenant itu. Idempotensi via `payments` unique on `(tenant_id, amount, external_ref)` dimana `external_ref` = `data.id` (webhook id).
- `.env.example`: `MAYAR_WEBHOOK_IP_ALLOWLIST=` (CSV, egress IPs Mayar — dicari saat sandbox test), bukan `MAYAR_WEBHOOK_SECRET`.
- Fitur "upload QRIS sendiri" opsional — bisa diganti fetch `GET /hl/v2/qr-codes/static`. Keputusan defer ke implementasi.
