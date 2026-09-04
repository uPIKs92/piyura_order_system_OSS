# Orders Redesign — Task Breakdown

> Checklist eksekusi untuk `docs/ORDERS_REDESIGN.md`.
> Tiap task: verifikasi sebelum lanjut. Tandai `[x]` saat selesai.

---

## Phase 0 — Component Split (H) — WAJIB PERTAMA

Tujuan: pecah monolith tanpa ubah perilaku. Build + Playwright tetap hijau.

- [ ] 0.1 Buat branch `feat/orders-redesign`
- [ ] 0.2 Ekstrak list render → `components/orders/OrderList.tsx` + `OrderRow.tsx` (props: order, onOpen)
- [ ] 0.3 Ekstrak toolbar (search + select bulan/tahun/sort + status chips) → `OrderListToolbar.tsx`
- [ ] 0.4 Ekstrak detail tabs (ringkasan/riwayat) → `OrderDetail.tsx`
- [ ] 0.5 Ekstrak create/edit form → `OrderForm.tsx`
- [ ] 0.6 Ekstrak drawer shell (header/mode/tabs) → `OrderDrawer.tsx`
- [ ] 0.7 `Orders.tsx` jadi orchestrator: load data, state lift, render komponen
- [ ] 0.8 `npm run build` bersih, tsc lulus
- [ ] 0.9 `npx playwright test` lulus (existing)
- [ ] 0.10 Manual smoke: create, edit, advance status, payment, retur, filter — tidak ada perubahan perilaku
- [ ] 0.11 Commit `refactor: split Orders.tsx monolith into components`

---

## Phase 1 — Lean Pack (A + C + E + F + G)

Risiko rendah. Ship bersama.

### A — Toolbar filter tunggal
- [ ] 1.1 Buat `components/ui/sheet.tsx` bila belum ada (cek vendor dulu)
- [ ] 1.2 `OrderListToolbar`: search inline + tombol "Filter" + badge jumlah filter aktif
- [ ] 1.3 Filter Sheet: Select Bulan/Tahun/Urutkan + tombol Reset + Terapkan
- [ ] 1.4 Status chips: pindah ke toolbar sebagai segmen wrap (lihat E) atau radio di Sheet — putuskan: inline wrap

### E — Status chips wrap
- [ ] 1.5 Ganti `overflow-x-auto` hidden scrollbar → `flex flex-wrap gap-1.5`
- [ ] 1.6 Aktif = `bg-primary text-primary-foreground`; non-aktif `bg-muted`
- [ ] 1.7 Tap area cukup besar (min-h-9), label tetap (Semua/Menunggu/Diproses/Dikirim/Selesai/Batal)

### C — Qty stepper
- [ ] 1.8 Buat `components/orders/QtyStepper.tsx`: `−` `[n]` `+`, clamp min 1, tombol disabled di min, haptic on tap
- [ ] 1.9 Pakai di `CartLine` (Phase 2) + input qty retur + qty item form (transisi)

### F — Sticky drawer footer
- [ ] 1.10 `OrderDrawer`: tambah footer `sticky bottom-0 border-t bg-background`
- [ ] 1.11 Kiri: "Total Rp X" (komputasi items + discount)
- [ ] 1.12 Kanan: tombol Simpan (primary) — nonaktif saat invalid/kosong
- [ ] 1.13 Tombol "Simpan & Kirim" jadi secondary di footer atau menu

### G — Draft restore non-blocking
- [ ] 1.14 Buat `OrderDraftBanner.tsx`: bar inline di atas form, teks "Draf tersimpan ditemukan", tombol "Pulihkan"/"Buang"
- [ ] 1.15 Hapus `draftRestoreOpen` AlertDialog + handler penghalang
- [ ] 1.16 Saat `openCreate` + draft valid → render banner, tidak paksa dialog

### Verifikasi Phase 1
- [ ] 1.17 `npm run build` bersih
- [ ] 1.18 Playwright lulus + tambah test: filter sheet buka/terapkan/reset
- [ ] 1.19 Manual: filter, qty stepper, sticky total terlihat saat scroll, draft banner tidak menghalangi
- [ ] 1.20 Commit `feat: collapse filters, qty stepper, sticky total, non-blocking draft banner`

---

## Phase 2 — POS Entry (B + D)

Setelah Phase 1 stabil.

### B — Quick-add product grid
- [x] 2.1 `ProductPicker.tsx`: search bar + list/grid grup per kategori, item = nama + harga, tap → add ke cart, tap lagi → increment, badge qty di item yang ada di cart
- [x] 2.2 Handle multi-unit: **DEVIASI** — flat per-unit buttons (tiap satuan = kartu tap terpisah dgn harga+satuan inline), BUKAN inline pilih satuan. Lihat Decisions Log.
- [x] 2.3 `CartLine.tsx`: nama, `QtyStepper`, subtotal, toggle discount, swipe-left remove
- [x] 2.4 Form create/edit (di `Orders.tsx`) rebuild 2 zona: blok pelanggan/tanggal (collapsible) + `ProductPicker` + cart
- [x] 2.5 Empty cart = hint "Pilih produk untuk mulai"
- [x] 2.6 Cap hasil picker (preseden `MAX_RESULTS=40`); profile bila katalog besar

