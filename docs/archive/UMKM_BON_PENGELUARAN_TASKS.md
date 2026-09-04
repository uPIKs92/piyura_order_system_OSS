# UMKM Bon & Pengeluaran — Task Breakdown

> Checklist eksekusi untuk `UMKM_BON_PENGELUARAN_PLAN.md`.
> Konvensi repo: satu commit per task, tests ikut commit-nya (commit selalu hijau).
> Validasi tiap task: `php artisan test` (+ `npx tsc --noEmit` untuk task frontend).

---

- [x] 1. Expenses migration + enum `ExpenseCategory` + model `Expense` (+ tenant FK validation green) — commit `9afb442`
- [x] 2. `ExpenseController` + routes owner-only + `ExpenseApiTest` (CRUD, validasi, 403 staff, isolasi tenant, paginasi, bump aggregates version) — commit `e6c8d98`
- [x] 3. `ReportService` integration (`daily` + `dailyTrend`: `expenses_total`, `laba_bersih`; helper `profitRows()`/`expenseRows()`) + `ReportExpenseTest` — commit `e8c2d08`
- [x] 4. Endpoint `GET /api/receivables` (grup telepon/nama, aging bucket, paritas math unpaid summary) + `ReceivablesTest` — commit `8ed6124`
- [x] 5. Frontend tab `Pengeluaran` + kartu Harian `Pengeluaran`/`Laba Bersih` + garis `Laba Bersih` di `ProfitTrendChart` + tipe `Expense` (tsc green) — commit `4876606`
- [x] 6. Frontend section `Buku Bon` di panel Pelanggan + sheet quick-pay `Catat Bayar` (tsc green) — commit `dc24b3d`
- [x] 7. Lifecycle tenant: `expenses` di `TenantExporter` + `TenantImportResetService` (+ tests) — commit `7f1e393`
- [x] 8. Docs: plan ini + tasks, update `docs/EPICS_AND_STORIES.md`, manual Bahasa Indonesia (`07-laporan.md`, `08-pengguna.md`, README manual) — diarsipkan ke `docs/archive/`

**Final validation:** `php artisan test` 591 passing · `npx tsc --noEmit` green.
