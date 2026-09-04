# Orders Performance Revision — Plan

> **Status: ✅ IMPLEMENTATION COMPLETE** — All 7 phases done (R1.A–R3).
> Code committed; manual device tests (R3.3, R3.4) deferred.
>
> Revisi performa untuk halaman **Pesanan** (Orders list) + **Pasang Aplikasi** (Install UX).
> Lihat `ORDERS_PERF_REVISION_TASKS.md` untuk checklist eksekusi.

---

## Context

Setelah Phase 1 PWA polish selesai, ada dua keluhan pengguna:

1. **Halaman Pesanan terasa "stuck"** saat load pertama / ganti filter / paginasi.
2. **"Pasang Aplikasi"** hanya muncul untuk Android Chrome (via `beforeinstallprompt`) dan iOS Safari.
   Browser Android lain (Firefox, Samsung Internet, Opera) tidak melihat kartu install sama sekali.

### Audit temuan

#### Halaman Pesanan — root cause

**Bukan N+1, tapi over-fetching yang lebih buruk:**

```php
// OrderController::index() — baris 32
$query = Order::with(['items', 'user']);
```

Komponen `OrderRow.tsx` yang merender setiap baris list **hanya membaca kolom skalar**:

- `invoice_no`, `status`, `customer_name`, `order_date`, `grand_total`, `payment_status` (accessor)

Tapi query eager-load `items[]` (5–10 baris per order) + `user` untuk **setiap order di setiap page**.
Hasilnya: 20 order × ~5 item = **~100 baris item + 20 objek user yang tidak perlu** diserialisasi ke JSON.
Di koneksi 3G/4G lemot, payload membengkak inilah yang terasa "stuck".

**Detail endpoint sudah benar** — `show()` line 93 memang perlu `items.product`, `payments`, dll.

**Frontend tidak punya loading state untuk refetch:**
`loading` state hanya `true` saat initial mount. Saat ganti filter / paginasi, list lama
dipertontonkan tanpa indikator sampai data baru tiba → user mengira app nge-hang.

#### Install UX — root cause

Hook `useInstallPrompt` hanya set `canInstall = true` pada dua kasus:

- `beforeinstallprompt` fires (Android Chrome/Edge) ✅
- `isIosSafari()` true → iOS sheet ✅
- **Android Firefox / Samsung Internet / Opera** → `canInstall` tetap `false`, card tidak muncul ❌

Sheet instruksi hanya ada untuk iOS. Tidak ada panduan install untuk Android non-Chrome.

---

## Decisions

- **Pangkas payload list, bukan tambah pagination.** Pagination sudah ada (20/page).
  Masalahnya adalah setiap page terlalu gemuk karena eager-load `items` + `user`.
  Dengan `select()` + `withCount('items')`, payload turun ~60–80% tanpa ubah UX.
- **Client-side cache via sessionStorage, bukan React Query.** App ini tidak pakai
  query library dan tidak perlu menambah dependency baru untuk satu hook. Cache
  sessionStorage cukup untuk render instan saat switch tab / reload PWA.
- **Service worker SWR sudah cukup.** `public/sw.js` sudah cache `/api/orders`
  stale-while-revalidate. Kita tidak sentuh SW — fokus di backend payload + client cache.
- **Install card jadi platform-aware.** Android non-Chrome tetap lihat card + sheet
  instruksi manual (Chrome menu → Add to Home Screen). Tidak perlu deteksi per-browser
  yang rumit — cukup `isAndroidBrowser()`.

---

## Phase R1 — Orders List Performance

### R1.A — Pangkas payload (backend, impact terbesar)

| Task | File |
|------|------|
| Ganti `->with(['items','user'])` → `->select([...])` + `->withCount('items')` | `OrderController::index()` |
| Filter staff tetap butuh `user_id` di select | same |
| Test: assert list response tidak punya `items` array | `OrderTest.php` |
| Opsional: tampilkan `{items_count} item` di OrderRow subtitle | `OrderRow.tsx` |

**Estimasi pengurangan payload:** ~60–80% lebih kecil untuk 20-order page.

### R1.B — Client-side list cache (instant di HP)

| Task | File |
|------|------|
| Hook generik `useCachedList` — sessionStorage keyed per-filter | `resources/js/lib/useCachedList.ts` (new) |
| Render cache lama instan → fetch background → update saat data beda | `Orders.tsx` |
| Debounce search tetap 300ms (existing) | same |

**Hasil:** Buka app → list dari sesi sebelumnya muncul <50ms → refresh background diam-diam.

### R1.C — Loading states (dari plan sebelumnya, refined)

| Task | File |
|------|------|
| Skeleton realistis yang meniru OrderRow layout | `OrderRowSkeleton.tsx` (new) |
| Initial load (no cache) → 6× skeleton | `Orders.tsx` |
| Filter change → shimmer bar di atas data existing | same |
| Pagination → "Memuat..." text di bawah | same |

---

## Phase R2 — Install UX Android

### R2.A — Platform detection

| Task | File |
|------|------|
| Tambah `isAndroidBrowser()` detection | `useInstallPrompt.ts` |
| Hook set `canInstall = true` untuk Android non-Chrome | same |
| Expose `platform: 'ios' \| 'android' \| 'desktop'` + `hasNativePrompt` | same |

### R2.B — Platform-aware install sheet

| Task | File |
|------|------|
| Android sheet: 3-step Chrome menu (⋮) → Add to Home Screen | `InstallAppCard.tsx` |
| iOS sheet: tetap (existing 3-step Share) | same |
| Button label per platform | same |

### R2.C — Nudge toast

| Task | File |
|------|------|
| Android tanpa native prompt → action buka Settings page | `AppShell.tsx` |

---

## Out of scope

- **Tidak** ubah SW caching pattern (sudah SWR, sudah bagus).
- **Tidak** tambah React Query / TanStack Query (dependency baru, tidak worth untuk 1 hook).
- **Tidak** sentuh detail endpoint `show()` (sudah benar — perlu full relations).
- **Tidak** ubah pagination page size (20 sudah reasonable).
- **Phase 2 Capacitor** tetap LATER.

---

## Execution order

1. **R1.A** dulu (payload lebih kecil = semua jadi cepat, termasuk cache).
2. **R1.B** (client cache untuk instant render).
3. **R1.C** (skeleton untuk initial load yang sebenarnya).
4. **R2.A–C** (install Android).
5. `php artisan test` + `npm run build` + commit per phase.
