# Katalog Page Redesign — POS + PWA UX Overhaul

> Redesign halaman `/catalog` (Katalog) jadi lebih familiar seperti POS, mobile-PWA-native.
> 3 tab (Produk/Kategori/Stok) ditingkatkan: grid tile POS-style, list/grid toggle,
> long-press restok, summary cards stok, cached instant-render, pull-to-refresh.
> Frontend-only. Backend API, route, controller, migration, payload shape tidak berubah.
>
> Diturunkan dari audit `resources/js/pages/catalog/` (3 panel, list-only, no cache).

---

## 1. Constraints (Hard)

| Aspek | Aturan |
|---|---|
| Backend | Tidak diubah. API route, controller, migration tetap. |
| Payload | Bentuk `Product`, `ProductUnit`, `Category`, `StockMovement`, `StockAlert` tetap. |
| Akses | Owner-only (route guard `ProtectedRoute roles={['owner']}`). |
| Bahasa | Label Indonesia. Ikuti konvensi existing (`STOCK_MOVEMENT_LABELS`, dll). |
| Design system | shadcn `ui/*` + `ios/*`. Tailwind v4 token (`bg-card`, `text-muted-foreground`, dll). Style base-luma. |
| Font | System stack (sudah baik). No font swap. |
| libs | Hanya yang sudah ada di `package.json`. Tidak tambah dep baru. |
| Orders precedent | Pull-to-refresh (`onTouchStart/Move/End` + delta 80px), `useCachedList` (sessionStorage TTL 5m), `ProductPicker` grid POS-style — pola yang sudah terbukti. |

---

## 2. Pain Points (Saat Ini)

| # | Masalah | Akibat |
|---|---|---|
| P1 | Produk = list rows, no POS tile grid | Tidak familiar POS |
| P2 | No category chip filter | Browse lambat |
| P3 | Stok multi-unit numpuk sebagai Badge kecil | Stok tidak glanceable |
| P4 | Fetch langsung tiap buka panel, no cache | Render lambat, no instant |
| P5 | No pull-to-refresh | Inconsistent dengan Orders |
| P6 | Form drawer footer tidak sticky | Total/tombol scroll off |
| P7 | Stok tab = history terkubur, no overview | Stock health tidak kelihatan |
| P8 | Kategori = bare list, no count | Lemah |
| P9 | No long-press quick action | Restok lambat (buka row → action menu) |

---

## 3. Solusi (A–F)

### A. POS-style tile grid + list/grid toggle (Produk)

Tile grid adaptasi `ProductPicker`:
`grid-cols-2 sm:grid-cols-3 lg:grid-cols-4`, tiap tile:
- Nama produk (truncate)
- Satuan + harga jual
- Stock chip (reuse `stockChipClass`: emerald/amber/destructive)
- **Tap → open edit drawer**
- **Long-press (500ms) → open restock drawer** (default unit)

List/grid toggle (`localStorage` key `catalog:view`, default `grid`):
- `grid` = tile grid
- `list` = `IosListRow` existing layout
- Tombol toggle di toolbar (icon `LayoutGrid` / `List`)

Desktop long-press fallback: `onContextMenu` (right-click) → restock drawer.

### B. Category chip scroller (Produk + Kategori)

Horizontal chip bar (wrap on mobile) filter by `category_id`:
"Semua" + tiap kategori. Active = `bg-primary text-primary-foreground`.
Komponen shared: `<CategoryChips categories={...} value={...} onChange={...} />`.

### C. Cached instant-render + pull-to-refresh

Migrasi `useState`+`useEffect` fetch → `useCachedList` (sessionStorage TTL 5m):
- Render cached page instantly on mount
- Background refresh non-blocking
- `refreshing` flag = shimmer bar (match Orders)

Pull-to-refresh: lift pattern dari `Orders.tsx` (touchstart/move/end, delta 80px, cek `scrollTop === 0`).

