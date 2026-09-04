# Feature Hand-off: 08 — Mobile UI (React + shadcn/ui)

> Supersedes PRD Alpine.js spec. iOS HIG layout preserved.

## Stack

| Layer | Path |
|-------|------|
| Entry | `resources/js/main.tsx` |
| Router | `resources/js/App.tsx` |
| Shell | `components/AppShell.tsx` |
| Tab bar | `components/AppNav.tsx` (`MobileTabBar`, `DesktopSidebar`) |
| Pages | `resources/js/pages/` |

## Stories shipped

| Story | Implementation |
|-------|----------------|
| 8.1 Tab navigation | `AppNav` (`MobileTabBar`/`DesktopSidebar`), glass blur, role-filtered tabs |
| 8.2 Bottom sheets | `components/ui/drawer.tsx`, order forms |
| 8.3 Swipe actions | `IosSwipeRow` — edit, delete, bayar |
| 8.4 Pull-to-refresh | `hooks/use-pull-to-refresh.ts` (Orders + Catalog panels) |
| 8.5 Toasts + haptic | sonner + `haptic()` in `lib/format.ts` |

## Pages

| Route | Component | Roles |
|-------|-----------|-------|
| `/orders` | `Orders.tsx` | owner, staff |
| `/products` | `Products.tsx` | owner |
| `/categories` | `Categories.tsx` | owner |
| `/reports` | `Reports.tsx` | owner |
| `/users` | `Users.tsx` | owner |

## Related

Order/payment UX detail: [04-orders-payments.md](04-orders-payments.md)
