# Order Tracker — Product Requirements Document (PRD)

---

## 1. Product Overview

**Product Name:** Order Tracker  
**Version:** 1.0  
**Target Release:** v1.0 (2-3 week sprint)  
**Target Users:** UMKM owners & sales staff (5-10 orders/day)  
**Platform:** Mobile-first web app (iOS HIG design), Laravel + React 19 + shadcn/ui

### 1.1 Problem Statement

UMKM owners track orders via notebooks, sticky notes, or spreadsheets — unstructured, error-prone, no audit trail, hard to scale. Staff can't collaborate; owners have no real-time visibility.

### 1.2 Solution

Mobile-first order management: create/edit orders, track status pipeline, manage payments, sync reports to Google Sheets. Multi-user with row-level auth. iOS-native feel via React + shadcn/ui, glassmorphism bottom tabs, PWA installable.

---

## 2. Target Users & Roles

| Role | Access | Description |
|------|--------|-------------|
| **Owner** | Full | All orders, products, reports, user management, settings |
| **Staff** | Limited | Own orders only (create, edit, view), own account settings |

---

## 3. Functional Requirements

### FR1: Authentication & Authorization
| ID | Requirement | Priority |
|----|-------------|----------|
| FR1.1 | Laravel Sanctum SPA authentication (login, logout, session) | Must |
| FR1.2 | Role-based access: Owner vs Staff (Policy + Gate) | Must |
| FR1.3 | Row-level authorization: Staff sees only own orders | Must |
| FR1.4 | Owner bypasses row-level policy | Must |
| FR1.5 | Rate limiting: 5 failed login → 1 min lockout | Must |
| FR1.6 | Password reset flow (owner-initiated for staff) | Must |

### FR2: Product Management
| ID | Requirement | Priority |
|----|-------------|----------|
| FR2.1 | CRUD products (name, price, stock, photo optional v1.1) | Must |
| FR2.2 | Owner-only access | Must |
| FR2.3 | Stock decrement on order checkout | Must |
| FR2.4 | Soft delete products | Must |

### FR3: Order Management (Core)
| ID | Requirement | Priority |
|----|-------------|----------|
| FR3.1 | Create order: customer name, phone, items (product + qty), order_date (editable, default now), notes | Must |
| FR3.2 | Optimistic locking: version column, 409 on conflict | Must |
| FR3.3 | Price snapshot at checkout (price_snapshot, product_name_snapshot) | Must |
| FR3.4 | Status state machine: Draft → Pending → Processing → Shipped → Completed | Must |
| FR3.5 | Cancel allowed only from Draft/Pending | Must |
| FR3.6 | Soft delete orders (Staff soft, Owner force) | Must |
| FR3.7 | Draft auto-save to localStorage (5s interval) | Must |
| FR3.8 | Order date validation: not future, not before 2020 | Must |
| FR3.9 | Order date history table (order_date_history) | Must |

### FR4: Payments
| ID | Requirement | Priority |
|----|-------------|----------|
| FR4.1 | Payments table (order_id, amount, method, paid_at, notes) | Must |
| FR4.2 | Multiple payments per order (DP + cicilan, cash + transfer) | Must |
| FR4.3 | Computed `total_paid` on order | Must |
| FR4.4 | Overpayment allowed → credit note (v1.1) | Should |
| FR4.5 | Status badge: Partial / Paid / Overpaid | Must |

### FR5: PPN (Tax) Calculation
| ID | Requirement | Priority |
|----|-------------|----------|
| FR5.1 | Config toggle: PPN enabled/disabled | Must |
| FR5.2 | Config PPN percentage (default 11% for 2026-2027) | Must |
| FR5.3 | Invoice breakdown: subtotal, discount, PPN, grand_total | Must |
| FR5.4 | PPN calculated on (subtotal - discount) | Must |

### FR6: Audit Trail & Activity Log
| ID | Requirement | Priority |
|----|-------------|----------|
| FR6.1 | spatie/laravel-activitylog on all models | Must |
| FR6.2 | Log: created, updated, deleted, status_changed, payment_added | Must |
| FR6.3 | Store: causer_id, causer_type, subject_id, subject_type, properties(old/new), description | Must |
| FR6.4 | Owner views all logs; Staff views own | Must |

### FR7: n8n Sync to Google Sheets
| ID | Requirement | Priority |
|----|-------------|----------|
| FR7.1 | Outbox table (n8n_sync_outbox) with idempotency_key (UUID) | Must |
| FR7.2 | Event-driven push on order status change | Must |
| FR7.3 | Payload: Customer PII stripped (customer_name, phone, address excluded). Includes order_id, invoice_no, date, status, totals, item summaries, payment metadata | Must |
| FR7.4 | Cron dispatch (every 5 min): batch 50, retry 3x exponential backoff | Must |
| FR7.5 | Failed after 3 retries → Telegram notification to owner | Must |

### FR8: Database Backup
| ID | Requirement | Priority |
|----|-------------|----------|
| FR8.1 | spatie/laravel-backup daily to S3/Spaces | Must |
| FR8.2 | Backup record in `backups` table | Must |
| FR8.3 | Documented restore procedure in README | Must |

### FR9: Legacy Import
| ID | Requirement | Priority |
|----|-------------|----------|
| FR9.1 | Artisan command `orders:import` from CSV/XLSX | Must |
| FR9.2 | Configurable column mapping | Must |
| FR9.3 | Row-level validation, error report per row | Must |
| FR9.4 | Batch via queue for large files | Should |

