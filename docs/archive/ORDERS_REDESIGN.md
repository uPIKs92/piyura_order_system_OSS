# Orders Page Redesign — Pesanan UX Overhaul

> Redesign halaman `/orders` (Pesanan) jadi lebih cepat dipakai: filter ramping,
> quick-add POS-style, qty stepper, combobox keyboard nav, sticky total, draft restore non-blocking.
> Frontend-only. Backend API, status flow, payload shape tidak berubah.
>
> Diturunkan dari audit `resources/js/pages/Orders.tsx` (1259 baris, monolith).

---

## 1. Constraints (Hard)

| Aspek | Aturan |
|---|---|
| Backend | Tidak diubah. API route, controller, migration tetap. |
| Status flow | `draft→pending→diproses→dikirim→selesai`; `cancelled` hanya dari draft/pending. Sumber: `lib/orderStatus.ts` (mirror `App\Enums\OrderStatus`). |
| Payload | Bentuk `OrderFormData` tetap: `customer_name`, `customer_phone`, `order_date`, `notes`, `items[{product_unit_id, quantity, discount_type?, discount_value?}]`, `status`, `version`. |
| Bahasa | Label Indonesia. Ikuti konvensi `STATUS_LABELS`. |
| Design system | shadcn `ui/*` + `ios/*`. Tailwind v4 token (`bg-card`, `text-muted-foreground`, dll). Style base-luma. |
| Font | System stack SF Pro (sudah baik). No font swap. |
| Create flow | Default status `draft`. `submitAndSend` = create lalu update ke pending (2 call API). Perilaku dipertahankan. |
| Draft autosave | Interval 5s menulis seluruh form ke DB. Logika tetap; hanya presentasi yang berubah. |

---

## 2. Pain Points (Saat Ini)

| # | Masalah | Akibat |
|---|---|---|
| P1 | Filter area makan layar: search + 3 Select (Bulan/Tahun/Urutkan) + ToggleGroup status horizontal | List tertekan ke bawah ~4 baris di mobile |
| P2 | Form create lambat: tiap item = Card terpisah, alur cari→pilih→qty diulang | Entri banyak item = lambat |
| P3 | `ProductSearchSelect` custom dropdown, tanpa keyboard nav | Power user yang mengetik terganggu |
| P4 | Input qty = raw number | Tidak mobile-friendly |
| P5 | Chip status scrollbar horizontal tersembunyi | "Semua"/"Batal" mudah terlewat |
| P6 | Drawer form panjang, total + tombol simpan bisa ter-scroll keluar | Konteks hilang saat item banyak |
| P7 | AlertDialog draft restore menghalangi saat buat | Interupsi alur |
| P8 | `Orders.tsx` 1259 baris, 30+ `useState` monolith | Susah dirawat, redesign berisiko |

---

## 3. Solusi (A–H)

### A. Toolbar filter tunggal (sticky)
Search inline full-width. Tombol "Filter" → bottom Sheet berisi Bulan/Tahun/Urutkan + Reset/Terapkan, badge jumlah saat filter aktif. Bebaskan ~2 baris.

### B. Quick-add product grid (POS-style)
Form create diubah jadi 2 zona: blok pelanggan/tanggal (atas, collapsible) + `ProductPicker` (grid cari per kategori, tap produk → tambah ke cart, tap lagi → increment, badge qty di item yang sudah ada) + cart `CartLine` di bawah footer sticky. Menggantikan alur Card-per-item.

### C. Qty stepper
`− [1] +` clamp min 1. Dipakai di `CartLine` + input retur.

### D. Combobox keyboard nav
Ganti custom dropdown jadi Radix `Popover` + cmdk (`Command`). Arrow keys + Enter, grup per kategori. Fallback power-user. Aksesibel (role combobox).

### E. Status chips = wrap
`flex-wrap`, bukan scrollbar horizontal tersembunyi. Semua 7 chip terlihat. Active = `bg-primary`.

