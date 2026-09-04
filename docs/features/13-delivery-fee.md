# Feature Hand-off: 13 — Delivery Fee (Ongkir)

## Goal

Charge delivery (ongkir) on orders. Order form gets a `Diantar` / `Diambil` toggle; `Diantar` orders carry a fee computed from owner-configured tariffs (per-km with optional minimum, or flat), with optional OpenStreetMap auto-estimate of distance (Nominatim geocoding + OSRM routing — no API key). The destination can be given as a typed address OR picked as a pin on a map (MapLibre; OpenStreetMap raster basemap), and the store origin can be pinned in Settings → Profil. Fee enters `grand_total = subtotal − discount + PPN + ongkir` (PPN does not apply to the fee).

## Fee resolution (canonical, server-side)

Implemented in `OrderService::resolveDeliveryAttributes`, mirrored client-side by `resolveDeliveryFee` (`resources/js/lib/delivery.ts`):

- `diambil` → fee 0, km null.
- `diantar` → explicit `delivery_fee` override (validated ≥ 0, integer-cents half-up), else `per_km`: `max(fee_per_km × km, min_fee)` when km present (else 0), else `fixed`: `fixed_fee`.

Fee is computed **at write time from current settings** (same behavior as PPN — no create-time snapshot): drafts re-resolve on edit with whatever rates are current; finalized orders keep their stored fee.

## Settings (owner-only)

- `TenantSettings` keys `delivery.fee_mode|fee_per_km|min_fee|fixed_fee` (defaults per_km / 5000 / 0 / 10000). The encrypted `delivery.google_maps_api_key` storage (mayar-style accessors; never leaves the backend) is **DORMANT but functional** — the active OSM engine needs no key.
- `GET/PATCH /api/settings/delivery` — returns `{fee_mode, fee_per_km, min_fee, fixed_fee, google_maps_configured}` (no key). PATCH: `fee_mode in:per_km,fixed`, nullable numeric 0..100000000 ×3, `google_maps_api_key` nullable string (non-empty sets, empty clears, absent keeps) — the key validation and `google_maps_configured` plumbing stay live for the dormant path.
- `POST /api/settings/delivery/test` — OSM connectivity check. With a store-origin pin set it routes pin→pin (zero geocoding); otherwise one cached-origin Nominatim geocode + one OSRM route from the tenant address to itself → `{connected, message, latency_ms?}`; success message "Koneksi OpenStreetMap berhasil.", failures return Indonesian reasons.

### Store origin pin (Profil)

- `TenantSettings` JSON keys `delivery.origin_lat` / `delivery.origin_lon` — no migration; the pair is always written or cleared together.
- `PATCH /api/settings/tenant` accepts optional nullable `latitude` (`between:-90,90`) / `longitude` (`between:-180,180`): absent keys keep the pin, explicit nulls clear it (google-key semantics). `GET` (and the PATCH response) echo `latitude`/`longitude` (null when unset).
- `GET /api/delivery/settings` also returns `origin: {lat, lon} | null` — the pin itself, never the geocoded address (no external calls on this endpoint; staff-allowed so the order form can center the map).
- UI: SettingsProfile "Profil Bisnis" card, below the Alamat field — row `Lokasi Toko di Peta` (status line + `Atur di Peta` / `Hapus Pin`, owner-only). Confirming a pin persists it immediately (PATCH with `name` — the endpoint requires it); the reverse-geocoded text is shown as an info preview (`Pin: …`) and never overwrites the store address field.

Re-enabling the Google fallback: restore the `fetchMetersGoogle()` call in `DeliveryEstimateService::distanceKm()` and the key FieldGroup in `SettingsOps.tsx` (kept commented out). The key storage path never went away.

## Estimate

