---
stepsCompleted:
  - requirements-inventory
  - epic-list
  - epic-1-stories
  - epic-2-stories
  - epic-3-stories
  - epic-4-stories
  - epic-5-stories
  - epic-6-stories
  - epic-7-stories
  - epic-8-stories
  - implementation-progress
  - epic-9-v1.1
  - schema-alignment
  - gap-closure-5-7
  - epic-10-sheets-import
inputDocuments:
  - docs/PRD.md
  - docs/ARCHITECTURE.md
  - docs/DATABASE.md
  - docs/archive/ROADMAP.md
  - docs/archive/PROJECT_CONTEXT.md
  - docs/features/handoff.md
  - docs/features/handoff-ops.md
  - docs/features/README.md
  - docs/features/01-sanctum-auth.md
  - docs/features/02-user-management.md
  - docs/features/03-produk-crud.md
  - docs/features/04-orders-payments.md
  - docs/features/05-audit-soft-delete.md
  - docs/features/06-google-sheets-sync.md
  - docs/features/07-backup-import.md
  - docs/features/08-mobile-ui.md
  - docs/features/09-v1-enhancements.md
  - docs/features/10-sheets-import.md
lastUpdated: 2026-07-18
---

# Order Tracker - Epic Breakdown

## Overview

This document provides the complete epic and story breakdown for Order Tracker v1.0 (+ v1.1 backlog), decomposing the requirements from the PRD, Architecture, and Database design into implementable stories with complete acceptance criteria for the Developer agent.

## Implementation Progress

**Branch:** `master` · **PHPUnit:** 80 passing · **Build:** `npm run build` green

| Epic | Status | Feature Doc | Tests / UI |
|------|--------|-------------|------------|
| 1 Auth & Authorization | **Done** | [01-sanctum-auth](features/01-sanctum-auth.md), [02-user-management](features/02-user-management.md) | `AuthTest`, `UserManagementTest`, `PolicyTest`, E2E auth/RBAC |
| 2 Products CRUD | **Done** | [03-produk-crud](features/03-produk-crud.md) | `CategoryTest`, `ProductTest` |
| 3 Orders Core | **Done** | [04-orders-payments](features/04-orders-payments.md) | `OrderTest` — locking, state machine, snapshots, date history |
| 4 Payments & PPN | **Done** | [04-orders-payments](features/04-orders-payments.md) | `PaymentTest`, `SettingsTest`, PPN on Reports |
| 5 Audit & Soft Delete | **Done** | [05-audit-soft-delete](features/05-audit-soft-delete.md) | `ActivityLogTest`, `ActivityLogRetentionTest`, field-diff UI |
| 6 Google Sheets Sync | **Done** | [06-google-sheets-sync](features/06-google-sheets-sync.md) | `SheetsSyncTest`, OAuth + direct Sheets API append |
| 7 Backup & Import | **Done** | [07-backup-import](features/07-backup-import.md) | `BackupTest`, `BackupApiTest`, `BackupRestoreTest`, `OrdersImportTest`, `ImportApiTest` |
| 8 Mobile UI | **Done** | [08-mobile-ui](features/08-mobile-ui.md) | React + shadcn/ui, `IosTabBar`, drawers, swipe, pull-to-refresh |
| 9 v1.1 Enhancements | **Done** (9.10 TUNDA) | [09-v1-enhancements](features/09-v1-enhancements.md) | Reports tabs, invoice PDF, returns, auto-cancel, discount, ops backup |
| 10 Sheets Import | **Done** | [10-sheets-import](features/10-sheets-import.md) | `SheetsLegacyImportTest`, `GoogleSheetsImportTest`, direct OAuth import + Excel upload |
| 11 UMKM Bon & Pengeluaran | **Done** | [archive/UMKM_BON_PENGELUARAN_PLAN.md](archive/UMKM_BON_PENGELUARAN_PLAN.md) | `ExpenseApiTest`, `ReportExpenseTest`, `ReceivablesTest`, tab Pengeluaran + Buku Bon UI |

**Current focus:** App code complete. Wire production env — see [features/handoff-ops.md](features/handoff-ops.md).

**Docs index:** [features/README.md](features/README.md) · [features/handoff.md](features/handoff.md) (developer) · [features/handoff-ops.md](features/handoff-ops.md) (ops)

### Schema Note (Implementation vs DATABASE.md)

Live migrations use **Indonesian column names** for catalog tables (`nama`, `harga_jual`, `stok`). Order tables (Epic 3+) follow **English names** per DATABASE.md (`customer_name`, `price_snapshot`, `grand_total`). Do not rename existing catalog columns — new order migrations use English.

## Requirements Inventory

### Functional Requirements