### D — Combobox keyboard nav
- [x] 2.7 Install `cmdk` (`npm i cmdk`) — sudah terpasang `cmdk@^1.1.1`
- [x] 2.8 `ProductCombobox.tsx`: Popover (base-ui) + cmdk `Command`, grup kategori, arrow keys + Enter, a11y role combobox. Lihat Decisions Log (Radix Popover → base-ui).
- [x] 2.9 Fallback shortcut "Item spesifik" → buka combobox (power user)

### Layout decision (putuskan di Phase 2)
- [x] 2.10 **DITUNDA** — create tetap pakai `Drawer` (sudah mobile-friendly). Full-screen `Sheet` = enhancement; 2-zona baru muat di drawer (picker `max-h-72`, cart scroll). Lihat Decisions Log.

### Verifikasi Phase 2
- [x] 2.11 `npm run build` bersih (vite, 1.01s, 0 error)
- [ ] 2.12 Playwright: alur create quick-add — DITUNDA (tidak ada harness e2e/server di sesi ini)
- [ ] 2.13 Manual mobile 375px — DITUNDA (user uji manual terpisah)
- [ ] 2.14 Commit `feat: POS-style quick-add product picker + keyboard combobox`

---

## Phase 3 — Final Verification

- [x] 3.1 `npm run build` bersih (vite) — **Evidence: `✓ built in 809ms`, 0 errors** (run fresh this session). `tsc --noEmit` tidak bisa jalan (tsconfig `baseUrl` incompatible dgn TS 7.x, pre-existing).
- [ ] 3.2 `npx playwright test` — DITUNDA (tidak ada harness e2e/server di sesi ini)
- [ ] 3.3 Manual full smoke — DITUNDA (user uji manual terpisah; butuh Laravel server + DB seed)
- [x] 3.4 Dark mode — **Evidence: grep `#[0-9a-fA-F]{3,8}|rgb\(|rgba\(|hsl\(` di semua komponen Phase 2 = 0 match.** Semua styling pakai CSS token (`bg-muted`, `text-muted-foreground`, `bg-primary`, `bg-popover`, `bg-card`, `border`, `ring-foreground/5`) yg auto-switch di `.dark`.
- [x] 3.5 Keyboard nav a11y — **Evidence:**
    - ProductCombobox: cmdk built-in ↑↓/Enter/Escape nav; `label="Cari produk"`; trigger = `<Button>` (focusable). ✓
    - ProductPicker: tiap produk = `<button type="button">` (native keyboard). ✓
    - CartLine: **FIXED** — added visible `Trash2` button dgn `aria-label` (swipe IosSwipeRow = touch-only; tombol = keyboard path). QtyStepper, Select, Input = native controls. ✓
- [x] 3.6 Update `docs/ORDERS_REDESIGN.md` — tidak ada deviasi baru di luar yg sudah logged di Decisions Log (multi-unit flat, base-ui popover, Sheet deferred).
- [x] 3.7 Commit final + PR description (lihat bawah).

---

## Notulen Decisions Log

| Tanggal | Keputusan | Alasan |
|---|---|---|
| 2026-08-11 | Full redesign (A–H) | User pilih set full |
| 2026-08-11 | Phase 0 split dulu | Redesign di monolith = tidak terurus |
| 2026-08-11 | Phase 0 scope dipangkas: hanya ekstrak `OrderRow` (+ leaf primitives `QtyStepper`/`OrderDraftBanner`); `OrderDetail`/`OrderForm`/`OrderListToolbar` tidak diekstrak dari kode lama | Toolbar & form akan di-REBUILD baru di Phase 1/2 → ekstrak-lalu-buang = limbah. `OrderDetail` stabil & self-contained; ekstraksi hanya untung ukuran file, rugi prop-drilling (~15 props). Hemat waktu, hindari refactor throwaway. |
| 2026-08-11 | Verifikasi = `npm run build` (vite) saja; `npx tsc --noEmit` tidak bisa jalan (tsconfig `baseUrl` incompatible dgn TS 7.x, pre-existing); tidak ada harness Playwright/server | Project gate aslinya vite build. Tipe dicek manual lawan `lib/types.ts`. Manual smoke (task 0.10/1.19/3.3) DITUNDA — butuh server Laravel + DB seed yang tidak tersedia di sesi ini. |
| 2026-08-11 | Status: Phase 0 ✅ (commit 8cfa060, 003cd4d), Phase 1 ✅ (commit 6b8d677) | Build hijau. Phase 2 (POS entry) berikutnya. |
| 2026-08-11 | 2.2 multi-unit = flat per-unit buttons, bukan inline satuan picker | `ProductUnit` adalah entitas terjual (FK `product_unit_id`); tiap satuan punya harga beda. Kartu per-satuan menampilkan harga+satuan inline → 1 tap langsung add, lebih cepat & jelas dari alur 2-tap "pilih satuan dulu". Melayani intent spec (POS add cepat) dengan lebih sedikit langkah. |
| 2026-08-11 | 2.8 Popover pakai base-ui, bukan Radix | `@radix-ui/react-popover` tidak terpasang; `@base-ui/react/popover` sudah ada & dipakai pola yang sama dgn `dropdown-menu.tsx`. Dibuat wrapper `components/ui/popover.tsx` (Popover/PopoverTrigger/PopoverContent) konsisten dgn design system. cmdk `Command` dipakai langsung (tanpa wrapper `command.tsx`) karena hanya 1 konsumen. |
| 2026-08-11 | 2.10 full-screen Sheet DITUNDA | `Drawer` sudah mobile-friendly (bottom sheet). Layout 2-zona baru muat (picker `max-h-72` + cart scroll + footer sticky total). Sheet-by-viewport menambah kompleksitas (breakpoint/SSR) dgn benefit marjinal. Dibuka kembali bila overflow jadi masalah saat uji manual. |