- `app/Services/DeliveryEstimateService.php` — keyless OpenStreetMap engine, backend-only:
  - **Nominatim geocoding** (`nominatim.openstreetmap.org/search` with `q={address}`, `format=json`, `limit=1`, `countrycodes=id`, timeout 10) resolves address → coords. The usage policy requires a descriptive `User-Agent` (`OrderTracker/1.0 (ongkir estimate)`, plus `Accept-Language: id`) and ~1 req/s.
  - **OSRM demo server** (`router.project-osrm.org/route/v1/driving/{lonO},{latO};{lonD},{latD}?overview=false`, timeout 10) resolves coords → driving meters; km rounded half-up to 2 dp (unchanged `metersToKm()`).
  - **Origin resolution is pin-first**: the stored store-origin pin when set (zero Nominatim dependency), else the geocoded tenant address. **Origin geocode cached 30 days** — `Cache::remember("delivery.origin_geo.{tenant_id}." . md5($origin))`, so a typical estimate is 1 destination geocode + 1 route call. Origin fallback = tenant address from Settings → Profil (empty → 422 reason).
  - **Routing is OSRM-only — deliberately NO straight-line/haversine fallback** (user decision). When OSRM fails, the 422 toast carries the Indonesian reason and the copy points users to the `Jarak manual` mode as the escape hatch.
  - **Reverse geocoding** (`nominatim.openstreetmap.org/reverse`, `format=jsonv2`, `zoom=18`, `accept-language=id`, same UA, timeout 10) turns pin coords into `display_name`; distinct Indonesian `DeliveryEstimateException` reasons for empty/failed/429 (429 reuses the busy copy).
  - Public servers have no SLA and rate limits (Nominatim ~1 req/s policy; 429 → "Layanan OpenStreetMap sedang sibuk. Coba lagi atau isi jarak manual."). When estimation fails, the order form falls back to manual km entry. Typed `DeliveryEstimateException` carries the Indonesian reasons (empty origin, destination miss, HTTP failure, 429, unexpected shape).
  - The Google Distance Matrix engine is kept as a dormant private method (`fetchMetersGoogle()` + `STATUS_REASONS`) — see re-enable steps in Settings above.
- `POST /api/delivery/estimate` (staff-allowed) takes **exactly one of** `{address}` (geocoded) or `{lat, lon}` (pin — validation matrix enforces one-of; neither or a lone `lat` → 422) → `{distance_km, fee_amount, fee_mode, fee_per_km, min_fee, fixed_fee}` or 422 `{reason}`. A coordinate estimate makes ZERO Nominatim calls.
- `POST /api/delivery/reverse` (staff-allowed) `{lat, lon}` → `{address}` or 422 `{reason}`.
- `GET /api/delivery/settings` (staff-allowed) — fee config + `origin` pin field, for client-side fee preview and map centering.

## Pin map picker (MapLibre)

`resources/js/components/LocationPickerPanel.tsx` — shared crosshair-center picker (Drawer mobile / Sheet ≥1024px via `ResponsiveFormPanel`), reused by the order form (customer destination) and SettingsProfile (store origin). MapLibre GL is lazily `import()`-ed on first open so the main bundle stays lean. User pans the map until the fixed center crosshair sits on the target, then `Pakai Lokasi Ini` confirms `map.getCenter()`; `Lokasi Saya` jumps to the device position (`navigator.geolocation`). Initial view: existing pin → store origin → Jakarta.

- **Customer flow** (CartReviewSheet): compact MapPin button beside the `Alamat` label. Confirm → `POST /delivery/reverse` → the returned text fills `customer_address` (still editable) and the exact coords are kept session-local; `Hitung Ongkir` then sends `{lat, lon}`. **Stale-guard**: typing in the Alamat textarea clears the pin — once hand-edited, the text is the source of truth. The `Otomatis` button is enabled by either a pin or non-empty address text. Destination coords are never persisted on the order; the address text is the durable artifact.
- **Basemap**: standard OpenStreetMap raster tiles (`tile.openstreetmap.org/{z}/{x}/{y}.png`, MapLibre raster style v8; attribution © OpenStreetMap contributors via the default attribution control). Previously BIG "Rupabumi Indonesia" vector tiles with an automatic OSM fallback; BIG was removed (repeatedly unavailable in practice).
- **Geolocation**: opening the picker with no existing pin auto-requests device location (`navigator.permissions.query` gates it — a previously denied site is skipped since browsers never re-prompt) and recenters on the fix unless the user already panned. Requires the `Permissions-Policy: geolocation=self` header (SecurityHeaders middleware) — `geolocation=()` disables the API entirely (instant PERMISSION_DENIED, no prompt, manual grants ignored).
- **PWA/offline**: map tiles need internet. The crosshair + `Pakai Lokasi Ini` still work on a blank map because the confirmed coords come from the map viewport, not from tiles.

## Orders

- Migration `add_delivery_fields_to_orders_table`: `delivery_method` string(20) default `diambil`, `delivery_fee` decimal(15,2) default 0, `delivery_distance_km` decimal(6,2) nullable.
- Validation (`OrderController` store/update): `delivery_method in:diantar,diambil`, `delivery_distance_km nullable|numeric|between:0,999`, `delivery_fee nullable|numeric|min:0`.
- Delivery money fields (method/km/fee) resolve on create and on **draft-only** updates — same guard as items; non-draft payloads carrying them are ignored. `customer_address` stays editable at any status (pre-existing customer field, also used by efactur).
- Payments untouched — `remaining_amount`/`change_amount` derive from `grand_total` automatically.