### F. Sticky drawer footer
Pin bawah: "Total Rp X" (komputasi dari items) kiri, tombol Simpan kanan. Selalu terlihat saat scroll form. Mobile: pertimbangkan full-screen `Sheet` untuk create; desktop tetap drawer.

### G. Draft restore non-blocking
Ganti `draftRestoreOpen` AlertDialog jadi banner inline di atas form saat buka create + ditemukan draft: "Draf tersimpan ditemukan" + "Pulihkan" / "Buang". Tidak menghalangi; user boleh mulai baru.

### H. Split komponen
Ekstrak list, form, detail, picker, cart, combobox, stepper jadi komponen terpisah di `components/orders/`. State lift di `Orders.tsx`. No premature context/store.


---

## 4. Struktur Komponen Baru

```
resources/js/components/orders/
  OrderListToolbar.tsx     ← A + E
  OrderList.tsx
  OrderRow.tsx
  OrderDrawer.tsx          ← F
  OrderForm.tsx            ← B + C + D
  OrderDetail.tsx
  ProductPicker.tsx        ← B
  CartLine.tsx             ← C
  ProductCombobox.tsx      ← D
  OrderDraftBanner.tsx     ← G
  QtyStepper.tsx           ← C (generic)
```
`resources/js/pages/Orders.tsx` = orchestrator: load data, state lift, routing ke komponen.

---

## 5. Phases

Eksekusi berurutan. Tiap phase = slice independen yang bisa diverifikasi + diship terpisah.

### Phase 0 — Component split (H) — WAJIB PERTAMA
Split monolith tanpa ubah perilaku. Redesign di atas monolith = tidak terurus.

### Phase 1 — Lean pack (A + C + E + F + G)
Risiko rendah, dampak tinggi.
- `OrderListToolbar` (A)
- Status chips wrap (E)
- `QtyStepper` + `CartLine` (C)
- Sticky footer `OrderDrawer` (F)
- `OrderDraftBanner` (G)

### Phase 2 — POS entry (B + D)
Setelah Phase 1 stabil.
- `OrderForm` rebuild 2 zona (B)
- `ProductPicker` (B)
- `ProductCombobox` via cmdk (D)

### Phase 3 — Verification
- `npm run build` (tsc + vite) bersih
- `npx playwright test` lulus
- Manual: create (quick-add), edit, advance status, payment, retur, draft banner, filter, empty/disabled state
- Viewport mobile 375px
- Tambah Playwright test alur create baru + filter sheet

---

## 6. Out of Scope

- Bottom nav (`AppNav`) tidak diubah
- `OrderStatusActions` (detail) — pertahankan, restyle hanya bila perlu
- Drawer payment/retur/invoice — pertahankan, restyle hanya bila perlu
- Backend, route, tipe

---

## 7. Risks & Mitigation

| Risiko | Mitigasi |
|---|---|
| Perf grid quick-add pada katalog besar | Virtualisasi / cap hasil (preseden `MAX_RESULTS=40`). Profile dulu. |
| Drawer overflow: sticky footer + picker + cart | Mobile create = full-screen `Sheet`, desktop tetap drawer. Putuskan di Phase 2. |
| Combobox a11y | cmdk + Radix Command handle keyboard; uji screen reader; pertahankan grup kategori. |
| Draft autosave 5s menulis seluruh form | Tetap; banner hanya ubah presentasi. |

---

## 8. Skills Diterapkan Saat Implementasi

`frontend-ui-engineering`, `incremental-implementation`, `test-driven-development`,
`code-simplification`, `verification-before-completion`, `design-taste-frontend`, `minimalist-ui`.

---

## 9. Definisi Selesai

- Build + typecheck bersih
- Playwright lulus (existing + baru)
- Perilaku runtime terverifikasi manual (create/edit/status/payment/retur/draft/filter)
- Tidak ada regresi
- Dokumen ini + task breakdown ter-update bila ada deviasi
