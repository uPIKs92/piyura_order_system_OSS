import { getAppBranding, safeLogoUrl } from '@/lib/branding';
import { formatCurrency } from '@/lib/format';
import type { AppBranding, Order, Payment, PaymentSettings, TenantBranding } from '@/lib/types';

/** Receipt width CSS: 58mm default. Printer driver scales to 80mm. */
const RECEIPT_WIDTH_PX = 280;

/** Print-zone element id; re-prints remove the previous zone first. */
const PRINT_ZONE_ID = 'receipt-print-zone';

/** Safety cleanup delay: print dialogs can stay open for minutes. */
const PRINT_ZONE_SAFETY_MS = 60_000;

function escapeHtml(s: string): string {
    return s
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function txt(v: unknown): string {
    return escapeHtml(String(v ?? ''));
}

function rule(): string {
    return '<div class="rule"></div>';
}

/** Flex row: label left, amount right. */
function row(label: string, amount: string | number, strong = false): string {
    const cls = strong ? 'row strong' : 'row';
    return `<div class="${cls}"><span>${txt(label)}</span><span>${txt(amount)}</span></div>`;
}

function paymentLineLabel(p: Payment): string {
    const m = (p.metode || '').toLowerCase();
    if (m === 'cash' || m === 'tunai') return 'Tunai';
    if (m === 'transfer' || m === 'bank') return 'Transfer';
    if (m === 'qris') return 'QRIS';
    if (m === 'card' || m === 'kartu' || m === 'debit') return 'Kartu';
    return p.metode || 'Bayar';
}

/** Indonesian provinces (unambiguous names/abbreviations only — bare
 * "Jakarta", "Yogyakarta", "Bengkulu" are kept because they commonly name
 * the city) matched against normalized comma-separated segments. */
const PROVINCES = new Set([
    'aceh', 'sumatera utara', 'sumut', 'sumatera barat', 'sumbar', 'riau',
    'kepulauan riau', 'kepri', 'jambi', 'sumatera selatan', 'sumsel',
    'kepulauan bangka belitung', 'bangka belitung', 'babel', 'lampung',
    'banten', 'dki jakarta', 'daerah khusus ibukota jakarta',
    'jawa barat', 'jabar', 'jawa tengah', 'jateng', 'jawa timur', 'jatim',
    'di yogyakarta', 'daerah istimewa yogyakarta', 'bali',
    'nusa tenggara barat', 'ntb', 'nusa tenggara timur', 'ntt',
    'kalimantan barat', 'kalbar', 'kalimantan tengah', 'kalteng',
    'kalimantan selatan', 'kalsel', 'kalimantan timur', 'kaltim',
    'kalimantan utara', 'kaltara', 'sulawesi utara', 'sulut',
    'sulawesi tengah', 'sulteng', 'sulawesi selatan', 'sulsel',
    'sulawesi tenggara', 'sultra', 'sulawesi barat', 'sulbar', 'gorontalo',
    'maluku', 'maluku utara', 'malut', 'papua', 'papua barat',
    'papua barat daya', 'papua tengah', 'papua selatan', 'papua pegunungan',
]);

const COUNTRIES = new Set(['indonesia', 'republik indonesia']);

const POSTAL_CODE = /\b\d{5}\b/;

function normalizeSegment(segment: string): string {
    return segment.toLowerCase().replace(/[.·]/g, '').replace(/\s+/g, ' ').trim();
}

/**
 * Trim a full address to "street … city + postal code" for the 58mm receipt
 * header: drop country and province segments (postal code survives even when
 * it sat on the dropped segment). Best-effort on free-text addresses.
 */
export function shortAddress(address: string): string {
    const kept: string[] = [];
    let droppedPostal = '';

    for (const raw of address.split(',')) {
        const segment = raw.trim();
        if (!segment) continue;

        // A segment that is only a postal code gets merged, not kept verbatim.
        const ownPostal = segment.match(POSTAL_CODE)?.[0] ?? '';
        if (/^\d{5}$/.test(normalizeSegment(segment))) {
            droppedPostal = ownPostal;
            continue;
        }

        const bare = normalizeSegment(segment)
            .replace(/^(provinsi|propinsi|prov)\s+/, '')
            .replace(/\b\d{5}\b/g, '')
            .trim();

        if (COUNTRIES.has(bare) || PROVINCES.has(bare)) {
            if (ownPostal) droppedPostal = ownPostal;
            continue;
        }

        kept.push(segment);
    }

    let result = kept.join(', ');
    if (droppedPostal && !POSTAL_CODE.test(result)) {
        result = result ? `${result} ${droppedPostal}` : droppedPostal;
    }
    return result;
}

/**
 * Receipt CSS, fully scoped under `.receipt-print` so the same fragment
 * renders identically in a standalone window and injected into the app
 * document (where Tailwind preflight applies to injected nodes).
 */
function receiptPrintCss(): string {
    return [
        '.receipt-print, .receipt-print * { box-sizing: border-box; }',
        `.receipt-print { width: ${RECEIPT_WIDTH_PX}px; font-family: "Courier New", "Menlo", monospace; font-size: 12px; line-height: 1.35; padding: 4px; background: #fff; color: #000; }`,
        '.receipt-print .center { text-align: center; }',
        '.receipt-print .name { font-weight: 700; font-size: 14px; }',
        '.receipt-print .muted { color: #000; opacity: 0.85; }',
        '.receipt-print .rule { border-top: 1px dashed #000; margin: 4px 0; }',
        '.receipt-print .row { display: flex; justify-content: space-between; gap: 6px; }',
        '.receipt-print .row.strong { font-weight: 700; font-size: 13px; }',
        '.receipt-print .item-name { font-weight: 700; }',
        '.receipt-print .item-sub { display: flex; justify-content: space-between; }',
        '.receipt-print .foot { text-align: center; margin-top: 6px; font-size: 11px; }',
        '.receipt-print img.logo { display: inline-block; max-width: 60px; max-height: 60px; object-fit: contain; }',
    ].join('\n');
}

/**
 * Build receipt body fragment (string): a self-contained
 * `<div class="receipt-print">` carrying its scoped styles. Pure,
 * side-effect-free, so it can be unit-tested later when a JS runner lands.
 */
export function buildReceiptFragment(
    order: Order,
    tenant: TenantBranding | null,
    app: AppBranding,
    paymentSettings?: PaymentSettings | null,
): string {
    const items = order.items ?? [];
    const payments = order.payments ?? [];

    const date = order.order_date ? order.order_date.slice(0, 10) : '-';
    const cashier = order.user?.name ?? '-';
    const ppnAmount = Number(order.ppn_amount ?? 0);
    const remaining = Number(order.remaining_amount ?? 0);
    const totalPaid = Number(order.total_paid ?? 0);
    const grandTotal = Number(order.grand_total ?? 0);
    const discountTotal = Number(order.discount_total ?? 0);
    const deliveryFee = Number(order.delivery_fee ?? 0);
    const changeAmount = Number(order.change_amount ?? order.change_due ?? 0);
    const isCash = (p: Payment) => ['cash', 'tunai'].includes((p.metode || '').toLowerCase());
    // Cash paid with money beyond what's owed: the handed-over cash and the
    // change fully describe the settlement, so they replace the "Dibayar"
    // row (which otherwise shows the credited amount and reads like the
    // buyer paid less than they handed over).
    const cashChangePayment = payments.find(
        (p) => isCash(p) && Number(p.tendered ?? 0) > Number(p.amount ?? 0),
    );
    const hasTransfer = payments.some((p) => (p.metode || '').toLowerCase() === 'transfer');
    const bank = paymentSettings ?? null;

    const headerLogo = safeLogoUrl(tenant?.logo_url);

    const parts: string[] = [];
    parts.push(`<style>${receiptPrintCss()}</style>`);

    // Header
    if (headerLogo) {
        parts.push(`<div class="center"><img class="logo" src="${txt(headerLogo)}" alt="logo"></div>`);
    }
    parts.push(`<div class="center name">${txt(tenant?.name ?? 'Toko')}</div>`);
    if (tenant?.tagline) parts.push(`<div class="center muted">${txt(tenant.tagline)}</div>`);
    if (tenant?.address) {
        const headerAddress = shortAddress(tenant.address);
        if (headerAddress) parts.push(`<div class="center muted">${txt(headerAddress)}</div>`);
    }
    if (tenant?.phone) parts.push(`<div class="center muted">${txt(tenant.phone)}</div>`);

    parts.push(rule());

    // Meta
    parts.push(`<div class="row"><span>No</span><span>${txt(order.invoice_no)}</span></div>`);
    parts.push(`<div class="row"><span>Tgl</span><span>${txt(date)}</span></div>`);
    parts.push(`<div class="row"><span>Kasir</span><span>${txt(cashier)}</span></div>`);
    if (order.customer_name) {
        parts.push(`<div class="row"><span>Pelanggan</span><span>${txt(order.customer_name)}</span></div>`);
    }
    if (order.customer_phone) {
        parts.push(`<div class="row"><span>Telp</span><span>${txt(order.customer_phone)}</span></div>`);
    }
    if (order.delivery_method === 'diantar' && order.customer_address) {
        parts.push(`<div class="row"><span>Alamat</span><span>${txt(order.customer_address)}</span></div>`);
    }

    parts.push(rule());

    // Items
    for (const it of items) {
        const qty = Number(it.quantity ?? 0);
        const price = Number(it.price_snapshot ?? 0);
        const sub = Number(it.subtotal ?? 0);
        const satuan = it.satuan ?? it.product_unit?.satuan ?? 'pcs';
        parts.push(`<div class="item-name">${txt(it.product_name ?? '-')}</div>`);
        parts.push(
            `<div class="item-sub"><span class="muted">${qty} ${txt(satuan)} x ${formatCurrency(price)}</span><span>${txt(formatCurrency(sub))}</span></div>`,
        );
    }

    parts.push(rule());

    // Totals
    parts.push(row('Subtotal', formatCurrency(Number(order.subtotal ?? 0))));
    if (discountTotal > 0) {
        parts.push(row('Diskon', formatCurrency(discountTotal)));
    }
    if (ppnAmount > 0) {
        const pct = Number(order.ppn_percentage ?? 0);
        parts.push(row(`PPN (${Math.round(pct)}%)`, formatCurrency(ppnAmount)));
    }
    if (deliveryFee > 0) {
        parts.push(row('Ongkir', formatCurrency(deliveryFee)));
    }
    parts.push(row('Total', formatCurrency(grandTotal), true));
    if (cashChangePayment) {
        // Separator groups the cash settlement (buyer money + change) apart
        // from the amount that should be paid.
        parts.push(rule());
        parts.push(row('Tunai', formatCurrency(Number(cashChangePayment.tendered))));
        if (remaining > 0) {
            parts.push(row('Sisa', formatCurrency(remaining)));
        }
        parts.push(row('Kembali', formatCurrency(changeAmount)));
    } else {
        parts.push(row('Dibayar', formatCurrency(totalPaid)));
        if (remaining > 0) {
            parts.push(row('Sisa', formatCurrency(remaining)));
        }
        if (changeAmount > 0) {
            parts.push(row('Kembali', formatCurrency(changeAmount)));
        }
    }

    // Payments (skip the overpay cash payment already shown as Tunai/Kembali)
    const listedPayments = cashChangePayment
        ? payments.filter((p) => p.id !== cashChangePayment.id)
        : payments;
    if (listedPayments.length > 0) {
        parts.push(rule());
        for (const p of listedPayments) {
            const amount = Number(p.amount ?? 0);
            const tendered = Number(p.tendered ?? 0);
            // For cash overpay the buyer's handed-over money is what prints
            // (e.g. Tunai 100.000), not the credited amount.
            parts.push(row(paymentLineLabel(p), formatCurrency(isCash(p) && tendered > amount ? tendered : amount)));
        }
    }

    // Bank details for transfer
    if (hasTransfer && bank?.bank_account_number) {
        parts.push(rule());
        parts.push('<div class="center" style="font-weight:700;">Transfer ke</div>');
        if (bank.bank_name) {
            parts.push(`<div class="center">${txt(bank.bank_name)}</div>`);
        }
        if (bank.bank_account_name) {
            parts.push(`<div class="center">${txt(bank.bank_account_name)}</div>`);
        }
        parts.push(`<div class="center" style="font-size:14px;font-weight:700;">${txt(bank.bank_account_number)}</div>`);
    }

    // Footer
    const footer: string[] = [];
    if (tenant?.invoice_footer_text) footer.push(txt(tenant.invoice_footer_text));
    if (app.show_platform_credit_on_invoice) {
        footer.push(txt(`Powered by ${app.platform_name} · ${app.app_name}`));
    }
    if (footer.length > 0) {
        parts.push(rule());
        parts.push(`<div class="foot">${footer.join('<br>')}</div>`);
    }

    return `<div class="receipt-print">\n${parts.join('\n')}\n</div>`;
}

/**
 * Build standalone receipt HTML document (string) by wrapping the receipt
 * fragment in a full document for the dedicated-window print path. Pure.
 */
export function buildReceiptHtml(
    order: Order,
    tenant: TenantBranding | null,
    app: AppBranding,
    paymentSettings?: PaymentSettings | null,
): string {
    const head: string[] = [];
    head.push('<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">');
    head.push('<meta name="viewport" content="width=device-width, initial-scale=1">');
    head.push(`<title>Struk ${txt(order.invoice_no)}</title>`);
    head.push('<style>');
    head.push([
        'html, body { margin: 0; padding: 0; background: #fff; color: #000; }',
        '@page { margin: 0; size: auto; }',
        '@media print { .receipt-print { width: auto; padding: 0; } }',
    ].join('\n'));
    head.push('</style></head><body>');
    return `${head.join('\n')}\n${buildReceiptFragment(order, tenant, app, paymentSettings)}\n</body></html>`;
}

/**
 * Inject the receipt fragment into an off-screen print zone in the current
 * document and print synchronously. The zone stays rendered off-screen
 * (display:none breaks printing in some browsers) and is removed on
 * afterprint plus a long safety timeout — print dialogs stay open longer
 * than short timers.
 */
function printReceiptInZone(fragment: string): void {
    // Idempotent re-prints: drop any zone left over from a previous print.
    document.getElementById(PRINT_ZONE_ID)?.remove();

    const zone = document.createElement('div');
    zone.id = PRINT_ZONE_ID;
    zone.setAttribute('aria-hidden', 'true');

    const style = document.createElement('style');
    style.textContent = [
        // Screen: kept rendered but off-screen.
        `#${PRINT_ZONE_ID} { position: fixed; left: -9999px; top: 0; }`,
        '@media print {',
        'body * { visibility: hidden !important; }',
        `#${PRINT_ZONE_ID}, #${PRINT_ZONE_ID} * { visibility: visible !important; }`,
        `#${PRINT_ZONE_ID} { position: absolute; left: 0; top: 0; width: 100%; background: #fff; }`,
        '@page { margin: 0; size: auto; }',
        '}',
    ].join('\n');
    zone.appendChild(style);

    const template = document.createElement('template');
    template.innerHTML = fragment;
    zone.appendChild(template.content);

    document.body.appendChild(zone);

    let safetyTimer = 0;
    const cleanup = () => {
        window.removeEventListener('afterprint', cleanup);
        clearTimeout(safetyTimer);
        zone.remove();
    };
    window.addEventListener('afterprint', cleanup);
    safetyTimer = window.setTimeout(cleanup, PRINT_ZONE_SAFETY_MS);

    // Synchronous, right after injection — no awaits.
    window.print();
}

/**
 * Print order receipt via a hybrid engine:
 * - Desktop browser tab: dedicated window (cleanest; OS dialog picks the
 *   thermal printer). Must run inside the click gesture, so callers must
 *   not await a fetch before calling.
 * - Mobile, standalone PWA, or blocked popup: off-screen print zone in the
 *   current document, then window.print().
 */
export function printReceipt(
    order: Order,
    tenant: TenantBranding | null,
    app: AppBranding = getAppBranding(),
    paymentSettings?: PaymentSettings | null,
): void {
    const isStandalone =
        window.matchMedia('(display-mode: standalone)').matches
        || (navigator as Navigator & { standalone?: boolean }).standalone === true;
    const isMobileLike =
        window.matchMedia('(pointer: coarse)').matches || navigator.maxTouchPoints > 0;

    if (!isStandalone && !isMobileLike) {
        // No noopener/noreferrer: Chrome only returns the window handle
        // (and lets us document.write it) without those features.
        const win = window.open('', '_blank', 'width=360,height=640');
        if (win) {
            const html = buildReceiptHtml(order, tenant, app, paymentSettings);
            win.document.open();
            win.document.write(html);
            win.document.close();
            win.focus();
            const trigger = () => {
                try {
                    win.print();
                } catch {
                    // Some embeds throw if not ready; user can Ctrl+P in window.
                }
            };
            win.addEventListener('load', trigger);
            // Fallback for browsers that skip load after document.write.
            setTimeout(trigger, 300);
            return;
        }
        // Popup blocked → fall through to the print-zone path.
    }

    printReceiptInZone(buildReceiptFragment(order, tenant, app, paymentSettings));
}