| ID | Requirement | Source |
|----|-------------|--------|
| FR1.1 | Laravel Sanctum SPA authentication (login, logout, session) | PRD |
| FR1.2 | Role-based access: Owner vs Staff (Policy + Gate) | PRD |
| FR1.3 | Row-level authorization: Staff sees only own orders | PRD |
| FR1.4 | Owner bypasses row-level policy | PRD |
| FR1.5 | Rate limiting: 5 failed login → 1 min lockout | PRD |
| FR1.6 | Password reset flow (owner-initiated for staff) | PRD |
| FR2.1 | CRUD products (name, price, stock, photo optional v1.1) | PRD |
| FR2.2 | Owner-only access | PRD |
| FR2.3 | Stock decrement on order checkout | PRD |
| FR2.4 | Soft delete products | PRD |
| FR3.1 | Create order: customer name, phone, items (product + qty), order_date (editable, default now), notes | PRD |
| FR3.2 | Optimistic locking: version column, 409 on conflict | PRD |
| FR3.3 | Price snapshot at checkout (price_snapshot, product_name_snapshot) | PRD |
| FR3.4 | Status state machine: draft → pending → diproses → dikirim → selesai | PRD |
| FR3.5 | Cancel allowed only from Draft/Pending | PRD |
| FR3.6 | Soft delete orders (Staff soft, Owner force) | PRD |
| FR3.7 | Draft auto-save to localStorage (5s interval) | PRD |
| FR3.8 | Order date validation: not future, not before 2020 | PRD |
| FR3.9 | Order date history table (order_date_histories) | PRD |
| FR4.1 | Payments table (order_id, amount, method, paid_at, notes) | PRD |
| FR4.2 | Multiple payments per order (DP + cicilan, cash + transfer) | PRD |
| FR4.3 | Computed total_paid on order | PRD |
| FR4.4 | Overpayment allowed → credit note (v1.1) | PRD |
| FR4.5 | Status badge: Partial / Paid / Overpaid | PRD |
| FR5.1 | Config toggle: PPN enabled/disabled | PRD |
| FR5.2 | Config PPN percentage (default 11% for 2026-2027) | PRD |
| FR5.3 | Invoice breakdown: subtotal, discount, PPN, grand_total | PRD |
| FR5.4 | PPN calculated on (subtotal - discount) | PRD |
| FR6.1 | spatie/laravel-activitylog on all models | PRD |
| FR6.2 | Log: created, updated, deleted, status_changed, payment_added | PRD |
| FR6.3 | Store: causer_id, causer_type, subject_id, subject_type, properties(old/new), description | PRD |
| FR6.4 | Owner views all logs; Staff views own | PRD |
| FR7.1 | Outbox table (n8n_sync_outbox) with idempotency_key (UUID) | PRD |
| FR7.2 | Event-driven push on order status change | PRD |
| FR7.3 | Payload: PII-stripped (order_id, order_date, total, status only) | PRD |
| FR7.4 | Cron dispatch (every 5 min): batch 50, retry 3x exponential backoff | PRD |
| FR7.5 | Failed after 3 retries → Telegram notification to owner | PRD |
| FR8.1 | spatie/laravel-backup daily to S3/Spaces | PRD |
| FR8.2 | Backup record in `backups` table | PRD |
| FR8.3 | Documented restore procedure in README | PRD |
| FR9.1 | Artisan command `orders:import` from CSV/XLSX | PRD |
| FR9.2 | Configurable column mapping | PRD |
| FR9.3 | Row-level validation, error report per row | PRD |
| FR9.4 | Batch via queue for large files | PRD |
| FR10.1 | Create staff accounts (email, password, name) | PRD |
| FR10.2 | Deactivate staff (is_active=false, orders preserved) | PRD |
| FR10.3 | Reassign orders: `orders:reassign --from=staff_id --to=owner_id` | PRD |
| FR10.4 | Staff cannot delete own account | PRD |

### Non-Functional Requirements

| ID | Requirement | Target |
|----|-------------|--------|
| NFR1 | Mobile-first, iOS HIG compliance (bottom tabs, glassmorphism, swipe gestures) | Must |
| NFR2 | React SPA + Fetch API (supersedes PRD Alpine.js spec) | Must |
| NFR3 | Offline draft auto-save (localStorage) | Must |
| NFR4 | API response < 200ms (p95) for CRUD | Should |
| NFR5 | MySQL/MariaDB (production), SQLite (dev) | Must |
| NFR6 | PHP 8.3+, Laravel 13 | Must |
| NFR7 | Zero-downtime deploy via `php artisan down --retry=60` | Should |
| NFR8 | Playwright E2E tests (serial, seeded owner) | Must |

### Additional Requirements (from Architecture & Database)

| ID | Requirement | Source |
|----|-------------|--------|
| AR1 | API controllers in `app/Http/Controllers/Api/` | ARCH |
| AR2 | Sanctum 500→401 fix in `bootstrap/app.php` | ARCH |
| AR3 | Soft-delete slug with `withTrashed()` | ARCH |
| AR4 | Remove `auth:sanctum` from web routes, handle in Alpine appShell() | ARCH |
| AR5 | Logout `.finally()` pattern | ARCH |
| AR6 | Playwright API testing serial, seed owner | ARCH |
| DB1 | 16 tables (users, personal_access_tokens, categories, products, orders, order_items, payments, order_status_logs, order_date_histories, activity_log, returns, return_items, backups, draft_autosaves, n8n_sync_outbox) | DB |
| DB2 | Migration order specified (15 migrations) | DB |
| DB3 | Foreign keys with proper cascade/restrict | DB |
| DB4 | Indexes on all FKs and query columns | DB |

### UX Design Requirements

| Area | Spec |
|------|------|
| Navigation | Bottom tab bar (Orders, Products, Reports, Settings) — glassmorphism, blur backdrop |
| Forms | iOS-style bottom sheets (slide up, drag down to dismiss) |
| Lists | Swipe actions (edit, delete) via @alpinejs/touch |
| Pull-to-refresh | Native feel on order list |
| Color | System light/dark, semantic colors (green=completed, orange=processing, red=cancelled) |
| Typography | SF Pro / system font stack |
| Feedback | Toast (SweetAlert2), haptic via `navigator.vibrate()` |

### FR Coverage Map

