# Orders Performance Revision — Task Breakdown

> Checklist eksekusi untuk `docs/ORDERS_PERF_REVISION_PLAN.md`.
> Tiap task: verifikasi sebelum lanjut. Tandai `[x]` saat selesai.

---

## Phase R1.A — Pangkas Payload List (H)

Tujuan: list orders hanya kirim kolom yang dipakai, bukan eager-load semua items+user.

- [x] R1.A.1 Di `OrderController::index()`, ganti `Order::with(['items', 'user'])` dengan `->select()` minimal: `id, user_id, invoice_no, status, customer_name, order_date, grand_total, total_paid, version, tenant_id, created_at, updated_at, deleted_at`
- [x] R1.A.2 Tambah `->withCount('items')` supaya bisa tampilkan "X item" di row (data gratis, no extra query)
- [x] R1.A.3 Verifikasi filter staff (`user_id`) tetap jalan — `user_id` harus ada di `select()`
- [x] R1.A.4 Tambah test `test_list_does_not_include_items_array` di `OrderTest.php` — assert `data.0.items` tidak ada; assert `data.0.items_count` ada
- [x] R1.A.5 Verifikasi `payment_status` accessor tetap di-resolve (dari `total_paid` + `grand_total`, tidak butuh relation)
- [x] R1.A.6 Jalankan `php artisan test --filter=OrderTest` — semua green (17/17, 63 assertions)

---

## Phase R1.B — Client-Side List Cache (M)

Tujuan: render instan dari cache saat buka halaman Pesanan.

- [x] R1.B.1 Buat `resources/js/lib/useCachedList.ts` — hook generik:
  - Key: `cached-list:{resource}:{hash(filters)}`
  - Store di `sessionStorage`: `{ data, last_page, total, timestamp }`
  - On mount: baca cache → set state instan → fetch background → update jika beda
  - Return: `{ data, loading, refreshing, refetch }`
- [x] R1.B.2 Refactor `Orders.tsx` — ganti `orders`/`loading` manual state dengan `useCachedList<Order>('orders', api, { search, status, month, year, sort, page, per_page })`
- [x] R1.B.3 Pertahankan debounce search 300ms (existing `useEffect` timer)
- [x] R1.B.4 Saat cache ada → tampilkan data + indikator "diperbarui" halus; saat fetch selesai → replace tanpa flash
- [x] R1.B.5 `npm run build` clean, no TS error

---

## Phase R1.C — Loading States (M)

Tujuan: user selalu tahu app sedang bekerja, tidak mengira "stuck".

- [x] R1.C.1 Buat `resources/js/components/orders/OrderRowSkeleton.tsx` — meniru layout `OrderRow`: dua badge kecil + invoice line + customer/date line + amount
- [x] R1.C.2 Initial load (no cache): render 6× `OrderRowSkeleton` (bukan 2 generic skeleton)
- [x] R1.C.3 Refetch saat filter ganti: shimmer bar tipis di atas data existing (bukan replace)
- [x] R1.C.4 Pagination: set `pageLoading` state, tampilkan "Memuat..." di bawah list
- [x] R1.C.5 `npm run build` clean

---

## Phase R2.A — Install Android Detection (M)

Tujuan: Android non-Chrome (Firefox, Samsung, Opera) juga lihat install card.

- [x] R2.A.1 Tambah `isAndroidBrowser()` di `useInstallPrompt.ts` — UA mengandung `android` tapi bukan `windows`
- [x] R2.A.2 Hook: jika `isAndroidBrowser() && !isStandalone() && !isInstallDismissed()` → set `canInstall = true`
- [x] R2.A.3 Expose `platform: 'ios' | 'android' | 'desktop'` dan `hasNativePrompt: boolean` (true jika `beforeinstallprompt` pernah fire)
- [x] R2.A.4 `npm run build` clean

---

## Phase R2.B — Platform-Aware Install Sheet (M)

Tujuan: Android non-Chrome dapat instruksi manual yang relevan.

- [x] R2.B.1 Di `InstallAppCard.tsx`, buat sheet content conditional:
  - iOS (existing): Share → Add to Home Screen → Add
  - Android: Chrome menu (⋮) → Add to Home Screen / Install app → Add
- [x] R2.B.2 Button label per platform:
  - Android + native prompt: "Pasang ke layar utama" (call `promptInstall()`)
  - Android no native prompt: "Cara pasang di Android" (open sheet)
  - iOS: "Cara pasang di iPhone/iPad" (existing)
- [x] R2.B.3 `npm run build` clean

---

## Phase R2.C — Nudge Toast Update (L)

Tujuan: nudge toast di AppShell juga handle Android non-Chrome.

- [x] R2.C.1 Di `AppShell.tsx`, cek `hasNativePrompt`: jika true → action "Pasang" call `promptInstall()`; jika false → action "Buka Settings" arahkan ke `/settings` (di mana card install ada)
- [x] R2.C.2 Toast description Android: "Buka Settings untuk memasang ke layar utama."
- [x] R2.C.3 `npm run build` clean

---

## Phase R3 — Verification & Ship (H)

- [x] R3.1 `php artisan test` full suite — 208 passed, 2 pre-existing failures (LoginBrandingTest, TenantExportTest) — no new regressions
- [x] R3.2 `npm run build` clean
- [ ] R3.3 Manual: buka halaman Pesanan → ganti filter → pastikan shimmer/pagination loading terlihat *(deferred to device testing)*
- [ ] R3.4 Manual: buka di Android non-Chrome browser → pastikan install card muncul + sheet instruksi benar *(deferred to device testing)*
- [x] R3.5 Commit: `perf(orders): trim list payload + client cache + loading states` *(split into 3 focused commits: ca955f1, 3a79497, c0e75f8)*
- [x] R3.6 Commit: `feat(pwa): Android install instructions for non-Chrome browsers` *(split into 3 focused commits: c6d9a8b, 6a11e00, 483a952)*
- [x] R3.7 Update `docs/ORDERS_PERF_REVISION_PLAN.md` status ke DONE
