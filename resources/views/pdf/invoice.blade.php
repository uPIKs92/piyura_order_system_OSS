<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $order->invoice_no }}</title>
    <style>
        @page { margin: 0; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #111827;
            margin: 0;
            padding: 24px;
            background-color: #ffffff;
        }

        .lunas-stamp {
            position: fixed;
            top: 360px;
            left: 47px;
            width: 700px;
            text-align: center;
            font-size: 110px;
            font-weight: bold;
            letter-spacing: 0.25em;
            color: #15803d;
            opacity: 0.13;
            transform: rotate(-24deg);
        }

        .layout-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
        }
        .layout-table td {
            border: none;
            padding: 0;
            vertical-align: top;
        }

        .brand-logo {
            width: 40px;
            height: 40px;
        }
        .brand-name {
            font-size: 14px;
            font-weight: bold;
            color: #111827;
            margin: 0 0 3px 0;
        }
        .brand-line {
            font-size: 9.5px;
            color: #6b7280;
            margin: 0 0 2px 0;
        }

        .doc-title {
            font-size: 18px;
            font-weight: bold;
            color: #111827;
            letter-spacing: 0.05em;
            margin: 0 0 10px 0;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td {
            border: none;
            padding: 1px 0;
        }
        .meta-label {
            font-size: 9px;
            color: #6b7280;
            text-align: left;
            width: 45%;
        }
        .meta-value {
            font-size: 10.5px;
            color: #111827;
            text-align: right;
        }
        .meta-strong { font-weight: bold; }

        .rule-heavy {
            border-bottom: 1.5px solid #111827;
            height: 0;
            font-size: 0;
            line-height: 0;
            margin: 12px 0 0 0;
        }

        .bill-to { width: 60%; margin-top: 14px; }
        .bill-to-label {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            margin: 0 0 3px 0;
        }
        .bill-to-name {
            font-size: 11.5px;
            font-weight: bold;
            color: #111827;
            margin: 0 0 2px 0;
        }
        .bill-to-line {
            font-size: 9.5px;
            color: #6b7280;
            margin: 0 0 1px 0;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 18px 0 14px 0;
        }
        .invoice-table th {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #111827;
            padding: 6px 8px;
            border-top: 1.25px solid #111827;
            border-bottom: 0.75px solid #111827;
        }
        .invoice-table td {
            font-size: 10.5px;
            color: #111827;
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        .invoice-table .col-no { width: 5%; text-align: center; }
        .invoice-table .col-desc { width: 45%; text-align: left; }
        .invoice-table .col-qty { width: 10%; text-align: center; }
        .invoice-table .col-price { width: 20%; text-align: right; }
        .invoice-table .col-sub { width: 20%; text-align: right; padding-right: 0; }
        .invoice-table .edge-left { padding-left: 0; }
        .invoice-table td.col-no { color: #6b7280; }
        .invoice-table td.col-qty { font-weight: bold; }
        .invoice-table td.col-sub { font-weight: bold; }
        .item-name { font-weight: bold; }
        .item-micro {
            font-size: 8px;
            color: #6b7280;
            font-weight: normal;
            margin-top: 2px;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        .totals-table td {
            border: none;
            padding: 3px 0;
            font-size: 10.5px;
            color: #111827;
        }
        .totals-value { text-align: right; }
        .totals-total td {
            border-top: 3px double #111827;
            padding-top: 6px;
        }
        .totals-total-label { font-size: 11px; font-weight: bold; }
        .totals-total-value { font-size: 13px; font-weight: bold; text-align: right; }
        .totals-paid-value { font-weight: bold; }
        .totals-due-value { font-weight: bold; color: #b91c1c; }
        .totals-change-value { font-weight: bold; color: #15803d; }

        .payment-section {
            border-top: 0.75px solid #d1d5db;
            margin-top: 16px;
            padding-top: 10px;
            page-break-inside: avoid;
        }
        .section-lead {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            margin: 0 0 8px 0;
        }
        .pay-sub {
            font-size: 10px;
            font-weight: bold;
            color: #111827;
            margin: 0 0 4px 0;
        }
        .pay-line {
            font-size: 10px;
            color: #111827;
            margin: 0 0 3px 0;
        }
        .pay-account {
            font-size: 12px;
            font-weight: bold;
            color: #111827;
            margin: 0 0 3px 0;
        }
        .qr-caption {
            font-size: 9.5px;
            color: #6b7280;
            margin: 6px 0 0 0;
        }

        .invoice-footer {
            border-top: 0.75px solid #d1d5db;
            margin-top: 22px;
            padding-top: 10px;
            text-align: center;
        }
        .invoice-footer p {
            font-size: 9px;
            color: #6b7280;
            margin: 0 0 2px 0;
        }
        .platform-credit {
            font-size: 8px;
            color: #9ca3af;
            margin-top: 3px;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
<!-- Stamp "lunas": covers paid AND overpaid (remaining_amount <= 0), matching the lunas gate in MayarController, not exact-equality paymentStatus 'paid'. -->
@if ((float) $order->grand_total > 0 && (float) $order->remaining_amount <= 0)
    <div class="lunas-stamp">LUNAS</div>
@endif
@php
    $formatIdr = fn (float|int|string|null $amount): string => 'IDR '.number_format((float) $amount, 0, ',', '.');
    $bank = $bank ?? null;
    $qrisDataUri = $qrisDataUri ?? null;
    $qrisPendingNote = $qrisPendingNote ?? false;
    $hasBank = $bank && (! empty($bank['bank_name']) || ! empty($bank['bank_account_name']) || ! empty($bank['bank_account_number']));
    $hasQris = ! empty($qrisDataUri) || $qrisPendingNote;
    $showPayment = $hasBank || $hasQris;
@endphp

<table class="layout-table">
    <tr>
        <td style="width: 60%;">
            <table class="layout-table">
                <tr>
                    @if ($logoDataUri)
                        <td style="width: 50px; padding-top: 2px;"><img src="{{ $logoDataUri }}" alt="{{ $tenant?->name }}" class="brand-logo"></td>
                    @endif
                    <td>
                        <p class="brand-name">{{ $tenant?->name ?? 'Invoice' }}</p>
                        @if ($tenant?->tagline)
                            <p class="brand-line">{{ $tenant->tagline }}</p>
                        @endif
                        @if ($tenant?->address)
                            <p class="brand-line">{{ $tenant->address }}</p>
                        @endif
                        @if ($tenant?->phone || $tenant?->email)
                            <p class="brand-line">
                                @if ($tenant?->phone){{ $tenant->phone }}@endif
                                @if ($tenant?->phone && $tenant?->email) · @endif
                                @if ($tenant?->email){{ $tenant->email }}@endif
                            </p>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: 40%; text-align: right;">
            <p class="doc-title">INVOICE</p>
            <table class="meta-table">
                <tr>
                    <td class="meta-label">No. Nota</td>
                    <td class="meta-value meta-strong">#{{ $order->invoice_no }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Tanggal</td>
                    <td class="meta-value">{{ $order->order_date?->format('d M Y') }}</td>
                </tr>
                @if ($order->user?->name)
                    <tr>
                        <td class="meta-label">Kasir</td>
                        <td class="meta-value">{{ $order->user->name }}</td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>
<div class="rule-heavy"></div>

<div class="bill-to">
    <p class="bill-to-label">Diterbitkan Untuk Pelanggan</p>
    <p class="bill-to-name">{{ $order->customer_name ?? 'Customer' }}</p>
    @if ($order->customer_phone)
        <p class="bill-to-line">{{ $order->customer_phone }}</p>
    @endif
    @if ($order->customer_address)
        <p class="bill-to-line">{{ $order->customer_address }}</p>
    @endif
</div>

<table class="invoice-table">
    <thead>
        <tr>
            <th class="col-no edge-left">No.</th>
            <th class="col-desc">Deskripsi Item</th>
            <th class="col-qty">QTY</th>
            <th class="col-price">Harga Satuan</th>
            <th class="col-sub">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($order->items as $item)
            <tr>
                <td class="col-no edge-left">{{ $loop->iteration }}</td>
                <td class="col-desc item-name">
                    {{ $item->product_name }}
                    @foreach ($item->batchAllocations->filter(fn ($alloc) => $alloc->productBatch?->batch_no || $alloc->productBatch?->expired_at) as $alloc)
                        <div class="item-micro">
                            @if ($alloc->productBatch->batch_no)Batch {{ $alloc->productBatch->batch_no }}@endif
                            @if ($alloc->productBatch->batch_no && $alloc->productBatch->expired_at) · @endif
                            @if ($alloc->productBatch->expired_at)Exp {{ $alloc->productBatch->expired_at->format('d-m-Y') }}@endif
                        </div>
                    @endforeach
                </td>
                <td class="col-qty">{{ $item->quantity }} {{ $item->satuan ?? $item->productUnit?->satuan ?? 'pcs' }}</td>
                <td class="col-price">{{ $formatIdr($item->price_snapshot) }}</td>
                <td class="col-sub">{{ $formatIdr($item->subtotal) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="layout-table">
    <tr>
        <td></td>
        <td style="width: 46%;">
            <table class="totals-table">
                <tr>
                    <td>Subtotal</td>
                    <td class="totals-value">{{ $formatIdr($order->subtotal) }}</td>
                </tr>
                <tr>
                    <td>Diskon / Potongan</td>
                    <td class="totals-value">{{ $formatIdr($order->discount_total) }}</td>
                </tr>
                @if ((float) $order->ppn_amount > 0)
                    <tr>
                        <td>PPN ({{ number_format((float) $order->ppn_percentage, 0) }}%)</td>
                        <td class="totals-value">{{ $formatIdr($order->ppn_amount) }}</td>
                    </tr>
                @endif
                @if ((float) $order->delivery_fee > 0)
                    <tr>
                        <td>Ongkir</td>
                        <td class="totals-value">{{ $formatIdr($order->delivery_fee) }}</td>
                    </tr>
                @endif
                <tr class="totals-total">
                    <td class="totals-total-label">Total Akhir</td>
                    <td class="totals-total-value">{{ $formatIdr($order->grand_total) }}</td>
                </tr>
                @php
                    // Cash settlement with change: show the buyer's handed-over
                    // money (tendered), not the credited amount, so "Tunai"
                    // can't be confused with the total that should be paid.
                    // Legacy payments recorded without tendered fall back to
                    // their amount.
                    $buyerMoney = (float) $order->payments->sum(
                        fn ($p) => (float) $p->tendered > 0 ? (float) $p->tendered : (float) $p->amount,
                    );
                @endphp
                @if ((float) $order->change_amount > 0)
                    <tr>
                        <td>Tunai</td>
                        <td class="totals-value totals-paid-value">{{ $formatIdr($buyerMoney) }}</td>
                    </tr>
                    <tr>
                        <td>Kembalian</td>
                        <td class="totals-value totals-change-value">{{ $formatIdr($order->change_amount) }}</td>
                    </tr>
                @else
                    <tr>
                        <td>Dibayar</td>
                        <td class="totals-value totals-paid-value">{{ $formatIdr($order->total_paid) }}</td>
                    </tr>
                    @if ((float) $order->remaining_amount > 0)
                        <tr>
                            <td>Sisa Tagihan</td>
                            <td class="totals-value totals-due-value">{{ $formatIdr($order->remaining_amount) }}</td>
                        </tr>
                    @endif
                @endif
            </table>
        </td>
    </tr>
</table>

@if ($showPayment)
    <div class="payment-section">
        <p class="section-lead">Pembayaran</p>
        <table class="layout-table">
            <tr>
                @if ($hasBank)
                    <td style="width: {{ $hasQris ? '55%' : '100%' }};">
                        <p class="pay-sub">Transfer</p>
                        @if (! empty($bank['bank_name']))
                            <p class="pay-line">Bank: <strong>{{ $bank['bank_name'] }}</strong></p>
                        @endif
                        @if (! empty($bank['bank_account_number']))
                            <p class="pay-line">No. Rekening:</p>
                            <p class="pay-account">{{ $bank['bank_account_number'] }}</p>
                        @endif
                        @if (! empty($bank['bank_account_name']))
                            <p class="pay-line">Atas Nama: <strong>{{ $bank['bank_account_name'] }}</strong></p>
                        @endif
                    </td>
                @endif
                @if ($hasQris)
                    <td style="width: {{ $hasBank ? '45%' : '100%' }}; text-align: center;">
                        <p class="pay-sub">QRIS</p>
                        @if (! empty($qrisDataUri))
                            <img src="{{ $qrisDataUri }}" alt="QRIS" style="width: 150px; height: 150px;" />
                            <p class="qr-caption">Scan untuk membayar</p>
                        @else
                            <p class="qr-caption">Scan QRIS lewat aplikasi kasir atau hubungi toko</p>
                        @endif
                    </td>
                @endif
            </tr>
        </table>
    </div>
@endif

<div class="invoice-footer">
    @if ($tenant?->invoice_footer_text)
        <p>{{ $tenant->invoice_footer_text }}</p>
    @endif
    @if ($showPlatformCredit)
        <p class="platform-credit">Powered by {{ $platformName }} · {{ $appName }}</p>
    @endif
</div>
</body>
</html>