| FR | Epic(s) |
|----|---------|
| FR1.x | Epic 1: Auth & Authorization |
| FR2.x | Epic 2: Products |
| FR3.x | Epic 3: Orders Core |
| FR4.x | Epic 4: Payments |
| FR5.x | Epic 4: Payments (PPN) |
| FR6.x | Epic 5: Audit & Soft Delete |
| FR7.x | Epic 6: n8n Sync |
| FR8.x | Epic 7: Backup & Import |
| FR9.x | Epic 7: Backup & Import |
| FR10.x | Epic 1: Auth & Authorization (User Mgmt) |
| NFR1-3,8 | Epic 8: Mobile UI |
| NFR4-7 | Cross-cutting (infra, testing) |
| v1.1 features | Epic 9: v1.1 Enhancements |

## Epic List

1. **Epic 1: Auth & Authorization** — Multi-user auth with Sanctum, role-based policies, row-level scoping
2. **Epic 2: Products CRUD** — Owner-only product management with stock tracking
3. **Epic 3: Orders Core** — Create/edit orders, optimistic locking, price snapshot, state machine, draft auto-save, date validation/history
4. **Epic 4: Payments & PPN** — Multi-payment per order, computed totals, configurable tax
5. **Epic 5: Audit Trail & Soft Delete** — Activity logging, soft deletes with role-based force delete
6. **Epic 6: n8n Sync to Google Sheets** — Outbox pattern, PII stripping, retry, notifications
7. **Epic 7: Backup & Import** — Daily S3 backup, CSV/XLSX import with validation
8. **Epic 8: Mobile UI (Alpine.js + Touch)** — iOS HIG bottom tabs, bottom sheets, swipe gestures, pull-to-refresh
9. **Epic 9: v1.1 Enhancements** — Reports, returns, auto-cancel, PDF invoice, per-item discount (backlog)

---

## Epic 1: Auth & Authorization

**Goal:** Implement multi-user authentication with Laravel Sanctum SPA, role-based access control (Owner/Staff), row-level authorization policies, and owner-only user management.

### Story 1.1: Sanctum SPA Authentication ✅

**Status:** Done — see [01-sanctum-auth](features/01-sanctum-auth.md)

**As a** user (Owner or Staff)  
**I want** to log in via email/password and maintain a session  
**So that** I can access the application securely

**Acceptance Criteria:**

**Given** a fresh Laravel 13 install with Sanctum configured  
**When** I POST `/api/login` with valid credentials  
**Then** I receive a 200 response with user data and Sanctum token  
**And** subsequent requests with the token authenticate successfully  
**And** the Sanctum 500→401 fix is applied in `bootstrap/app.php` (exception handler for TokenMismatchException → 401)

**Given** I am authenticated  
**When** I POST `/api/logout`  
**Then** my token is revoked  
**And** the response uses `.finally()` pattern to ensure cleanup

**Given** invalid credentials  
**When** I POST `/api/login`  
**Then** I receive 401 with error message  
**And** after 5 failed attempts, I am rate limited for 1 minute

---

### Story 1.2: Role-Based Access Control ✅

**Status:** Done — `OrderPolicy` live with owner `before()` bypass; `ProductPolicy`, `CategoryPolicy`, `UserPolicy` live

**As an** Owner  
**I want** to have full access to all resources  
**So that** I can manage the entire business

**As a** Staff  
**I want** to access only my assigned resources  
**So that** I cannot see other staff's data

**Acceptance Criteria:**

**Given** a User model with `role` enum ('owner', 'staff')  
**When** I create a Policy for each model (OrderPolicy, ProductPolicy, etc.)  
**Then** `viewAny` scopes query: Owner → all, Staff → where user_id = auth()->id()  
**And** `view` checks: Owner → true, Staff → resource.user_id === auth()->id()  
**And** `create`, `update`, `delete` follow same ownership rules  
**And** Owner bypasses all row-level checks via Gate::before

**Given** Staff user  
**When** I access `/api/orders`  
**Then** I only see orders where `user_id = my_id`  
**And** I cannot access `/api/orders/{other_staff_id}` (403)

**Given** Owner user  
**When** I access any resource  
**Then** I see all records regardless of ownership

---

### Story 1.3: Owner User Management ✅

**Status:** Done — see [02-user-management](features/02-user-management.md)

**As an** Owner  
**I want** to create, deactivate, and reassign staff accounts  
**So that** I can manage my team

**Acceptance Criteria:**

**Given** I am authenticated as Owner  
**When** I POST `/api/users` with {name, email, password, role: 'staff'}  
**Then** staff account is created with `is_active = true`  
**And** staff can immediately log in

**Given** a Staff user exists  
**When** I PATCH `/api/users/{id}` with `{is_active: false}`  
**Then** staff cannot log in (auth check fails)  
**And** their existing orders remain visible to Owner  
**And** their `user_id` on orders is preserved (not nullified)

**Given** a deactivated Staff  
**When** I POST `/api/orders/reassign` with `{from_user_id, to_user_id}`  
**Then** all orders from `from_user_id` are reassigned to `to_user_id`  
**And** activity log records the reassignment

**Given** Staff user  
**When** I attempt DELETE `/api/users/{my_id}`  
**Then** I receive 403 Forbidden

---

### Story 1.4: Password Reset & Dynamic Role Assignment ✅

**As an** Owner  
**I want** to reset staff passwords and assign/promote roles  
**So that** I can onboard and manage team access

**Acceptance Criteria:**

