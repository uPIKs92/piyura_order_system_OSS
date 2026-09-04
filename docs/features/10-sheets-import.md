# Feature Hand-off: 10 — Google Sheets Import

> **Direction:** Legacy workbook (Products + Orders tabs) → Laravel.  
> **Not** outbound reporting sync — see [06-google-sheets-sync.md](06-google-sheets-sync.md).

## Workbook format

One spreadsheet, **two tabs** (sheet index 0 = Products, 1 = Orders):

### Tab: Products

| Product Name | Unit | COGS | Selling Price |
|--------------|------|------|---------------|
| Omega Egg Negeri | pack | 28000 | 35000 |
| Omega Egg Negeri | krat | 80000 | 90000 |

- Identity = **`(nama, satuan)`** on `product_units` — one product row, many unit rows
- Skip rows with empty Selling Price

### Tab: Orders

| Date | Customer Name | Product Name | Qty | Unit | Status | Delivery |
|------|---------------|--------------|-----|------|--------|----------|
| 6/7/2026 0:00:00 | Dewi | Omega Egg Negeri | 1 | krat | lunas | Selesai |

| Sheet value | Laravel |
|-------------|---------|
| Delivery = Selesai + Status = lunas | `status = selesai`, full payment |
| Delivery = Selesai + Status = Pending | `status = dikirim`, unpaid |
| Status = lunas | Full payment (`cash`) |
| Product Name + Unit | Must exist in Products tab |

**Order:** Products first, then Orders.

## Code

| Piece | Location |
|-------|----------|
| Mapping | `config/import.php` → `sheets_legacy` |
| Service | `OrdersImportService` |
| Direct pull | `GoogleSheetsService` + `POST /api/integrations/google/import` |
| Template | `ImportTemplateService`, `GET /api/imports/template` |
| UI | Settings → Operasi |

## Option A — Direct from Google Sheets (preferred)

1. Owner connects Google in Settings → Operasi
2. Set Spreadsheet ID + Products/Orders tab names
3. Click **Import dari Google Sheets**

Requires OAuth connected + spreadsheet ID.

## Option B — Upload (fallback)

1. Download template Excel
2. Export Google Sheet as `.xlsx` or copy into template
3. Upload & Import (auto-detects `sheets_legacy`)

## Option C — CLI

```bash
php artisan orders:import export.xlsx --mapping=auto
php artisan orders:import export.xlsx --mapping=sheets_legacy
```

## Tests

- `SheetsLegacyImportTest`
- `GoogleSheetsImportTest`
- `ImportApiTest` (template download)

## Errors

Reports in `storage/app/imports/reports/import-*.json`.
