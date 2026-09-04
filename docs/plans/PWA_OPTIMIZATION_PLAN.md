# PWA Optimization & Native Migration Plan

> Two-phase plan for the order-tracker SPA.
>
> **Phase 1 (now):** Polish the existing PWA so it feels native on Android + iOS.
> **Phase 2 (later):** Wrap the SPA in Capacitor for App Store / Play Store distribution.
>
> See `PWA_OPTIMIZATION_TASKS.md` for the executable checklist.

---

## Context

The app is a Laravel 13 + React 19 SPA with Sanctum SPA-cookie auth, multi-tenant by subdomain. A baseline PWA shell already exists (`public/manifest.json`, `public/sw.js`, 4 generated icons, Blade `<head>` wiring). This document covers the gap between "baseline PWA" and "feels like a real app on the home screen", then the path to a true store-distributed binary.

## Decisions

- **Stay PWA-first for now.** No native rewrite. Reuse 100% of the React frontend.
- **Capacitor over React Native for Phase 2.** The UI is POS-style forms, lists, charts. WebView perf is sufficient. RN would require rebuilding shadcn/ui, recharts, react-router — 2-4 months of rework for no user-visible benefit. Capacitor reuses the existing SPA in a WebView shell.
- **iOS splash screens are in scope for Phase 1.** They are the single biggest perceived-quality lever for iPhone users (removes the white flash on cold launch). Time cost accepted.
- **No backend changes in Phase 1.** All work is `public/`, Blade, JS, and CSS.

---

## Phase 1 — PWA Polish

### Current gaps

1. **Icons** — only 4 PNGs. iOS wants ~7 touch-icon sizes plus per-device splash screens; Android benefits from a monochrome icon for themed display.
2. **iOS launch** — no splash screens, so cold start shows a white flash.
3. **Manifest** — missing `id`, `display_override`, `launch_handler`, `shortcuts`, `screenshots`, `prefer_related_applications`. Bare-minimum shell only.
4. **Service worker** — no update flow (users get stuck on stale bundles), no read API cache (catalog/orders lists reload slowly), Google Fonts not cached so theme breaks offline.
5. **Install UX** — `beforeinstallprompt` is not captured. Android users see Chrome's prompt only when Chrome decides; iOS users get zero guidance.
6. **Safe area** — partial coverage. `AppShell`, `AppNav`, `IosTabBar` handle `env(safe-area-inset-*)`, but content scroll areas and any fixed/sticky elements not audited.

### Work items

1. **Icon set** — extend `pwa:icons` command to emit the full matrix (16/32/48/96/144/192/512 + monochrome 512 for Android; 152/167/180/1024 + ~12 splash sizes for iOS). Reduce maskable letter to 33% to survive aggressive masks.
2. **iOS splash screens** — generate per-device launch PNGs and wire via media-query `<link rel="apple-touch-startup-image">`.
3. **Manifest enrichment** — add `id`, `display_override: ["window-controls-overlay","standalone"]`, `launch_handler: { client_mode: "navigate-existing" }`, `shortcuts` (New Order / Catalog / Reports), `screenshots` (reuse `public/landing/*.png`), `prefer_related_applications: false`.
4. **Service worker upgrade** — precache manifest + offline.html + favicon, stale-while-revalidate for read-heavy GET endpoints (`/api/products`, `/api/categories`, `/api/orders`), cache Google Fonts CSS + woff2, add update flow (new SW → `postMessage` → toast → reload), bump VERSION, evict old caches.
5. **Install prompt UX** — capture `beforeinstallprompt` (Android/Chrome), surface an "Install app" button in `SettingsProfile` + a first-session toast, iOS detection (`!navigator.standalone && /iPhone/.test(userAgent)`) → "Add to Home Screen" instructions sheet, dismiss state in localStorage.
6. **Safe area audit** — grep all fixed/sticky headers, bottom navs, FABs; apply `env(safe-area-inset-*)` where missing; verify `viewport-fit=cover` on notch devices.
7. **Verification** — Lighthouse PWA audit (target 100), update `PwaManifestTest` for new fields, new tests for shortcuts/screenshots, manual install on Android Chrome + iOS Safari.

### Non-goals

- Push notifications (deferred to Phase 2 — needs native plugin for iOS).
- Background sync.
- Any Laravel route or controller change.

### Effort

~2-3 days solo. Bulk is icon generation logic + service worker update flow.

---

## Phase 2 — Capacitor (Recommended)

### Why Capacitor, not React Native

| | Capacitor | React Native |
|--|--|--|
| Code reuse | ~95% (WebView wraps existing SPA) | ~30% (all JSX rebuilt as RN components) |
| shadcn/ui | works | rewrite with Tamagui / NativeBase |
| recharts | works | rewrite with victory-native |
| react-router | works | migrate to React Navigation |
| Effort | 1-3 weeks | 2-4 months |
| Perf | WebView (fine for POS / forms / lists) | Native (overkill here) |
| App Store / Play Store | yes | yes |
| iOS push | via native plugin | via native module |

The app is POS-style: forms, lists, charts, settings. WebView performance is sufficient. RN would cost 2-4 months to rebuild UI that already works.

### Migration outline

1. `npm install @capacitor/core @capacitor/cli` + `@capacitor/ios` + `@capacitor/android`.
2. `npx cap init` — `webDir: "public/build"`.
3. **Auth migration** — Sanctum cookie auth does not survive cleanly in a WebView (no reliable same-origin cookies). Add a bearer-token flow: Sanctum is already installed, add a `POST /api/tokens` route issuing a personal-access token; store token client-side; send `Authorization: Bearer` header from the API client.
4. **Tenant resolution** — `auth.host` middleware resolves tenant from the Host header. Native app has no subdomain. Bake the tenant slug into the Capacitor config or resolve it from the login flow; pass via token claims or an explicit `X-Tenant` header.
5. **Native plugins** — `@capacitor/haptics`, `@capacitor/share`, `@capacitor/push-notifications`, `@capacitor/app` (lifecycle), `@capacitor/status-bar`, `@capacitor/splash-screen`.
6. **Drop the service worker** — Capacitor bundles assets inside the APK/IPA, so `public/sw.js` is no longer needed for the native build. Keep it for the web deployment.
7. **Deep links** — register `myapp://` scheme for order/invoice share URLs.
8. **Build** — `./gradlew assembleRelease` (Android, any OS) + Xcode (iOS, requires a Mac).
9. **Assets** — reuse `public/icons/` for native splash + launcher icons via `@capacitor/assets`.

### Revisit only if

- Heavy native UI is needed (complex animations, maps, AR) — not the case here.
- Profiling shows WebView performance is insufficient — unlikely for this workload.

---

## Open questions (Phase 2, resolve when starting)

- Mac availability for Xcode builds? If not, iOS deferred or use cloud CI (Codemagic, Bitrise).
- Apple Developer account ($99/yr) and Google Play Console ($25 one-time) — who owns them?
- Tenant onboarding flow inside the native app — login screen first, or tenant-slug screen first?