**Given** Staff email exists  
**When** Owner POST `/api/password/email` with `{email}`  
**Then** password reset link/token generated  
**And** Staff can POST `/api/password/reset` with new password

**Given** Owner on user management UI  
**When** Owner creates user with `role: 'staff'` or `'owner'`  
**Then** role stored as enum (`UserRole`)  
**And** Staff promoted to Owner via PATCH `/api/users/{id}` with `{role: 'owner'}`

**Given** authenticated user  
**When** viewing own profile in UI  
**Then** Edit/Delete buttons hidden (self-management guard)

**Deliverables:** `AuthController::sendResetLink`, `AuthController::resetPassword`, `UserController` role enum validation, React `Users.tsx` with role select + Reset PW button.

---

## Epic 2: Products CRUD

**Goal:** Owner-only product management with categories, stock tracking, and soft deletes.

### Story 2.1: Category Management ✅

**Status:** Done — see [03-produk-crud](features/03-produk-crud.md)

**As an** Owner  
**I want** to create and manage product categories  
**So that** products are organized

**Acceptance Criteria:**

**Given** I am Owner  
**When** I CRUD `/api/categories`  
**Then** standard CRUD works (name, description)  
**And** Staff cannot access (403)

---

### Story 2.2: Product CRUD with Stock ✅

**Status:** Done — see [03-produk-crud](features/03-produk-crud.md)

**As an** Owner  
**I want** to manage products with price and stock  
**So that** staff can sell them

**Acceptance Criteria:**

**Given** I am Owner  
**When** I CRUD `/api/products` with {nama, harga_jual, harga_beli, stok, category_id, sku, satuan}  
**Then** price stored as `decimal(15,2)`, stock as integer  
**And** soft delete works (`deleted_at` set)  
**And** `withTrashed()` scope available for admin views

**Status:** Done — staff may **read** products/categories (order line items); write remains owner-only.

---

## Epic 3: Orders Core

**Goal:** Complete order lifecycle — create, edit, status transitions, optimistic locking, price snapshots, draft auto-save, date validation/history.

**Status:** Done — `Order` model, migrations, `OrderService`, `OrderController`, React `Orders.tsx`, `OrderTest`.
### Story 3.0: Order Schema Migrations & Models ✅

### Story 3.1: Create Order (Draft) ✅

**As a** Staff  
**I want** to create a draft order with customer details and line items  
**So that** I can build an order before finalizing

**Acceptance Criteria:**

**Given** I am authenticated Staff  
**When** I POST `/api/orders` with {customer_name, customer_phone, order_date, items: [{product_id, quantity}], notes, status: 'draft'}  
**Then** order created with `user_id = auth()->id()`, `status = 'draft'`, `version = 1`  
**And** `invoice_no` auto-generated (format: `INV-YYYYMMDD-###`)  
**And** order_items created with `price_snapshot = product.harga_jual`, `product_name = product.nama`  
**And** `ppn_percentage` snapshotted from `config('ppn.percentage')`  
**And** response includes full order with computed totals

**Given** I provide `order_date` in future  
**When** I create order  
**Then** validation fails: `order_date` must be `before_or_equal:today` and `after_or_equal:2020-01-01`

**Given** I provide `order_date` before 2020  
**When** I create order  
**Then** validation fails

---

### Story 3.2: Optimistic Locking on Update ✅

**As a** Staff  
**I want** to be warned if someone else modified the order while I was editing  
**So that** I don't lose data

**Acceptance Criteria:**

**Given** an order with `version = 5`  
**When** I PUT `/api/orders/{id}` with `{version: 5, status: 'pending', ...}`  
**And** no one else modified it  
**Then** update succeeds, `version` becomes 6  
**And** response includes new version

**Given** an order with `version = 5`  
**When** I PUT `/api/orders/{id}` with `{version: 5, ...}`  
**But** another request already updated it to version 6  
**Then** I receive 409 Conflict with message "Order telah diubah oleh pengguna lain. Muat ulang."  
**And** order unchanged in database

**Given** I omit `version` in update request  
**When** I PUT `/api/orders/{id}`  
**Then** I receive 422 validation error (version required)

---

### Story 3.3: Price Snapshot at Checkout ✅

**As an** Owner  
**I want** order line items to preserve the price at time of checkout  
**So that** historical orders reflect correct pricing

**Acceptance Criteria:**

**Given** a product with price 100,000  
**When** I create order in draft, add item, then checkout (status: draft → pending)  
**Then** `order_items.price_snapshot = 100000`  
**And** `order_items.product_name = 'Product Name'`

**Given** I later update product price to 120,000  
**When** I view the historical order  
**Then** order still shows 100,000 per item (from snapshot)  
**And** product current price shows 120,000

---

### Story 3.4: Status State Machine ✅

**As a** Staff  
**I want** order status to follow a strict pipeline  
**So that** orders don't go backwards incorrectly

**Acceptance Criteria:**

**Given** order status transitions defined as:
- `draft` → [`pending`, `cancelled`]
- `pending` → [`diproses`, `cancelled`]
- `diproses` → [`dikirim`]
- `dikirim` → [`selesai`]
- `selesai` → [] (terminal)
- `cancelled` → [] (terminal)

**When** I PUT `/api/orders/{id}` with new status  
**Then** transition validated via `OrderStatus::allowedTransitions()`  
**And** invalid transition (e.g., `dikirim` → `pending`) returns 422 with "Invalid status transition"  
**And** valid transition updates status, increments version, logs to `order_status_logs`

