# PWA Optimization — Task Breakdown

> Checklist eksekusi untuk `docs/PWA_OPTIMIZATION_PLAN.md` (Phase 1 only).
> Tiap task: verifikasi sebelum lanjut. Tandai `[x]` saat selesai.

---

## Phase 1.0 — Icon Set (H)

Tujuan: full icon matrix Android + iOS. Generate via `php artisan pwa:icons`.

- [x] 1.0.1 Extend `GeneratePwaIcons` command: add Android sizes 16/32/48/96/144/192/512 to `public/icons/`
- [x] 1.0.2 Add monochrome icon 512 (`icon-512-monochrome.png`) — white-on-transparent silhouette for Android themed display
- [x] 1.0.3 Add iOS touch icons 152/167/180/1024 (1024 for App Store / future Capacitor use)
- [x] 1.0.4 Reduce maskable letter scale 0.40 → 0.33 (survive aggressive masks)
- [x] 1.0.5 Re-run `php artisan pwa:icons`, verify all files in `public/icons/`
- [x] 1.0.6 Add test assertions in `PwaManifestTest` for new sizes

---

## Phase 1.1 — iOS Splash Screens (H)

Tujuan: no white flash on cold launch. Per-device launch images.

- [x] 1.1.1 Define device list: iPhone SE (750x1334), iPhone 8 (750x1334), iPhone 8 Plus (1242x2208), iPhone X/XS/11 Pro (1125x2436), iPhone XR/11 (828x1792), iPhone 12/13/14 (1170x2532), iPhone 12/13/14 Pro Max (1284x2778), iPhone 14 Pro (1179x2556), iPad mini (768x1024), iPad (810x1080), iPad Pro 11 (834x1194), iPad Pro 12.9 (1024x1366)
- [x] 1.1.2 Extend `GeneratePwaIcons` (or new `pwa:splash` command) — render solid bg + centered icon PNG for each device size into `public/icons/splash/`
- [x] 1.1.3 Generate media-query `<link rel="apple-touch-startup-image">` block via Blade partial `resources/views/partials/_apple-splash.blade.php`
- [x] 1.1.4 Include partial in `app.blade.php` `<head>`
- [ ] 1.1.5 Manual verify: Safari iOS → Add to Home Screen → launch → no white flash *(deferred — no iOS device in CI environment)*

---

## Phase 1.2 — Manifest Enrichment (M)

Tujuan: leverage full PWA spec.

- [x] 1.2.1 Add `"id": "/?source=pwa"`
- [x] 1.2.2 Add `"display_override": ["window-controls-overlay", "standalone"]`
- [x] 1.2.3 Add `"launch_handler": { "client_mode": "navigate-existing" }` — single instance
- [x] 1.2.4 Add `"prefer_related_applications": false`
- [x] 1.2.5 Add `"shortcuts"`: New Order (`/orders?action=new`), Catalog (`/catalog`), Reports (`/reports`)
- [x] 1.2.6 Add `"screenshots"`: reuse `public/landing/pesanan-mobile.png`, `katalog-mobile.png`, `laporan-mobile.png`, `pengaturan-mobile.png` — 2 mobile (form_factor: narrow) + 2 desktop (form_factor: wide, reuse non-mobile variants)
- [x] 1.2.7 Update all icon entries to match Phase 1.0 output (new sizes + monochrome)
- [x] 1.2.8 Update `PwaManifestTest`: assert `id`, `display_override`, `launch_handler.client_mode`, shortcuts count >= 3, screenshots count >= 4

---

## Phase 1.3 — Service Worker Upgrade (H)

Tujuan: update flow + read API cache + fonts offline.

- [x] 1.3.1 Bump `VERSION = 'v1'` → `'v2'`, verify old caches evicted on activate
- [x] 1.3.2 Precache list: add `/manifest.json`, `/favicon.ico`, all icons from Phase 1.0
- [x] 1.3.3 Add stale-while-revalidate for read endpoints: `/api/products`, `/api/categories`, `/api/orders` (GET only, same-origin)
- [x] 1.3.4 Cache Google Fonts: intercept `fonts.googleapis.com/css2` (CSS) + `fonts.gstatic.com` (woff2) into separate `fonts-v2` cache
- [x] 1.3.5 Implement update flow: on `controllerchange` or new SW waiting, `postMessage({type:'UPDATE_AVAILABLE'})` to all clients
- [x] 1.3.6 Front-end: add SW update listener in `main.tsx` (or new `lib/sw-update.ts`) → fire toast "Update available" with reload action via `sonner`
- [x] 1.3.7 Verify network-first navigation still falls back to cached shell
- [ ] 1.3.8 Manual test: bump VERSION → reload → see update toast → accept → new bundle active *(deferred — needs real deploy)*


