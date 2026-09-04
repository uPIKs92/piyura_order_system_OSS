# UMKM Plan — Buku Bon (Piutang) + Catatan Pengeluaran

**Status:** Selesai (diarsipkan). Implementasi lengkap di branch `feat/catalog-redesign`, commit `9afb442`..`7f1e393`.

**Persona:** small UMKM sellers (homemade snack, warung Madura, apotek, depot makan).
**Constraint:** stay a simple order system — no POS (no barcode/cash drawer/variants), no bookkeeping ERP.
**Scope locked with user:** two features only — (1) Buku bon / piutang per customer, placed in Pengguna → Pelanggan tab; (2) Catatan pengeluaran, placed in Laporan. Earlier candidates (WA share, antrian hari ini, salin pesanan, harga grosir, target penjualan, resi) were explicitly declined.

## What shipped

### Feature 1 — Catatan Pengeluaran (expenses)

- **Migration** `create_expenses_table`: `tenant_id` FK (cascade), `user_id` nullable FK (creator), `expense_date` date (indexed), `amount` decimal(12,2), `category` string(30), `note` nullable, timestamps; no soft deletes.
- **Enum** `App\Enums\ExpenseCategory`: `bahan_baku`, `kemasan`, `transport`, `operasional`, `gaji`, `lain_lain` + label Indonesia.
- **Model** `App\Models\Expense`: `BelongsToTenant`, activity log, casts (`expense_date: date`, `amount: decimal:2`, enum).
- **API** `ExpenseController` (owner-only `apiResource('expenses')` minus show): validation `amount > 0`, `expense_date <= today`, `category` in enum, `note <= 255`; index filter `from`/`to` (default today), order `expense_date desc, id desc`, paginate default 20 max 100; destroy = hard delete; **setiap mutasi memanggil `OrderService::bumpAggregatesVersion()`**.
- **Laporan**: `ReportService::daily()` menambah `net_profit`, `expenses_total`, `laba_bersih = net_profit − expenses_total`; `dailyTrend()` menambah per-hari + totals; query ekspenses di dalam `rememberAggregate` (ikut cache versi). Helper baru: `profitRows()` (ekstraksi query laba) & `expenseRows()`.
- **Lifecycle tenant**: `expenses` masuk `TenantExporter` dan `TenantImportResetService::resetBusinessData`.
- **Frontend**: tab `Pengeluaran` baru di Laporan (quick-add ≤5 detik: nominal + chip kategori + catatan opsional; daftar per tanggal; edit drawer; hapus AlertDialog); kartu Harian baru `Pengeluaran` + `Laba Bersih` (ditebalkan); `ProfitTrendChart` menambah garis `Laba Bersih`.

### Feature 2 — Buku Bon (piutang) in Pengguna → Pelanggan

- **API** `GET /api/receivables` (auth owner + staff): orders `grand_total > total_paid`, status bukan draft/cancelled, tenant aktif; dikelompokkan per telepon pelanggan (`customer_phone`, fallback nama — **kode tidak punya `customer_id` di orders**, pelanggan dicocokkan by phone seperti `Customer::upsertFromOrder`); respons `{ totals: {unpaid_amount, unpaid_count, debtor_count}, groups: [...] }` sorted `unpaid_amount` desc; aging bucket dari umum bon tertua: `<=7`, `8-14`, `15-30`, `>30`.
- **Quick-pay** memakai `POST /api/orders/{order}/payments` yang ada (PaymentPolicy menegakkan staff hanya boleh bayar pesanannya sendiri).
- **Frontend**: section `Buku Bon — Rp X (n pesanan, m pelanggan)` di atas panel Pelanggan (CollapsibleCard, terbuka bila ada bon, sembunyi bila nihil); baris debitur: nama, telepon, total bon, jumlah pesanan, badge umur bon; ketuk → sheet daftar pesanan belum lunas + form mini "Catat Bayar" (nominal default sisa, metode cash/transfer/qris); sukses → toast + haptic + refetch; pesanan lunas otomatis keluar daftar.

## Deviations from the original plan

- Original context claimed orders support `customer_id` — tidak ada di skema. Grup piutang memakai telepon/nama (lihat atas), plus `customer_id` opsional di respons dari pencocokan telepon ke tabel customers.
- Nama file doc: `UMKM_BON_PENGELUARAN_PLAN.md` (plan asli salah tulis "PENGELUARANG").

## Tests

- `tests/Feature/ExpenseApiTest.php` — CRUD, validasi, 403 staff, isolasi tenant, paginasi, invalidasi cache.
- `tests/Feature/ReportExpenseTest.php` — daily + trend memuat `expenses_total`/`laba_bersih`, matematika lintas hari, refresh tanpa cache basi, isolasi tenant.
- `tests/Feature/ReceivablesTest.php` — grup registered/ad-hoc, aging bucket, eksklusi draft/cancelled/lunas/overpaid/soft-delete, paritas dengan orders summary, staff boleh lihat, isolasi tenant.
- Lifecycle: `TenantExportTest`, `TenantFreshSheetsImportTest` diperbarui.

**Validation:** `php artisan test` 591 passing · `npx tsc --noEmit` green.

## Out of scope (declined by user — do NOT add)

WA share, antrian hari ini filter, salin pesanan, harga grosir, target penjualan, resi/kurir fields, public order tracking, multi-branch, credit-note overpayment ledger, POS anything.