### FR10: User Management (Owner only)
| ID | Requirement | Priority |
|----|-------------|----------|
| FR10.1 | Create staff accounts (email, password, name) | Must |
| FR10.2 | Deactivate staff (is_active=false, orders preserved) | Must |
| FR10.3 | Reassign orders: `orders:reassign --from=staff_id --to=owner_id` | Must |
| FR10.4 | Staff cannot delete own account | Must |

---

## 4. Non-Functional Requirements

| ID | Requirement | Target |
|----|-------------|--------|
| NFR1 | Mobile-first, iOS HIG compliance (bottom tabs, glassmorphism, swipe gestures) | Must |
| NFR2 | React 19 + react-router-dom + shadcn/ui SPA frontend. Fetch API via centralized ApiProvider | Must |
| NFR3 | Offline draft auto-save (localStorage) | Must |
| NFR4 | API response < 200ms (p95) for CRUD | Should |
| NFR5 | MySQL/MariaDB (production), SQLite (dev) | Must |
| NFR6 | PHP 8.3+, Laravel 13 | Must |
| NFR7 | Graceful degradation deploy via `php artisan down --retry=60` (returns 503 with Retry-After, not zero-downtime) | Should |
| NFR8 | Playwright E2E tests (serial, seeded owner) | Must |

---

## 5. UX / Design Requirements

| Area | Spec |
|------|------|
| **Navigation** | Bottom tab bar (Orders, Products, Reports, Settings) — glassmorphism, blur backdrop |
| **Forms** | iOS-style bottom sheets (slide up, drag down to dismiss) |
| **Lists** | Swipe actions (edit, delete) via @alpinejs/touch |
| **Pull-to-refresh** | Native feel on order list |
| **Color** | System light/dark, semantic colors (green=completed, orange=processing, red=cancelled) |
| **Typography** | SF Pro / system font stack |
| **Feedback** | Toast (SweetAlert2), haptic via `navigator.vibrate()` |

---

## 6. Technical Architecture Summary

- **Backend:** Laravel 13, API controllers in `app/Http/Controllers/Api/`
- **Auth:** Sanctum SPA, `web` guard + `sanctum` middleware on API routes
- **Frontend:** Blade SPA shell (`app.blade.php`), React 19 + react-router-dom + shadcn/ui
- **Database:** 22 tables (see DATABASE.md)
- **Queue:** Database driver (sync for dev)
- **Scheduler:** `n8n:dispatch`, `backup:run`, `orders:auto-cancel` (v1.1)
- **Testing:** Playwright (serial), Pest/PHPUnit (unit/feature)

---

## 7. Release Plan

### v1.0 (2-3 weeks / 14-18 person-days)
| Epic | Effort | Dependencies |
|------|--------|--------------|
| Auth & Authorization | 3 days | — |
| Products CRUD | 2 days | Auth |
| Orders Core (CRUD + Optimistic Lock + Snapshot + State Machine) | 5 days | Auth, Products |
| Payments + PPN | 3 days | Orders |
| Audit Trail + Soft Delete | 1 day | Orders |
| n8n Sync + PII Strip + Retry | 2 days | Orders |
| DB Backup | 1 day | — |
| Import Command | 1 day | Orders, Products |
| User Management | 1 day | Auth |
| Mobile UI (Alpine + Touch + Bottom Tabs) | 3 days | Auth, Orders API |

### v1.1 (1-2 weeks after v1.0 stable)
- Returns/Refund flow
- Discount per item
- Tax report export (`orders:tax-report`)
- Staff resign tool (`orders:reassign`)
- File storage (product photos)
- Auto-cancel expired pending orders (7 days)
- Mobile UX polish (touch gestures, haptics)

---

## 8. Success Metrics

| Metric | Target v1.0 |
|--------|-------------|
| Order creation → completion | < 60 seconds |
| API error rate | < 0.5% |
| Staff onboarding time | < 5 minutes |
| Data loss incidents | 0 |
| Backup restore tested | Yes (documented) |

---

## 9. Open Questions / Risks

| # | Question | Status |
|---|----------|--------|
| 1 | n8n webhook URL & credentials management | Pending infra |
| 2 | S3/Spaces credentials for backup | Pending infra |
| 3 | Telegram bot token for notifications | Pending infra |
| 4 | Production domain + SSL | Pending infra |
| 5 | SQLite vs MySQL parity in dev | Acceptable risk |

---

## 10. Appendix: Key Decisions Log

| Decision | Rationale | Date |
|----------|-----------|------|
| Multi-user from v1.0 (not single-user) | Foundation for scaling, avoids auth retrofit | 2026-07-16 |
| Optimistic locking over pessimistic | Low concurrency (5-10/day), simpler, no deadlocks | 2026-07-16 |
| Price snapshot at checkout | Accounting integrity — historical prices immutable | 2026-07-16 |
| PII-stripped Sheets sync | Staff Sheets access shouldn't leak other staff's customers | 2026-07-16 |
| Soft delete + audit trail | Accountability, recoverability, compliance | 2026-07-16 |
| Alpine.js over Livewire | Mobile UX control, offline draft, iOS feel, lighter | 2026-07-16 |
| State machine for status | Prevent invalid transitions (shipped → pending) | 2026-07-16 |