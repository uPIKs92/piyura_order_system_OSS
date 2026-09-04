# Feature Hand-off: 12 — Order Receipt Print (EPOS via browser)

## Goal

Print order receipt to thermal/EPOS printer (58mm/80mm) alongside existing invoice PDF. Invoice = formal customer doc; receipt = kitchen/counter thermal slip.

## Mechanism

Hybrid engine — `printReceipt` picks a path synchronously at click time. OS print dialog selects thermal printer; driver scales width.

- **Desktop window path** (regular desktop browser: not standalone PWA, not touch/mobile-like): `window.open('', '_blank', 'width=360,height=640')` fixed-size window (no `noopener`, so Chrome still returns the handle), write the receipt document, print on load + timeout fallback.
- **Print-zone path** (mobile browser, installed PWA / standalone display-mode, or popup blocked): inject a `#receipt-print-zone` div into the current page — off-screen on screen, print-media visibility rules, `@page { margin: 0; size: auto; }` — then call `window.print()` synchronously. Cleanup on `afterprint` + 60s safety timer.

The old hidden-iframe path was deleted entirely.

Rationale: server is cloud/multi-tenant, cannot reach tenant LAN printers. Zero new deps. Works mobile + desktop (incl. installed PWA). Universal across ESC/POS, Star, generic PCL printers.

## Files

| Layer | File |
|-------|------|
| Receipt builder + print | `resources/js/lib/receipt.ts` |
| UI button + payment-settings preload | `resources/js/pages/Orders.tsx` (detail drawer) |
| Branding source | `window.__APP_BRANDING__` via `getAppBranding()`; tenant from `useApi()` context |

## Layout (58mm default, 280px CSS)

- Header: tenant name (bold center), tagline, address, phone
- Rule `-----`
- Meta: `No: {invoice_no}` · `Tgl: {order_date}` · `Kasir: {user.name}`
- Customer: name + phone if present
- Items: `name`, `qty satuan x price`, subtotal (right)
- Totals: Subtotal, Diskon, PPN (if > 0), **Total**, Dibayar, Sisa (if > 0)
- Payment lines: `{metode} {amount}`
- Footer: `invoice_footer_text`, platform credit if `show_platform_credit_on_invoice`
- Inline `@media print`: margins 0, monospace font

## Builder split

`receipt.ts` exposes two pure builders:

- `buildReceiptFragment` — self-contained `<div class="receipt-print">`, all CSS scoped under `.receipt-print` (feeds the in-page print zone).
- `buildReceiptHtml` — full standalone document wrapper around the fragment (feeds the desktop popup window).

## Popup-gesture preload

`Orders.tsx` preloads payment settings on mount so the print click handler never awaits a fetch before `window.open` (a transient async hop loses the user gesture and triggers popup blockers). Cold-cache fallback routes to the print-zone path.

## Out of scope

- ESC/POS raw socket, ePOS-Print SDK, per-tenant printer IP config
- Auto-print on status change
- 80mm per-tenant toggle (default 58mm; printer driver scales)

## Tests

No JS unit runner in repo (Playwright = API/RBAC/layout only). `buildReceiptFragment` / `buildReceiptHtml` exported pure for future unit test. Manual verify: order detail → Cetak Struk → OS dialog → thermal printer, on desktop browser and mobile/PWA. Existing `InvoiceTest.php` untouched.