## UI

| Layer | File |
|-------|------|
| Order form (Review Pesanan → Tambah Pelanggan) | `resources/js/components/orders/CartReviewSheet.tsx` |
| Shared pin map picker | `resources/js/components/LocationPickerPanel.tsx` |
| Store origin pin row | `resources/js/pages/settings/SettingsProfile.tsx` (Profil card) |
| Order page wiring + detail rows | `resources/js/pages/Orders.tsx` |
| Client fee preview + labels | `resources/js/lib/delivery.ts` |
| Settings card | `resources/js/pages/settings/SettingsOps.tsx` ("Pengiriman" card after Pajak) |
| Receipt | `resources/js/lib/receipt.ts` |

- `Pengiriman` RadioGroup (Diantar/Diambil, default diambil). Diantar reveals the `Alamat` textarea (with the pin-picker MapPin button beside its label) plus a collapsed **Atur Ongkir** trigger (Truck icon, badge dot when set; once a fee exists it becomes a summary chip `Ongkir Rp X · Y km`). Tapping it opens a compact Ongkir panel (X close button) with a mode Select:
  - `Otomatis` — `Hitung Ongkir` button only (spinner "Menghitung…", disabled without an address AND without a pin, or while estimating; sends `{lat, lon}` when a pin is active, else `{address}`). Success fills km + clears the override + inline result `X km · Rp Y`; a 422 `{reason}` is toasted and the user can switch to a manual mode.
  - `Jarak manual` — compact km input + live fee preview ("Tarif: Rp …").
  - `Tarif manual` — compact fee override input (placeholder "Rp").
  - Switching modes clears the other mode's field (km ↔ override) so hidden stale values never reach the payload. Mode preselect: stored km > 0 → Jarak manual; else stored fee > 0 → Tarif manual; else Otomatis. The panel starts collapsed; the `Ongkir` row and the Total row ("Total (termasuk ongkir)") stay outside the panel.
- Delivery section disabled when editing a non-draft order (static summary + lock note "Metode dan ongkir hanya bisa diubah pada pesanan draf."; address stays editable).
- Order detail: `Pengiriman:` line (`Diantar (x km)` / `Diantar` / `Diambil`) + `Ongkir` row when fee > 0.
- Settings card: mode Select (Per Kilometer / Tarif Tetap) with rate + min fee or fixed fee fields; `Tes Koneksi` tests OSM connectivity (no key field — the Google Maps key FieldGroup is commented out, dormant). Helper text: jarak otomatis dihitung memakai OpenStreetMap — tanpa API key; rate helper cites market norm Rp 3.000–10.000/km.
- Receipt (`receipt.ts`): `Alamat` meta line after Telp when Diantar + address present; `Ongkir` row between PPN and Total when fee > 0.

## Out of scope

Reports delivery-fee revenue breakdown, efactur CSV fields, Places Autocomplete, self-hosting Nominatim/OSRM, applying PPN to ongkir, Mayar invoice amount adjustments after fee edits, persisting destination pin coords on orders, straight-line/haversine routing fallback (explicitly rejected — OSRM-only), tile proxying/self-hosted tiles.

## Tests

`tests/Feature/DeliveryEstimateTest.php` (Nominatim+OSRM `Http::fake`: estimate success with km + fee, origin-cache reuse on second estimate, destination-geocode miss → 422 reason, Nominatim 429/5xx → 422, OSRM 5xx/NoRoute → 422, unexpected shape, `testConnection` ok/fail + staff guard, and offline fee-math cases min-clamp/fixed mode; plus coordinate-estimate cases — estimate-by-coords makes zero Nominatim calls, origin-pin set makes even address estimates geocode-free, reverse success/miss/429, and the estimate one-of validation matrix) and the store-origin pin round-trip in `tests/Feature/SettingsTest.php` (PATCH store/echo/absent-keeps/null-clears) and `tests/Feature/OrderDeliveryFeeTest.php` (settings validation, totals math incl. override wins/diambil zeroing, PPN-excludes-fee, draft re-resolve, non-draft lock, legacy orders). Frontend validated by `npx tsc --noEmit`; manual QA on the `tester` tenant (owner@tester.test / staff@tester.test).