**Given** order is `draft`  
**When** I change to `pending` (checkout)  
**Then** stock decremented for each item (`product.stok -= quantity`)  
**And** price snapshots finalized  
**And** `n8n_sync_outbox` record created (Epic 6)

**Deliverables:** `app/Services/OrderService.php`, `app/Exceptions/InvalidStatusTransitionException.php`, `OrderPolicy` with row-level scoping.

---

### Story 3.5: Draft Auto-Save (localStorage + Server Fallback) ✅

**As a** Staff  
**I want** my draft order to auto-save every 5 seconds  
**So that** I don't lose work if browser crashes

**Acceptance Criteria:**

**Given** I am on order create/edit page with unsaved changes  
**When** 5 seconds pass  
**Then** draft JSON saved to `localStorage.draft_order_{user_id}`  
**And** includes all form fields (customer, items, notes, order_date)

**Given** I reload the page  
**When** draft exists in localStorage  
**Then** form pre-populated with draft data  
**And** toast shows "Draft dipulihkan dari penyimpanan lokal"

**Given** I successfully checkout (status → pending)  
**When** order created  
**Then** localStorage draft cleared

**Given** I switch devices  
**When** I log in  
**Then** optional server-side fallback: GET `/api/drafts/restore` returns latest `draft_autosaves` record if exists

---

### Story 3.6: Order Date History ✅

**As an** Owner  
**I want** every order_date change tracked  
**So that** I can audit backdating

**Acceptance Criteria:**

**Given** an order with `order_date = 2026-07-17`  
**When** I update `order_date` to `2026-07-15`  
**Then** record inserted into `order_date_histories` with {order_id, old_date, new_date, changed_by, changed_at}  
**And** order `order_date` updated  
**And** activity log records the change

**Given** I view order detail  
**When** I check date history  
**Then** I see chronological list of all changes with user and timestamp

---

### Story 3.7: Order API & Policy Scaffold ✅

**As a** developer  
**I want** REST endpoints and authorization for orders  
**So that** Alpine UI and tests can consume orders

**Acceptance Criteria:**

**Given** `OrderController` at `app/Http/Controllers/Api/OrderController.php`  
**When** routes registered under `auth:sanctum`  
**Then** `apiResource('orders', OrderController)` exposes index/show/store/update/destroy  
**And** `OrderPolicy` enforces: Owner → all orders; Staff → `user_id = auth()->id()`  
**And** `StoreOrderRequest`, `UpdateOrderRequest` validate fields + `version` on update

**Given** Staff creates order  
**When** POST `/api/orders`  
**Then** `user_id` auto-set to `auth()->id()` (not client-supplied)

**Deliverables:** `routes/api.php` order routes, `tests/Feature/OrderTest.php` covering create, scope, version conflict.

---

## Epic 4: Payments & PPN

**Goal:** Multi-payment support per order, computed payment status, configurable PPN calculation.

### Story 4.1: Payments CRUD ✅

**As a** Staff  
**I want** to record multiple payments for an order  
**So that** I can track DP, cicilan, mixed methods

**Acceptance Criteria:**

**Given** an order in `pending`/`diproses`/`dikirim` status  
**When** I POST `/api/orders/{id}/payments` with {amount, metode, notes, paid_at}  
**Then** payment record created linked to order  
**And** `order.total_paid` accessor returns `payments()->sum('amount')`  
**And** `metode` enum: `cash`, `transfer`, `qris`

**Given** I add payment of 500,000 to order of 1,000,000  
**When** I view order  
**Then** status badge shows "Partial"  
**And** `total_paid = 500000`, `remaining = 500000`

**Given** I add another payment of 500,000 (transfer)  
**When** I view order  
**Then** status badge shows "Paid"  
**And** payments list shows both records with methods

**Given** I add payment of 600,000 (overpayment)  
**When** I view order  
**Then** status badge shows "Overpaid"  
**And** overpayment tracked as credit (v1.1: credit_note table)

---

### Story 4.2: PPN Configuration & Calculation ✅

**As an** Owner  
**I want** to enable/disable PPN and set percentage  
**So that** invoices comply with tax regulations

**Acceptance Criteria:**

**Given** config `ppn.enabled = true`, `ppn.percentage = 11`  
**When** I calculate order total  
**Then** subtotal = sum(price_snapshot * qty)  
**And** discount_total = sum(discount_value)  
**And** after_discount = subtotal - discount_total  
**And** ppn_amount = after_discount * (11/100)  
**And** grand_total = after_discount + ppn_amount

**Given** config `ppn.enabled = false`  
**When** I calculate order total  
**Then** ppn_amount = 0, grand_total = after_discount

**Given** I view invoice  
**When** PPN enabled  
**Then** breakdown shows: Subtotal, Discount, PPN (11%), Grand Total  
**And** PPN percentage shown on invoice

---

## Epic 5: Audit Trail & Soft Delete

**Goal:** Complete activity logging and role-based soft/hard delete.

### Story 5.1: Activity Log on All Models ✅

**Status:** Done — see [05-audit-soft-delete.md](features/05-audit-soft-delete.md)

**As an** Owner  
**I want** all changes tracked with old/new values  
**So that** I have full audit trail

**Acceptance Criteria:**

**Given** spatie/laravel-activitylog installed and configured  
**When** any model (Order, Product, Payment, User, etc.) is created/updated/deleted  
**Then** activity_log record created with: causer_id, causer_type, subject_id, subject_type, properties (old/new), description  
**And** important events tagged: `payment_added`, `status_changed`, `price_changed`, `order_deleted`

