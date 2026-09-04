# Order Tracker — Project Brief

**Version:** 1.0.0  
**Status:** Phase 1 Complete  
**Stack:** Laravel 13 + Alpine.js 3 + MySQL 8

---

## Purpose

Mobile-first order management system for small-to-medium businesses. Owner manages products, categories, and staff. Staff enters and tracks orders. All data stays in-house with optional cloud sync.

---

## Problem

Small shops use paper notes or generic spreadsheets to track orders. No role separation, no inventory tracking, no recovery from accidental deletion. Existing POS apps are either too expensive or too complex for 5–10 order/day volumes.

---

## Solution

A lean, mobile-first SPA with:

- **Owner/Staff roles** — Staff can't delete products or manage users
- **Point-of-sale workflow** — Draft → Pending → Process → Ship → Complete
- **Safety nets** — Soft deletes, rate limiting, restore, activity log
- **Offline-ready future** — Draft auto-save to localStorage
- **Zero monthly cost** — Runs on own VPS, no SaaS fees

---

## Key Design Decisions

| Decision | Rationale |
|----------|-----------|
| **No Filament/Livewire** | Staff don't need admin panels; Alpine + Fetch is lighter |
| **iOS HCI (bottom tabs)** | Target users are mobile-first, not desktop |
| **Sanctum tokens** | Simple, no OAuth complexity, good for 1-10 users |
| **Policy-based RBAC** | Laravel native, testable, auditable |
| **Optimistic locking** | Prevents data loss in low-volume concurrent edits |
| **MySQL over SQLite** | Client insists on MariaDB/MySQL production |

---

## User Personas

**Owner (Andi)**  
Runs a small grocery shop. Does inventory monthly. Has 2 staff. Needs to see what was sold last week. Does not want to pay for SaaS.

**Staff (Rina)**  
Works the counter. Enters customer orders. Catalogs new stock. Never touches user management or settings.

---

## Current Scope (Phase 1 — ✓ Complete)

1. **Auth** — Sanctum login/logout, rate limit, password reset
2. **User Mgmt** — Owner-only CRUD Staff, active/inactive
3. **Product Mgmt** — CRUD products + categories, Staff read-only
4. **RBAC** — Dynamic role assignment, self-management guard

## Phase 2 (Next)

5. **Orders** — Full order lifecycle with state machine
6. **Stock** — Auto-decrement, low-stock alerts
7. **Backup** — S3 daily backup + import
8. **Mobile UI** — Bottom sheets, swipe, pull-to-refresh

---

## Architecture Highlights

- **API-first** — All mutations go through `app/Http/Controllers/Api/`
- **Centralized API Client** — `window.Api` wraps fetch with auth headers
- **Database enum** — `UserRole` enum in DB + PHP, not strings
- **Soft deletes** — Every business entity uses `SoftDeletes`
- **Activity log** — Spatie Activitylog tracks all mutations

---

## Links

- **Code:** `/var/www/html/order-tracker`
- **Docs:** `docs/` (PRD, Architecture, DB Schema)
- **Entry:** `owner@example.com` / `password` on `:8084`
