<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $order->invoice_no }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #334155;
            margin: 0;
            padding: 20px;
            background-color: #ffffff;
        }
        .invoice-card {
            max-width: 700px;
            margin: 10px auto;
            background: #ffffff;
            padding: 20px;
        }

        .layout-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
            margin-bottom: 20px;
        }
        .layout-table td {
            border: none;
            padding: 0;
        }

        .brand-logo {
            width: 56px;
            height: 56px;
            object-fit: contain;
            border-radius: 8px;
            margin-bottom: 8px;
        }
        .brand-title {
            font-size: 26px;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 5px 0;
        }
        .brand-subtitle {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }
        .invoice-badge {
            display: inline-block;
            background: #f1f5f9;
            color: #1e293b;
            font-size: 11px;
            font-weight: bold;
            padding: 5px 12px;
            border-radius: 50px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .meta-text {
            font-size: 13px;
            color: #334155;
            margin: 4px 0;
        }

        .info-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 15px;
            margin-top: 15px;
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
        }
        .info-title {
            font-size: 11px;
            text-transform: uppercase;
            color: #94a3b8;
            font-weight: bold;
            margin: 0 0 5px 0;
        }
        .info-value {
            font-size: 15px;
            color: #0f172a;
            font-weight: bold;
            margin: 0;
        }
        .info-detail {
            font-size: 12px;
            color: #64748b;
            margin: 4px 0 0 0;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .invoice-table th {
            background: #0f172a;
            color: #ffffff;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 12px 14px;
        }
        .invoice-table td {
            padding: 14px;
            font-size: 13px;
            color: #334155;
            border-bottom: 1px solid #e2e8f0;
        }
        .item-name {
            font-weight: bold;
            color: #0f172a;
        }

        .summary-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 15px;
            border: 1px solid #e2e8f0;
        }
        .summary-row-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-row-table td {
            padding: 5px 0;
            font-size: 13px;
            color: #64748b;
        }
        .grand-total-border {
            border-top: 2px dashed #cbd5e1;
            padding-top: 10px !important;
            margin-top: 5px;
        }
        .total-label {
            font-size: 14px !important;
            color: #0f172a !important;
            font-weight: bold;
        }
        .total-value {
            font-size: 18px !important;
            color: #059669 !important;
            font-weight: bold;
        }

        .invoice-footer {
            text-align: center;
            margin-top: 40px;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
            padding-top: 15px;
        }
        .platform-credit {
            font-size: 10px;
            margin-top: 5px;
            color: #cbd5e1;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
@php
    $formatIdr = fn (float|int|string|null $amount): string => 'IDR '.number_format((float) $amount, 0, ',', '.');
@endphp

<div class="invoice-card">
    <table class="layout-table">
        <tr>
            <td style="width: 50%; text-align: left; vertical-align: top;">
                @if ($logoDataUri)
                    <img src="{{ $logoDataUri }}" alt="{{ $tenant?->name }}" class="brand-logo">
                @endif
                <h1 class="brand-title">{{ $tenant?->name ?? 'Invoice' }}</h1>
                @if ($tenant?->tagline)
                    <p class="brand-subtitle">{{ $tenant->tagline }}</p>
                @endif
                @if ($tenant?->address)
                    <p class="brand-subtitle" style="margin-top: 4px;">{{ $tenant->address }}</p>
                @endif
                @if ($tenant?->phone || $tenant?->email)
                    <p class="brand-subtitle" style="margin-top: 4px;">
                        @if ($tenant?->phone){{ $tenant->phone }}@endif
                        @if ($tenant?->phone && $tenant?->email) · @endif
                        @if ($tenant?->email){{ $tenant->email }}@endif
                    </p>
                @endif
            </td>
            <td style="width: 50%; text-align: right; vertical-align: top;">
                <span class="invoice-badge">Official Receipt</span>
                <p class="meta-text"><strong>No. Nota:</strong> #{{ $order->invoice_no }}</p>
                <p class="meta-text"><strong>Tanggal:</strong> {{ $order->order_date?->format('d M Y') }}</p>
            </td>
        </tr>
    </table>

    <div class="info-box">
        <p class="info-title">Diterbitkan Untuk Pelanggan</p>
        <h3 class="info-value">{{ $order->customer_name ?? 'Customer' }}</h3>
        @if ($order->customer_phone)
            <p class="info-detail">{{ $order->customer_phone }}</p>
        @endif
        @if ($order->customer_address)
            <p class="info-detail">{{ $order->customer_address }}</p>
        @endif
    </div>

    <table class="invoice-table">
        <thead>
            <tr>
                <th style="text-align: left; width: 40%;">Deskripsi Item</th>
                <th class="text-center" style="width: 15%;">QTY</th>
                <th class="text-right" style="width: 20%;">Harga Satuan</th>
                <th class="text-right" style="width: 25%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td class="item-name">{{ $item->product_name }}</td>
                    <td class="text-center" style="font-weight: bold;">
                        {{ $item->quantity }} {{ $item->satuan ?? $item->productUnit?->satuan ?? 'pcs' }}
                    </td>
                    <td class="text-right">{{ $formatIdr($item->price_snapshot) }}</td>
                    <td class="text-right" style="font-weight: bold; color: #0f172a;">{{ $formatIdr($item->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="layout-table">
        <tr>
            <td style="width: 55%;"></td>
            <td style="width: 45%; vertical-align: top;">
                <div class="summary-box">
                    <table class="summary-row-table">
                        <tr>
                            <td>Subtotal</td>
                            <td class="text-right" style="color: #1e293b; font-weight: bold;">{{ $formatIdr($order->subtotal) }}</td>
                        </tr>
                        <tr>
                            <td>Diskon / Potongan</td>
                            <td class="text-right" style="color: #94a3b8;">{{ $formatIdr($order->discount_total) }}</td>
                        </tr>
                        @if ((float) $order->ppn_amount > 0)
                            <tr>
                                <td>PPN ({{ number_format((float) $order->ppn_percentage, 0) }}%)</td>
                                <td class="text-right" style="color: #1e293b; font-weight: bold;">{{ $formatIdr($order->ppn_amount) }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td class="grand-total-border total-label">Total Akhir</td>
                            <td class="text-right grand-total-border total-value">{{ $formatIdr($order->grand_total) }}</td>
                        </tr>
                        <tr>
                            <td>Dibayar</td>
                            <td class="text-right" style="color: #1e293b; font-weight: bold;">{{ $formatIdr($order->total_paid) }}</td>
                        </tr>
                        @if ((float) $order->remaining_amount > 0)
                            <tr>
                                <td>Sisa Tagihan</td>
                                <td class="text-right" style="color: #dc2626; font-weight: bold;">{{ $formatIdr($order->remaining_amount) }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="invoice-footer">
        @if ($tenant?->invoice_footer_text)
            <p>{{ $tenant->invoice_footer_text }}</p>
        @endif
        @if ($showPlatformCredit)
            <p class="platform-credit">Powered by {{ $platformName }} · {{ $appName }}</p>
        @endif
    </div>
</div>
</body>
</html>