**Given** I am Owner  
**When** I view activity log for an order  
**Then** I see chronological list of all changes with user, timestamp, field-level diff

**Given** I am Staff  
**When** I view activity log  
**Then** I only see logs for orders I own

**Given** log retention policy  
**When** scheduler runs `activitylog:clean-retention` monthly  
**Then** logs older than 90 days deleted  
**Except** tagged important logs (`important=true`) retained forever

---

### Story 5.2: Soft Delete with Role-Based Force Delete ✅

**Status:** Done — `ActivityLogTest`, `OrderTest` force delete policy

**As a** Staff  
**I want** to delete orders (soft delete)  
**So that** mistakes can be recovered

**As an** Owner  
**I want** to permanently delete orders  
**So that** I can clean up test data

**Acceptance Criteria:**

**Given** Staff deletes order  
**When** DELETE `/api/orders/{id}`  
**Then** `deleted_at` set, record hidden from normal queries  
**And** activity log records soft delete

**Given** Owner deletes order  
**When** DELETE `/api/orders/{id}?force=true`  
**Then** record hard deleted from database  
**And** activity log records force delete

**Given** Staff attempts force delete  
**When** DELETE with `force=true`  
**Then** 403 Forbidden

---

## Epic 6: n8n Sync to Google Sheets

**Goal:** Reliable, PII-safe sync of order data to Google Sheets via n8n.

### Story 6.1: Outbox Pattern Implementation ✅

**Status:** Done — `N8nSyncService::pushToOutbox`, status transition test in `N8nSyncTest`

**As a** system  
**I want** order changes queued to outbox table  
**So that** n8n sync is reliable and idempotent

**Acceptance Criteria:**

**Given** order status changes (any transition)  
**When** `OrderUpdated` event fires  
**Then** `N8nSyncService::pushToOutbox(order)` called  
**And** record inserted into `n8n_sync_outbox` with: order_id, idempotency_key (UUID v4), payload (PII-stripped JSON), status='pending', attempts=0

**Given** payload construction  
**When** building JSON  
**Then** includes: order_id, invoice_no, order_date, status, subtotal, ppn_amount, grand_total, total_paid, items[{product_name, quantity, price, subtotal}], payments[{amount, metode, paid_at}], created_at  
**And** EXCLUDES: customer_name, customer_phone, customer_address, staff name/email

---

### Story 6.2: Cron Dispatch with Retry ✅

**Status:** Done — see [06-n8n-sync.md](features/06-n8n-sync.md)

**As a** system  
**I want** outbox processed every 5 minutes with retry logic  
**So that** transient failures don't lose data

**Acceptance Criteria:**

**Given** scheduler runs `php artisan n8n:dispatch` every 5 minutes  
**When** command executes  
**Then** fetches up to 50 records where `status='pending' ORDER BY created_at`  
**For each** record: POST to n8n webhook URL with payload  
**On success** (2xx): update `status='sent', sent_at=now()`  
**On failure**: increment `attempts`, if `attempts >= 3` → `status='failed'`, `last_error=response`, send Telegram notification to owner  
**And** exponential backoff: retry delays handled by scheduler frequency (5 min, 10 min, 15 min effectively)

**Given** n8n webhook returns 200 but Sheets write fails  
**When** n8n returns error in response body  
**Then** treated as failure, retry logic applies

---

### Story 6.3: Failed Sync Notification ✅

**Status:** Done — Telegram alert on permanent failure, Reports retry UI

**As an** Owner  
**I want** to be notified if sync fails permanently  
**So that** I can investigate

**Acceptance Criteria:**

**Given** outbox record reaches `attempts=3` and `status='failed'`  
**When** scheduler processes it  
**Then** Telegram message sent to owner: "⚠️ n8n Sync Gagal: Order #{order_id} (INV-...) gagal sinkronisasi setelah 3 percobaan. Error: {last_error}"  
**And** record remains in `failed` state for manual retry

---

## Epic 7: Backup & Import

**Goal:** Daily automated backup to S3 and legacy data import from spreadsheets.

### Story 7.1: Daily Backup to S3/Spaces ✅

**Status:** Done — spatie/laravel-backup, `GET/POST /api/backups`, `backup:restore`, Reports backup UI — [07-backup-import.md](features/07-backup-import.md)

**As an** Owner  
**I want** daily database and file backups to cloud storage  
**So that** I can recover from data loss

**Acceptance Criteria:**

**Given** spatie/laravel-backup configured  
**When** scheduler runs `php artisan backup:run` daily  
**Then** backup created (database dump + files)  
**And** uploaded to S3/Spaces disk  
**And** record inserted into `backups` table with name, disk, size, checksum, status  
**And** old backups cleaned per retention (keep 7 daily, 4 weekly, 12 monthly)

**Given** I need to restore  
**When** I follow documented procedure in README  
**Then** I can download backup from S3, run `php artisan backup:restore {filename}`  
**And** database restored successfully

---

### Story 7.2: Legacy Import from CSV/XLSX ✅

**Status:** Done — `orders:import`, `POST /api/imports`, XLSX `sheets_legacy`, Reports import UI — [10-sheets-import.md](features/10-sheets-import.md)

**As an** Owner  
**I want** to import historical orders from spreadsheet  
**So that** all data is in one system

**Acceptance Criteria:**

