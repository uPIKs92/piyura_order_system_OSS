# Katalog Redesign — Task Breakdown

> Checklist eksekusi untuk `docs/CATALOG_REDESIGN.md`.
> Tiap task: verifikasi sebelum lanjut. Tandai `[x]` saat selesai.
> Urutan: Phase 0 → 1 → 2 → 3 → 4 → 5. Tiap phase build hijau sebelum lanjut.

---

## Phase 0 — Prerequisite

- [x] 0.1 Buat branch `feat/catalog-redesign`
- [x] 0.2 Verify clean tree: `git status` (hanya docs baru)
- [x] 0.3 Baseline check: `npm run build` lulus sebelum ubah kode

---

## Phase 1 — Shared hooks + components (foundation)

Tujuan: hook + komponen reusable dipakai cross-panel. Tidak ubah panel dulu.

- [x] 1.1 `hooks/use-long-press.ts` — pointer events, 500ms threshold, cancel on move > 10px, return `{onPointerDown, onPointerUp, onPointerLeave, onPointerCancel, onPointerMove}`
- [x] 1.2 `components/catalog/CategoryChips.tsx` — props `{categories: Category[], value: string|'', onChange: (id)=>void}`, horizontal wrap, "Semua" chip + tiap kategori, active = `bg-primary text-primary-foreground`
- [x] 1.3 `components/catalog/ProductTile.tsx` — props `{product, onEdit, onRestock}`, bind `useLongPress(() => onRestock(product, defaultUnit))` + `onContextMenu` desktop fallback, tampilan: nama truncate, satuan + `formatCurrency(harga_jual)`, stock chip per unit (`stockChipClass`)
- [x] 1.4 `components/catalog/ProductTileSkeleton.tsx` — shape match `ProductTile` (rounded-lg, animate-pulse)
- [x] 1.5 `components/catalog/ProductTileGrid.tsx` — props `{products, loading, onEdit, onRestock}`, `grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2`, skeleton saat loading, `<Empty>` saat 0 hasil
- [x] 1.6 `components/catalog/StockSummaryCards.tsx` — props `{totalCount, lowCount, outCount}`, 3 card `size="sm"` reuse reports stat pattern, icon `Package/AlertTriangle/PackageX`
- [x] 1.7 `components/catalog/CategoryTile.tsx` — props `{category, productCount, onEdit}`, tile `grid-cols-2 sm:grid-cols-3`, nama + count badge
- [x] 1.8 `npm run build` lulus, tsc lulus

---

## Phase 2 — ProductsPanel rework (A + B + C + D)

Tujuan: POS-style grid + list toggle + cache + pull-to-refresh + sticky footer.

- [x] 2.1 Tambah state `view: 'grid'|'list'` persist `localStorage` key `catalog:view` (default `grid`)
- [x] 2.2 Toolbar: search (existing) + CategoryChips filter (new state `categoryFilter`) + toggle button (icon `LayoutGrid`/`List`) + Tambah Produk button
- [x] 2.3 Migrasi fetch products → `useCachedList<Product>('products', api, {}, {perPage: 100})` (deviation: empty params — backend ignores `search`; filter client-side). Filter `stockFilter` (Semua/Rendah/Habis) + `categoryFilter` applied client-side via `productMatchesFilter` + `category_id` match
- [x] 2.4 Grid mode: render `<ProductTileGrid>` dengan filtered products. Tap tile → `openEdit`, long-press → `openRestock(product, defaultUnit)`
- [x] 2.5 List mode: render existing `GroupedList`+`IosListRow` layout (keep current)
- [x] 2.6 Pull-to-refresh: lift pattern `Orders.tsx` (touchstart/move/end, delta 80px, `scrollTop===0` check, panggil `refetch`, "Memuat ulang..." text)
- [x] 2.7 Sticky drawer footer: scroll container `flex-1 min-h-0`, `<DrawerFooter>` (`shrink-0 mt-auto`) tetap pin bawah
- [x] 2.8 CategoryChips sync dengan `categories` state. Filter produk by `category_id === categoryFilter || categoryFilter === ''`
- [x] 2.9 `onInventoryChange` callback tetap dipanggil setelah save/delete/restock success
- [x] 2.10 `npm run build` lulus

---

## Phase 3 — StockPanel rework (E + C)

Tujuan: summary cards + pull-to-refresh, history tetap.

