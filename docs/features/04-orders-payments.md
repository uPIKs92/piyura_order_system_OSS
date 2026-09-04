# Feature 04 — Orders & Payments (React UI)

> Handoff doc — UX Polish Sprint, 18 Juli 2026

## Overview

Full orders and payments UI built in React + shadcn/ui. Replaces Alpine Blade pages. Backend `/api/*` contract unchanged.

## Frontend Stack

| Layer | Path | Notes |
|-------|------|-------|
| Entry | `resources/js/main.tsx` | Vite + React 19 |
| Router | `resources/js/App.tsx` | React Router, catch-all web route |
| API client | `resources/js/lib/api.ts` | Sanctum bearer token from localStorage |
| Auth context | `resources/js/lib/ApiProvider.tsx` | `useApi()` hook |
| Shell | `resources/js/components/AppShell.tsx` | Header + Outlet + AppNav + sonner |
| Orders page | `resources/js/pages/Orders.tsx` | Primary polish target |

## iOS Components

| Component | Path | Purpose |
|-----------|------|---------|
| `AppNav` | `components/AppNav.tsx` | `MobileTabBar` (bottom) + `DesktopSidebar` (lg), role-filtered |
| `IosListRow` | `components/ios/IosListRow.tsx` | 44px min touch target rows |
| `IosSwipeRow` | `components/ios/IosSwipeRow.tsx` | Swipe left Edit/Hapus, right Bayar |
| `IosPageHeader` | `components/ios/IosPageHeader.tsx` | Large title + action |
| `StatusBadge` | `components/ios/StatusBadge.tsx` | Order status chip |
| `PaymentBadge` | `components/ios/PaymentBadge.tsx` | unpaid/partial/paid/overpaid |

## Orders Page Features

- List with search debounce + status filter chips
- `StatusBadge` + `PaymentBadge` on every row
- Swipe actions via `react-swipeable`
- Bottom `Drawer` for create/edit/detail (invoice breakdown, payments, timelines)
- Payment `Drawer` for recording payments
- `AlertDialog` for delete (staff soft / owner force toggle)
- `sonner` toasts + `navigator.vibrate` on success/error
- Draft autosave: localStorage 5s + `POST /api/drafts`; restore via `AlertDialog`
- Pull-to-refresh on list container

## API Additions

`Order` model appends computed fields on every JSON response:

```php
protected $appends = ['payment_status', 'remaining_amount'];
```

| Field | Values |
|-------|--------|
| `payment_status` | `unpaid`, `partial`, `paid`, `overpaid` |
| `remaining_amount` | `max(0, grand_total - total_paid)` |

## shadcn Components Used

`button`, `badge`, `card`, `drawer`, `alert-dialog`, `sonner`, `input`, `label`, `field`, `select`, `separator`, `scroll-area`, `skeleton`, `empty`, `tabs`, `sheet`

## Routes

```php
// routes/web.php — SPA catch-all
Route::view('/{any?}', 'app')->where('any', '.*');
```

React Router handles `/login`, `/orders`, `/products`, `/categories`, `/users`, `/reports`.

## Tab Visibility (RBAC)

| Tab | Owner | Staff |
|-----|-------|-------|
| Orders | ✓ | ✓ |
| Products | ✓ | — |
| Reports | ✓ | — |
| Users | ✓ | — |

## Verification

```bash
npm run build          # must succeed
php artisan test       # 43 tests (42 passed, 1 skipped)
```

## Deferred

- Playwright E2E selector updates for React DOM
- PDF invoice (v1.1)
- Activity log viewer UI
