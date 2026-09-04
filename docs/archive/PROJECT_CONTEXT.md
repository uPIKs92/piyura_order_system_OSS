# Project Context Transfer — Order Tracker

## Project Overview
Migrasi order system dari Google Sheets + Apps Script ke Laravel web app dengan iOS-style UI, Alpine + Fetch, mobile-first.

## Current Status
**BMAD 10-round selesai.** Semua konsep teruji. Dokumentasi arsitektur & database lengkap.

### Key Decisions
| Aspek | Keputusan |
|---|---|
| Stack | Laravel 13 + Alpine 3 + Fetch API + Tailwind 4 |
| Frontend style | iOS HIG — glassmorphism, bottom tabs, slide-up modal, swipe actions |
| Auth | Sanctum SPA (multi-user: owner/staff) |
| Authorization | Laravel Policy + Row-level scoping |
| Database | MySQL |
| Persistence | Orders persistent draft (DB, status=draft) |
| Frontend library | Alpine.js + Fetch API (NO Livewire) |
| Migration | One-time import from Sheets via artisan command |
| Laporan | Hybrid n8n sync ke Sheets (PII-stripped) |
| Multi-toko | Branch_id scaffold (TUNDA — YAGNI) |

### What Changed from Initial Concept (BMAD findings)
- Optimistic locking (version column) -> prevent last-write-wins
- Harga snapshot di order_items -> price changes don't affect past orders
- Status state machine -> no backward status transitions
- SoftDeletes + ActivityLog -> audit trail
- PII stripping in n8n sync -> privacy compliance
- Auto-cancel 7 hari pending
- Retur/refund with stock rollback
- Draft auto-save localStorage fallback

## Documents Available
```
ot-docs/
├── DATABASE.md        (26 KB — 16 tables, migration SQL, enums, FK)
├── ARCHITECTURE.md    (24 KB — stack, folder, auth, deploy, flows)
├── ROADMAP.md         (10 KB — 5 phases, 28hr total, risk matrix)
└── PROJECT_CONTEXT.md (this file)
```

### Database Tables (16)
users, personal_access_tokens, categories, products, orders, order_items, payments, order_status_logs, order_date_histories, activity_log, returns, return_items, backups, draft_autosaves, n8n_sync_outbox

## Uteke Rooms
- ot-bmad-1 — 16 memories, BMAD 10-round diskusi
- bmad-brain-1 — 19 memories, brainstorming awal

## Recommended Next Actions
1. Buat project Laravel baru (laravel new order-tracker)
2. Konfigurasi Sanctum + Policy scaffold
3. Eksekusi migration order sesuai ROADMAP.md Phase 1
4. Implement per fitur: Auth -> Produk -> Orders -> Payments -> UI -> n8n

## User Preferences
- Taufik: Telegram DM, efficient, preview-first
- Delegative: Hermes routes to specialists (Forge/Atlas/Titan)
- Workflow: docs/ -> commit -> review -> implement
- Design: iOS HIG, Alpine+Fetch, mobile-first
- Language: English
- Style: direct, no filler

## Constraints
- All profile skills symlinked to default/
- Uteke v0.7.3 active, rooms enabled
- Webhook gateway port 8644