- [x] 3.1 Tambah fetch `api.list<StockAlertsResponse>('inventory/alerts')` di `StockPanel` + `api.list('products')` untuk total count
- [x] 3.2 Hitung: `totalCount` = panjang produk list ; `lowCount` = alerts items filter `!is_out_of_stock` ; `outCount` = alerts items filter `is_out_of_stock`
- [x] 3.3 Render `<StockSummaryCards>` di atas quick-action buttons
- [x] 3.4 Tetap: Restok cepat + Penerimaan supplier buttons + `<StockMovementsPanel>` card
- [x] 3.5 Pull-to-refresh: refresh summary + trigger `StockMovementsPanel` `refreshKey++`
- [x] 3.6 `npm run build` lulus

---

## Phase 4 — CategoriesPanel rework (F + B + C)

Tujuan: tile grid + counts + cache.

- [x] 4.1 Migrasi fetch categories → `useCachedList<Category>('categories', api, {}, {perPage: 100})`
- [x] 4.2 Hitung product count per kategori: pakai `category.products_count` jika ada; fallback count dari `products` (useMemo map)
- [x] 4.3 Render tile grid `<CategoryTile>` instead of list rows. `grid-cols-2 sm:grid-cols-3 gap-2`
- [x] 4.4 Tap tile → `openEdit(cat)`. Long-press → delete confirm AlertDialog
- [x] 4.5 Pull-to-refresh: panggil `refetch`
- [x] 4.6 Tetap: drawer form (nama/deskripsi/aktif) + AlertDialog delete
- [x] 4.7 `npm run build` lulus

---

## Phase 5 — Polish + verify

- [x] 5.1 Haptic on every primary tap (tile tap, long-press restock, save, delete) — verify via `haptic()` call. Coverage: ProductTile/CategoryTile (tap+long-press+context-menu), Products/CategoriesPanel (openCreate+save+delete+view toggle), StockPanel (Restok cepat + Penerimaan supplier)
- [x] 5.2 Skeleton shape match final layout — `ProductTileSkeleton` matches ProductTile; CategoriesPanel skeleton refined from generic `h-16` block to tile shape (name line + pill badge)
- [x] 5.3 Empty state konsisten (`<Empty>` + title + description) di semua 3 panel — Produk (ProductTileGrid), Kategori (CategoriesPanel), Stok (StockMovementsPanel)
- [x] 5.4 Responsive verify (live browser): 375px → 2 col, 768px → 3 col, 1280px → 4 col. Toggle + search reachable di mobile
- [x] 5.5 `npx tsc --noEmit` lulus untuk scope catalog (1 error catalog `Select` `string|null` diperbaiki `v ?? ''`). Catatan: 21 error pre-existing lain (Orders/api/Users/dll) di luar scope redesign, tidak diutak-atik
- [x] 5.6 `npm run build` final lulus
- [ ] 5.7 `npx playwright test` E2E — **BLOCKED (pre-existing, bukan dampak redesign)**: login E2E gagal di semua halaman (orders/catalog/users/settings) karena env production: `TurnSTILE_ENABLED=true` + site-key terikat domain `piyuralabs.com` (test tidak kirim token), guard `isProduction && !Turnstile` → 503, `SESSION_SECURE_COOKIE=true` + `SANCTUM_STATEFUL_DOMAINS=sos.piyuralabs.com` blokir cookie sesi di `localhost`, dan user seed `owner@example.com` tidak ada (hanya `admin@agency.local`). Dibuktikan non-regresi: `git stash` (kode baseline tanpa redesign) gagal identik di login. Layout-width tidak break (grid `grid-cols-2` base, tidak override container) — diverifikasi via render live.
- [x] 5.8 Manual browser (live, via env lokal temporer): tile grid render dgn data nyata, tile-tap buka drawer "Ubah Produk", view toggle grid↔list + **persist across reload** (`catalog:view`), cache instant-render, semua 3 tab (Produk/Kategori/Stok) render — Stok summary cards "11 Total SKU / 0 Stok Rendah / 14 Habis"
- [x] 5.9 Console: no error — 0 error / 0 unhandled rejection selama alur tab-switch + toggle + tile-tap + drawer (capture via `window.onerror` + `console.error` wrap)
- [x] 5.10 Commit atomic per phase (Phase 1, 2, 3, 4, 5)

---

## Risk Checks

- [x] Long-press tidak konflik scroll: `onPointerMove` delta > 10px cancel timer (hook `use-long-press.ts`)
- [x] Cache stale saat restock: `onInventoryChange` → `refetch` dari parent
- [x] `products_count` null: fallback count dari loaded products (`countFor` di CategoriesPanel)
- [x] Layout-width E2E tidak break: tile grid pakai `grid-cols-2` base, tidak override container width (live-verified: grid contained dalam app-frame)