### D. Sticky form drawer footer (Produk)

Pin footer bawah drawer: tombol Batal kiri, Simpan/Buat kanan.
Selalu terlihat saat scroll form panjang (multi-unit).

### E. Stock summary cards (Stok)

3 card ringkas (reuse reports stat pattern, `Card size="sm"`):
- **Total SKU** — count dari `products` (atau `inventory/alerts` items total)
- **Stok Rendah** — `count` dari `api.list('inventory/alerts')` filter `is_out_of_stock=false`
- **Habis** — count dari alerts filter `is_out_of_stock=true`

No nilai stok (Rp) — itu ranah Reports.

### F. Category tiles + counts (Kategori)

Tile grid `grid-cols-2 sm:grid-cols-3`:
- Nama kategori
- **Product count badge** (`products_count` dari API; jika kosong, fetch produk dan count manual)
- Tap → edit drawer
- Long-press → delete confirm (opsional, match Produk pattern)

---

## 4. Struktur Komponen Baru

```
resources/js/
  hooks/
    use-long-press.ts          ← A (pointer events, 500ms, cancel on move)
  components/catalog/
    CategoryChips.tsx          ← B (shared Produk + Kategori filter)
    ProductTile.tsx            ← A
    ProductTileGrid.tsx        ← A (grid + skeleton + empty)
    ProductTileSkeleton.tsx    ← A
    StockSummaryCards.tsx      ← E
    CategoryTile.tsx           ← F
```

Panel yang diubah:
```
resources/js/pages/catalog/
  ProductsPanel.tsx   ← A + B + C + D (slim, compose)
  StockPanel.tsx      ← E + C
  CategoriesPanel.tsx ← F + B + C
```

---

## 5. Interaksi Detail

### Long-press (use-long-press hook)

```
onPointerDown → start timer 500ms
onPointerUp / onPointerLeave / onPointerCancel → clear timer
onPointerMove (delta > 10px) → clear timer (scroll cancel)
fire → onLongPress()
```

Bind di tile: `{...bind(tileRestock)}`. Plus `onContextMenu={(e) => { e.preventDefault(); tileRestock(); }}` untuk desktop.

### List/grid toggle

```ts
const [view, setView] = usePersistentState<'grid'|'list'>('catalog:view', 'grid');
```

Toggle button di toolbar kanan-atas Produk. State persist `localStorage`.

### Pull-to-refresh (shared)

Extract ke hook opsional `use-pull-to-refresh(refetch)` jika dipakai 3+ tempat.
Mulai: inline di tiap panel (match Orders), refactor hook di task terakhir bila redundansi jelas.

---

## 6. Out of Scope

- Backend API/migrations/validation
- PWA manifest/SW/service-worker (Phase 1 sudah done)
- Orders/Reports/Users pages
- Nilai stok (Rp) — ranah Reports
- New npm dependencies

---

## 7. Verifikasi

| Gate | Cara |
|---|---|
| Build | `npm run build` (TS + Vite) bersih |
| Tipe | `npx tsc --noEmit` lulus |
| E2E | `npx playwright test` lulus (auth, layout-width, rbac) |
| Manual | 375px / 768px / 1280px: grid responsive, toggle persist, long-press fires restock, pull-to-refresh, sticky footer, cache instant-render |
| Console | No error, haptic fire, toast on save/delete |

---

## 8. Risiko / Tradeoff

| Risiko | Mitigasi |
|---|---|
| Tile grid buruk untuk SKU 50+ | Search + category chips jadi primary filter; tile untuk hasil filtered. List mode toggle jadi escape hatch. |
| Long-press konflik scroll mobile | `onPointerMove` delta > 10px cancel timer; `touch-action` default. |
| `products_count` null di API | Fallback: count dari list produk yang sudah loaded. |
| Cache stale saat restock | `onInventoryChange` callback sudah ada — panggil `refetch` dari parent. |