---

## Phase 1.4 — Install Prompt UX (M)

Tujuan: make install discoverable, guide iOS users.

- [x] 1.4.1 Create `resources/js/lib/useInstallPrompt.ts` hook: capture `beforeinstallprompt`, expose `{ canInstall, promptInstall }`
- [x] 1.4.2 Detect iOS standalone: `window.navigator.standalone === false && /iphone|ipad|ipod/i.test(userAgent)`
- [x] 1.4.3 Add "Install app" button to `SettingsProfile` (or `SettingsLayout`): Android → call `promptInstall()`, iOS → open instructions sheet
- [x] 1.4.4 Create iOS instructions sheet component (Share icon → Add to Home Screen) using existing shadcn `sheet` or `dialog`
- [x] 1.4.5 First-session toast on app entry (after login): show install prompt once, dismiss state in `localStorage` key `pwa-install-dismissed`
- [ ] 1.4.6 Manual verify: Android Chrome → button triggers prompt; iOS Safari → button opens instructions *(deferred — no mobile devices in CI)*

---

## Phase 1.5 — Safe Area Audit (M)

Tujuan: no content clipped under notch/home indicator.

- [x] 1.5.1 Grep `fixed`, `sticky`, `absolute inset-0` in `resources/js/` — list all hits
- [x] 1.5.2 For each hit: verify `env(safe-area-inset-top/right/bottom/left)` applied where needed
- [x] 1.5.3 Audit `AppShell` scroll container — top padding under status bar — added `.safe-top` CSS rule (header already used class)
- [x] 1.5.4 Audit bottom nav (`AppNav`, `IosTabBar`) — already have bottom inset, verified ✓
- [x] 1.5.5 Audit FABs, sticky action bars, drawer footers — added bottom + top safe-area to `SheetContent` bottom/top variants, added `.safe-bottom` utility
- [x] 1.5.6 Verify `viewport-fit=cover` already in viewport meta (yes, confirm no regression) — line 5 of app.blade.php ✓
- [ ] 1.5.7 Manual verify on iPhone notch device (or simulator): no clipping top/bottom *(deferred — no device in CI)*

---

## Phase 1.6 — Verification & Ship (H)

Tujuan: prove it works, ship.

- [ ] 1.6.1 Run Lighthouse PWA audit on production build — target 100 PWA category *(deferred — needs deployed HTTPS host; all manifest/SW/icon criteria are test-covered)*
- [x] 1.6.2 `php artisan test --filter=PwaManifestTest` — all green (10/10, 77 assertions)
- [x] 1.6.3 `php artisan test` full suite — no new regressions vs baseline (207 passed; only pre-existing `LoginBrandingTest` + `TenantExportTest` fail, both confirmed unrelated to PWA)
- [x] 1.6.4 `npm run build` clean, no TS errors
- [ ] 1.6.5 Manual install: Android Chrome → Add to Home Screen → icon + splash + standalone launch *(deferred — no Android device in CI)*
- [ ] 1.6.6 Manual install: iOS Safari → Share → Add to Home Screen → icon + splash + standalone launch *(deferred — no iOS device in CI)*
- [ ] 1.6.7 Verify offline: airplane mode → app shell loads → offline.html fallback works *(deferred — needs installed client)*
- [x] 1.6.8 Commit `feat: optimize PWA for Android + iOS`
- [x] 1.6.9 Update `README.md` PWA section if any user-facing change — added PWA deploy note in `docs/DEPLOY.md`

---

## Phase 2 — Capacitor (LATER)

Not in scope now. See `PWA_OPTIMIZATION_PLAN.md` Phase 2 section. Start only when:
- Phase 1 shipped
- Decision made on App Store vs Play Store priority
- Mac availability confirmed for iOS build

Initial tasks when started:
- [ ] 2.0.1 `npm install @capacitor/core @capacitor/cli @capacitor/ios @capacitor/android`
- [ ] 2.0.2 `npx cap init` with `webDir: "public/build"`
- [ ] 2.0.3 Auth: add `POST /api/tokens` Sanctum token route
- [ ] 2.0.4 Tenant: pass slug via `X-Tenant` header or token claims
- [ ] 2.0.5 Replace cookie auth in `lib/api.ts` with bearer token
- [ ] 2.0.6 Add native plugins (haptics, share, push, app, status-bar, splash-screen)
- [ ] 2.0.7 Deep link `myapp://` scheme
- [ ] 2.0.8 Android build: `./gradlew assembleRelease`
- [ ] 2.0.9 iOS build: Xcode on Mac
- [ ] 2.0.10 Submit to stores