**Given** CSV/XLSX file in `storage/app/imports/`  
**When** I run `php artisan orders:import {filename} --mapping=config`  
**Then** command reads file, maps columns via config (e.g., 'Customer Name' → customer_name)  
**And** validates each row: required fields, valid product references, date format  
**And** valid rows → orders created in Draft status (Owner can review)  
**And** invalid rows → error report with row number, field, reason  
**And** for large files (>100 rows): processed via queue jobs (database queue)  
**And** summary shown: total rows, success, failed, errors

---

## Epic 8: Mobile UI (React + shadcn/ui)

**Goal:** iOS HIG compliant mobile-first UI with bottom tabs, drawers, swipe gestures, pull-to-refresh.

**Status:** Done — see [08-mobile-ui.md](features/08-mobile-ui.md)

### Story 8.1: SPA Shell & Bottom Tab Navigation ✅

**Implementation:** [`resources/js/components/ios/IosTabBar.tsx`](resources/js/components/ios/IosTabBar.tsx), [`AppShell.tsx`](resources/js/components/AppShell.tsx)

- Bottom tab bar: Orders, Products, Categories, Reports, Users (owner)
- Glassmorphism via Tailwind `backdrop-blur` + border
- React Router client-side navigation (no full page reload)

### Story 8.2: iOS-Style Bottom Sheets for Forms ✅

**Implementation:** [`resources/js/components/ui/drawer.tsx`](resources/js/components/ui/drawer.tsx), [`Orders.tsx`](resources/js/pages/Orders.tsx)

- Drawer slides up from bottom, drag handle, backdrop dismiss
- Form fields: customer autocomplete (datalist), date picker, product search combobox, notes

### Story 8.3: Swipe Actions on Order List ✅

**Implementation:** [`IosSwipeRow.tsx`](resources/js/components/ios/IosSwipeRow.tsx)

- Swipe left: Edit + Delete (AlertDialog confirmation)
- Swipe right: Bayar (payment) for pending orders

### Story 8.4: Pull-to-Refresh on Order List ✅

**Implementation:** [`Orders.tsx`](resources/js/pages/Orders.tsx) pull gesture + `load()` refresh

### Story 8.5: Toast & Haptic Feedback ✅

**Implementation:** sonner toasts + `haptic()` in [`lib/format.ts`](resources/js/lib/format.ts)

- Success/error toasts on all mutating actions
- `navigator.vibrate` for tactile feedback

---

## Epic 9: v1.1 Enhancements

**Goal:** Post-launch features from ROADMAP.md v1.1.

**Status:** Done — see [09-v1-enhancements.md](features/09-v1-enhancements.md). All stories shipped except **9.10 branch_id** (deferred).

| Story | Title | Status | Implementation |
|-------|-------|--------|----------------|
| 9.1 | Multidimensional Reports (staff/product/status) | ✅ Done | `ReportController`, `Reports.tsx` tabs |
| 9.2 | Filter + Search on order list | ✅ Done | `Orders.tsx` search + status chips |
| 9.3 | Invoice Modal + PDF (dompdf) | ✅ Done | `InvoiceController`, `InvoiceTest`, PDF blade |
| 9.4 | Return/Refund Flow | ✅ Done | `ReturnController`, `ReturnTest`, Returns UI on Orders |
| 9.5 | Auto-cancel Pending > 7 days | ✅ Done | `orders:auto-cancel` hourly, `OrdersAutoCancelTest` |
| 9.6 | Per-item Discount | ✅ Done | `discount_type`/`discount_value` on `order_items`, `OrderTest` |
| 9.7 | Staff Password Reset Email | ✅ Done | Epic 1.4 `AuthController` reset flow |
| 9.8 | n8n Scheduled DB Backup | ✅ Done | `POST /api/ops/backup`, `OpsBackupTest`, [07-backup-import.md](features/07-backup-import.md) |
| 9.9 | Deploy Maintenance Mode + IP whitelist | ✅ Done | `PreventRequestsDuringMaintenanceWithIpWhitelist`, [DEPLOY.md](DEPLOY.md) |
| 9.10 | Branch_id Multi-store Scaffold | **TUNDA** | Not started — YAGNI per ROADMAP |

---

## Epic 10: Google Sheets Import

**Goal:** Import legacy workbook (Products + Orders tabs) via upload or scheduled n8n pull.

**Status:** Done — see [10-sheets-import.md](features/10-sheets-import.md)

| Story | Title | Status | Implementation |
|-------|-------|--------|----------------|
| 10.1 | `sheets_legacy` mapping (nama+satuan, lunas payment) | ✅ Done | `config/import.php`, `OrdersImportService` |
| 10.2 | Two-tab Excel template + download | ✅ Done | `ImportTemplateService`, `GET /api/imports/template`, Reports UI |
| 10.3 | Scheduled Sheets pull via n8n | ✅ Done | `SheetsImportController`, `POST /api/imports/sheets/*` |
| 10.4 | Import cursor + idempotent row dedupe | ✅ Done | `Cache` cursor + row hash dedupe in `OrdersImportService` |

**Clarification:** Epic 6 pushes **out** ([06-n8n-sync.md](features/06-n8n-sync.md)). Epic 10 pulls **in** from legacy workbook.

## Epic 11: UMKM Buku Bon & Catatan Pengeluaran

**Goal:** Two UMKM features — (1) expense notes in Laporan with laba bersih; (2) customer receivables ledger (buku bon) in Pengguna → Pelanggan with quick-pay.

**Status:** Done — see [archive/UMKM_BON_PENGELUARAN_PLAN.md](archive/UMKM_BON_PENGELUARAN_PLAN.md)

