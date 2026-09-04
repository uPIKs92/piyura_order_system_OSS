# Order Tracker — Docs Index

Single navigation map for every document under `docs/`. Docs are split by **status** so you can tell at a glance what is current truth, what is an active/future plan, and what is finished history.

> **Live status of the project:** [EPICS_AND_STORIES.md](EPICS_AND_STORIES.md) — Epics 1–10 done (9.10 `branch_id` TUNDA). Treat it as the source of truth for "what shipped".

---

## Status legend

| Tag | Meaning |
|-----|---------|
| **reference** | Evergreen spec / ops guide. Current truth. |
| **active** | Plan being worked now, or with open (deferred) tasks. |
| **future** | Planned, not started. |
| **archived** | Completed plan or superseded doc. Kept for history. |

---

## Reference & ops (top-level)

| Doc | Status | Covers |
|------|--------|--------|
| [EPICS_AND_STORIES.md](EPICS_AND_STORIES.md) | reference | Epic/story breakdown + implementation progress (the tracker) |
| [PRD.md](PRD.md) | reference | Product requirements, functional requirements, release plan |
| [ARCHITECTURE.md](ARCHITECTURE.md) | reference | Stack, folder layout, auth, flows, deploy checklist |
| [DATABASE.md](DATABASE.md) | reference | Schema, enums, FKs, migration order |
| [LOCAL_DEV.md](LOCAL_DEV.md) | reference | Multi-tenant local dev on `*.localhost:8084` |
| [DEPLOY.md](DEPLOY.md) | reference | Deploy/rollback, scheduler, queue |
| [PRODUCTION.md](PRODUCTION.md) | reference | DNS/TLS/nginx/`.env`, wildcard subdomains, shared apex |
| [manual/](manual/README.md) | reference | Manual pengguna end-user (Bahasa Indonesia) — login sampai semua fitur |

> **Known staleness:** `PRD.md` and `ARCHITECTURE.md` were written when the frontend plan was Alpine.js. The shipped UI is a **React 19 + shadcn/ui SPA** (see [features/handoff.md](features/handoff.md)). All other content in those docs remains accurate.

---

## Feature handoffs & reference (`features/`)

Index: [features/README.md](features/README.md).

| Doc | Status | Covers |
|------|--------|--------|
| [features/handoff.md](features/handoff.md) | reference | Developer handoff — stack, run locally, key paths, scheduler, known gaps |
| [features/handoff-ops.md](features/handoff-ops.md) | reference | Ops/infra handoff — env vars, cron, queue, security, post-deploy checklist |
| [features/b2-agency-ops.md](features/b2-agency-ops.md) | reference | Multi-tenant agency runbook — provision, suspend, export tenants |
| [features/swipe-actions.md](features/swipe-actions.md) | reference | Orders swipe gesture semantics (status gating, thresholds, input paths) |
| [features/01–12](features/README.md) | reference | Per-epic / per-feature implementation handoffs (Auth → Receipt print) |

---

## Active / future plans (`plans/`)

| Doc | Status | Covers |
|------|--------|--------|
| [plans/PWA_OPTIMIZATION_PLAN.md](plans/PWA_OPTIMIZATION_PLAN.md) | active / future | Phase 1 PWA polish (done) + Phase 2 Capacitor store build (LATER) |
| [plans/PWA_OPTIMIZATION_TASKS.md](plans/PWA_OPTIMIZATION_TASKS.md) | active / future | Phase 1 checklist (done, manual device tests deferred) + Phase 2 stub |

---

## Archived — completed plans & superseded docs (`archive/`)

What got moved here and why: [archive/README.md](archive/README.md). Summary:

| Archived doc | Status | Current truth lives in |
|------|--------|------------------------|
| `CATALOG_REDESIGN*` | done | [features/03-produk-crud.md](features/03-produk-crud.md) |
| `ORDERS_PERF_REVISION_*` | done | [features/04-orders-payments.md](features/04-orders-payments.md) |
| `ORDERS_REDESIGN*` | done | [features/04-orders-payments.md](features/04-orders-payments.md) |
| `PAYMENT_SETTINGS_PLAN.md` | done | [features/04-orders-payments.md](features/04-orders-payments.md) |
| `swipe-touch-events-fix.md` | done | [features/swipe-actions.md](features/swipe-actions.md) |
| `PROJECT_BRIEF.md` | superseded | this index + [PRD.md](PRD.md) |
| `PROJECT_CONTEXT.md` | superseded | [ARCHITECTURE.md](ARCHITECTURE.md) + [EPICS_AND_STORIES.md](EPICS_AND_STORIES.md) |
| `ROADMAP.md` | superseded | [EPICS_AND_STORIES.md](EPICS_AND_STORIES.md) |
| `handoff-v1.1.md` | superseded | [features/handoff.md](features/handoff.md) |

---

## Conventions

- **Planning a new feature?** Create `plans/<NAME>_PLAN.md` (+ `_TASKS.md` checklist). When the work ships, move both into `archive/` and record status in [EPICS_AND_STORIES.md](EPICS_AND_STORIES.md).
- **Editing reference docs?** Keep them evergreen. If a doc describes a decision that was reversed, either update it or move it to `archive/` — do not leave live docs contradicting shipped behavior.