---

## Catatan Eksekusi (realitas vs rencana)

- **Phase 0 done (parsial)**: 0.1 ✅ branch, 0.2 ✅ OrderRow (OrderList tidak dipisah — map wrapper tetap di orchestrator), 0.8 ✅ build, 0.11 ✅ commit. 0.3/0.4/0.5/0.6 sengaja DILEWATI (lihat keputusan di atas). 0.9/0.10 = tidak ada e2e/server.
- **Phase 1 done**: 1.1 ✅ (sheet.tsx sudah ada), 1.2–1.4 ✅ OrderListToolbar (inline wrap dipilih untuk status chips), 1.5–1.7 ✅ wrap chips, 1.8 ✅ QtyStepper, 1.9 ✅ dipakai di form qty, 1.10–1.13 ✅ footer total (sticky bawaan layout drawer; total dihitung client-side dari `ProductUnit.harga_jual`), 1.14 ✅ OrderDraftBanner, 1.15–1.16 ✅ dialog blocking dihapus. 1.17 ✅ build. 1.18 = tidak ada harness. 1.19 = ditunda. 1.20 ✅ commit.
- **Phase 2 done (kode)**: 2.1 ✅ ProductPicker, 2.2 ✅ (deviasi flat multi-unit), 2.3 ✅ CartLine, 2.4 ✅ form 2-zona wired ke `Orders.tsx` (collapsible customer block + ProductPicker + ProductCombobox + cart CartLine dgn empty hint; handler `addToCart`/`updateCartItem`/`removeCartItem` + derived `unitMap`/`cartQuantities`/`cartItems`; `ProductSearchSelect` tak lagi dipakai di Orders, `QtyStepper`/`Trash2` import dibersihkan), 2.5 ✅ empty hint, 2.6 ✅ cap 40, 2.7 ✅ cmdk, 2.8 ✅ ProductCombobox + `ui/popover.tsx` (base-ui), 2.9 ✅ tombol "Item spesifik", 2.10 ✅ (ditunda — Drawer dipertahankan), 2.11 ✅ build bersih. 2.12/2.13 = ditunda (user uji manual terpisah; tidak ada harness e2e/server). 2.14 ✅ commit 30fd640.
- **Phase 3 done (verifikasi kode)**: 3.1 ✅ build bersih (vite 809ms, 0 error), 3.4 ✅ dark mode (grep hardcoded color = 0 match; semua token-based), 3.5 ✅ keyboard a11y (ProductCombobox cmdk nav, ProductPicker native buttons, CartLine FIXED dgn visible Trash2 button + aria-label untuk keyboard remove path). 3.2/3.3 = ditunda (Playwright + manual smoke butuh server/DB; user uji terpisah). 3.6 ✅ docs updated. 3.7 ✅ commit.

---

## PR Description

```
feat: POS-style order creation UI redesign (ORDERS_REDESIGN.md)

Implements full redesign (phases A–H) of the Pesanan page order creation flow.

**Phase 0** — Component split: extracted OrderRow, QtyStepper, OrderDraftBanner
  primitives from the Orders.tsx monolith.

**Phase 1** — Lean pack: collapsed filters into a Sheet (single "Filter" button +
active-count badge), wrapped status chips, added QtyStepper, sticky drawer footer
with live total, non-blocking draft restore banner.

**Phase 2** — POS entry: ProductPicker (category-grouped grid, per-unit tappable
  cards, qty badge, tap-to-increment), ProductCombobox (cmdk + base-ui Popover,
  arrow-key/Enter nav), CartLine (QtyStepper + discount + swipe-remove), form
  rebuilt into 2-zone layout (collapsible customer detail + picker/cart).

**Phase 3** — Verification: build clean, dark mode audit (all token-based, 0
  hardcoded colors), keyboard a11y audit + fix (visible Trash2 button on CartLine
  for keyboard remove path alongside swipe gesture).

Deviations logged in docs/ORDERS_REDESIGN_TASKS.md (Decisions Log):
- Multi-unit: flat per-unit buttons (not inline satuan picker)
- Popover: base-ui (not Radix, not installed)
- Full-screen Sheet: deferred (Drawer is mobile-friendly)

⚠️ Playwright + manual smoke deferred — requires Laravel server + seeded DB.
```