| Story | Title | Status | Implementation |
|-------|-------|--------|----------------|
| 11.1 | Catatan Pengeluaran (CRUD + laporan harian/tren `expenses_total`, `laba_bersih`) | ✅ Done | `expenses` table, `ExpenseCategory`, `ExpenseController` (owner-only, bump aggregates version), `ReportService::profitRows()/expenseRows()`, tab `Pengeluaran` + kartu Harian + garis laba bersih di `ProfitTrendChart` |
| 11.2 | Buku Bon receivables (`GET /api/receivables` grup telepon/nama, aging bucket, quick-pay via payments API) | ✅ Done | `ReceivablesController`, tab `Buku Bon` di `ReportsDashboard` (`BukuBonPanel`) dengan sheet `Catat Bayar` (PaymentPolicy menegakkan staff-own-order) |

### Story 11.1: Catatan Pengeluaran (Detail)

**As an** Owner
**I want** to record daily expenses (bahan baku, kemasan, transport, operasional, gaji, lain-lain)
**So that** laba bersih in the daily report reflects real costs

**Acceptance Criteria:**

**Given** an owner records an expense with `amount > 0`, `expense_date <= today`, valid category
**When** the daily or daily-trend report is requested
**Then** the payload includes `expenses_total` and `laba_bersih = net_profit − expenses_total`
**And** the aggregate cache version is bumped so reports never serve stale totals
**And** staff receive 403 on all expense endpoints; cross-tenant rows are invisible

### Story 11.2: Buku Bon Receivables (Detail)

**As an** Owner or Staff
**I want** to see who still owes money, grouped per customer with aging
**So that** I can collect payments (buku bon)

**Acceptance Criteria:**

**Given** orders where `grand_total > total_paid` excluding draft/cancelled
**When** `GET /api/receivables` is called
**Then** orders are grouped by customer phone (name fallback — orders have no `customer_id`)
**And** groups carry `unpaid_amount`, `unpaid_count`, `oldest_order_date`, `aging_bucket` (`<=7`, `8-14`, `15-30`, `>30`)
**And** totals match the orders-summary unpaid math
**And** quick-pay reuses `POST /api/orders/{order}/payments` (staff limited to their own orders)

### Story 9.4: Return/Refund Flow (Detail)

**As an** Owner  
**I want** to process returns against completed orders  
**So that** stock and refunds are tracked

**Acceptance Criteria:**

**Given** order status `selesai`  
**When** Owner POST `/api/orders/{id}/returns` with `{items: [{order_item_id, quantity, reason}], reason}`  
**Then** `returns` + `return_items` records created  
**And** `refund_amount` computed from `price_snapshot * quantity`  
**And** `restock_status` toggled when stock restored  
**And** return quantity ≤ original order item quantity

### Story 9.5: Auto-cancel Expired Pending (Detail)

**Given** scheduler runs `orders:auto-cancel` hourly  
**When** order `status=pending` AND `created_at < now()->subDays(7)`  
**Then** status → `cancelled`, stock restored, activity logged  
**And** `order_status_logs` entry with reason "Auto-cancel: expired 7 hari"

---

## Cross-Cutting Concerns

### Testing (NFR8)
- **PHPUnit:** 80 tests (`php artisan test`)
- **E2E:** Playwright `tests/E2E/auth.spec.js`, `rbac.spec.js` (requires app on port 8084)
- **Feature coverage:** auth, policies, orders, payments, activity log, n8n outbox, backup/import API, returns, invoice PDF, auto-cancel
- **Unit:** `PolicyTest` (role gates)

### Deployment (NFR7)
- `php artisan down --retry=60 --render=errors::503` before deploy
- `php artisan up` after migrate
- `php artisan queue:restart` after deploy
- Health check endpoint for load balancer

### Configuration Required (Infra)
| Config | Purpose |
|--------|---------|
| `S3_BACKUP_BUCKET`, `AWS_*` | Backup destination |
| `N8N_WEBHOOK_URL` | n8n sync endpoint |
| `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID` | Notifications |
| `PPN_ENABLED`, `PPN_PERCENTAGE` | Tax config |
| `SANCTUM_STATEFUL_DOMAINS` | SPA domain |

---

## Story Mapping to Sprints (Suggested)

| Sprint | Stories | Goal |
|--------|---------|------|
| 1 ✅ | 1.1–1.4, 2.1, 2.2 | Auth + Products foundation |
| 2 ✅ | 3.0–3.7 | Orders core |
| 3 ✅ | 3.5, 4.1, 4.2 | Draft auto-save, Payments + PPN |
| 4 ✅ | 5.1, 5.2, 6.1–6.3 | Audit, Soft delete, n8n sync |
| 5 ✅ | 7.1, 7.2, 8.1–8.5 | Backup, Import, Mobile UI |
| 6 ✅ | Polish, tests, deploy docs | Harden, document, ship |
| Post-v1.0 ✅ | 9.1–9.9 | v1.1 enhancements (9.10 TUNDA) |
| UMKM ✅ | 11.1, 11.2 | Buku bon + catatan pengeluaran |

---

## Definition of Done (Per Story)

- [x] Implementation complete per acceptance criteria (Epics 1–9, except 9.10)
- [x] Feature tests for API endpoints
- [x] Migrations included and runnable
- [x] Documentation updated (this file, [features/](features/) handoffs, DEPLOY.md)
- [ ] Playwright E2E full order flow (auth + RBAC only today)
- [ ] PHPStan level 5 / Pint enforced in CI
- [ ] Production infra creds wired (see [features/handoff-ops.md](features/handoff-ops.md))