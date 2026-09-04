<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 24px; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #111827;
            margin: 0;
            background-color: #ffffff;
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
            text-transform: uppercase;
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

        .section-lead {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            margin: 16px 0 8px 0;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 14px 0;
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
        .invoice-table .edge-left { padding-left: 0; }
        .empty-cell {
            text-align: center;
            color: #6b7280;
            font-style: italic;
        }
        .item-name { font-weight: bold; }
        .cell-bold { font-weight: bold; }
        .cell-note {
            font-size: 8px;
            font-weight: normal;
            color: #6b7280;
        }
        .status-chip-line {
            font-size: 10.5px;
            color: #111827;
            margin: 0;
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
        .totals-value { text-align: right; font-weight: bold; }
        .totals-total td {
            border-top: 3px double #111827;
            border-bottom: none;
            padding-top: 6px;
            font-weight: bold;
        }

        .payment-section {
            border-top: 0.75px solid #d1d5db;
            margin-top: 16px;
            padding-top: 10px;
            page-break-inside: avoid;
        }
        .tax-note {
            font-size: 9px;
            color: #6b7280;
            margin: 8px 0 0 0;
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
@php
    $formatIdr = fn (float|int|string|null $value): string => 'IDR '.number_format((float) $value, 0, ',', '.');
    $formatDate = fn (?string $date): string => $date ? \Illuminate\Support\Carbon::parse($date)->format('d M Y') : '-';
    $statusLabels = [
        'draft' => 'Draft',
        'pending' => 'Menunggu',
        'diproses' => 'Diproses',
        'dikirim' => 'Dikirim',
        'selesai' => 'Selesai',
        'cancelled' => 'Batal',
    ];
@endphp

<table class="layout-table">
    <tr>
        <td style="width: 60%;">
            <table class="layout-table">
                <tr>
                    @if (! empty($logoDataUri))
                        <td style="width: 50px; padding-top: 2px;"><img src="{{ $logoDataUri }}" alt="{{ $tenant?->name }}" class="brand-logo"></td>
                    @endif
                    <td>
                        <p class="brand-name">{{ $tenant?->name ?? 'Laporan' }}</p>
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
            <p class="doc-title">{{ $title }}</p>
            <table class="meta-table">
                <tr>
                    <td class="meta-label">Periode</td>
                    @if (($type ?? null) === 'status' && empty($statusData['period']['from']) && empty($statusData['period']['to']))
                        <td class="meta-value">Semua Waktu</td>
                    @else
                        <td class="meta-value">{{ $formatDate($from ?? null) }} &ndash; {{ $formatDate($to ?? null) }}</td>
                    @endif
                </tr>
                <tr>
                    <td class="meta-label">Dicetak</td>
                    <td class="meta-value">{{ ($printedAt ?? now())->format('d M Y H:i') }}</td>
                </tr>
                @if (! empty($staffName))
                    <tr>
                        <td class="meta-label">Staff</td>
                        <td class="meta-value">{{ $staffName }}</td>
                    </tr>
                @endif
                @if (! empty($productName))
                    <tr>
                        <td class="meta-label">Produk</td>
                        <td class="meta-value">{{ $productName }}</td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>
<div class="rule-heavy"></div>

@if (($type ?? null) === 'daily')
    @php
        $dailyData = $daily ?? [];
        $taxData = $tax ?? [];
        $trendData = $trend ?? [];
        $trendTotals = $trendData['totals'] ?? [];
        $trendItems = $trendData['items'] ?? [];
    @endphp

    <p class="section-lead">Ringkasan Hari Ini</p>
    <table class="layout-table">
        <tr>
            <td></td>
            <td style="width: 46%;">
                <table class="totals-table">
                    <tr>
                        <td>Pesanan Hari Ini</td>
                        <td class="totals-value">{{ $dailyData['total_orders'] ?? 0 }}</td>
                    </tr>
                    <tr>
                        <td>Pendapatan Bersih</td>
                        <td class="totals-value">{{ $formatIdr($dailyData['net_revenue'] ?? $dailyData['total_revenue'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td>PPN {{ $taxData['year'] ?? now()->year }}</td>
                        <td class="totals-value">{{ $formatIdr($taxData['totals']['ppn'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td>Pengembalian (Retur)</td>
                        <td class="totals-value">{{ $formatIdr($dailyData['returns_total'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td>Pendapatan Kotor (Hari Ini)</td>
                        <td class="totals-value">{{ $formatIdr($dailyData['total_revenue'] ?? 0) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p class="section-lead">Total Periode</p>
    <table class="layout-table">
        <tr>
            <td></td>
            <td style="width: 46%;">
                <table class="totals-table">
                    <tr>
                        <td>Omzet / Sales Turnover</td>
                        <td class="totals-value">{{ $formatIdr($trendTotals['sales_turnover'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td>COGS</td>
                        <td class="totals-value">{{ $formatIdr($trendTotals['purchasing_cost'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td>Profit Bersih</td>
                        <td class="totals-value">{{ $formatIdr($trendTotals['net_profit'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td>Omzet Kotor</td>
                        <td class="totals-value">{{ $formatIdr($trendTotals['gross_subtotal'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td>Diskon</td>
                        <td class="totals-value">{{ $formatIdr($trendTotals['total_discount'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td>Pengembalian (Retur)</td>
                        <td class="totals-value">{{ $formatIdr($trendTotals['returns_total'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td>Pendapatan Bersih</td>
                        <td class="totals-value">{{ $formatIdr($trendTotals['net_revenue'] ?? $trendTotals['total_revenue'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td>Rata-rata Pesanan (AOV)</td>
                        <td class="totals-value">{{ $formatIdr($trendTotals['aov'] ?? 0) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p class="section-lead">Rincian Per Hari</p>
    <table class="invoice-table">
        <thead>
            <tr>
                <th class="edge-left">Tanggal</th>
                <th class="text-right">Pesanan</th>
                <th class="text-right">Omzet</th>
                <th class="text-right">COGS</th>
                <th class="text-right">Profit Bersih</th>
            </tr>
        </thead>
        <tbody>
            @if (count($trendItems) > 0)
                @foreach ($trendItems as $item)
                    <tr>
                        <td class="edge-left">{{ $formatDate($item['date'] ?? null) }}</td>
                        <td class="text-right">{{ $item['total_orders'] ?? 0 }}</td>
                        <td class="text-right">{{ $formatIdr($item['total_revenue'] ?? 0) }}</td>
                        <td class="text-right">{{ $formatIdr($item['purchasing_cost'] ?? 0) }}</td>
                        <td class="text-right cell-bold">{{ $formatIdr($item['net_profit'] ?? 0) }}</td>
                    </tr>
                @endforeach
            @else
                <tr><td colspan="5" class="empty-cell">Tidak ada data pada periode ini</td></tr>
            @endif
        </tbody>
    </table>
@endif

@if (($type ?? null) === 'staff')
    @php
        $staffData = $staff ?? [];
        $staffItems = $staffData['items'] ?? [];
        $staffByStatus = $staffData['by_status'] ?? collect();
        $staffChips = [];
        foreach ($statusLabels as $chipStatus => $chipLabel) {
            $chipCount = (int) ($staffByStatus[$chipStatus] ?? 0);
            if ($chipCount > 0) {
                $staffChips[] = $chipLabel.' '.$chipCount;
            }
        }
    @endphp

    <p class="section-lead">Ringkasan</p>
    <table class="layout-table">
        <tr>
            <td></td>
            <td style="width: 46%;">
                <table class="totals-table">
                    <tr>
                        <td>Pesanan</td>
                        <td class="totals-value">{{ $staffData['total_orders'] ?? 0 }}</td>
                    </tr>
                    <tr>
                        <td>Pendapatan</td>
                        <td class="totals-value">{{ $formatIdr($staffData['total_revenue'] ?? 0) }}</td>
                    </tr>
                    @if (($staffData['total_ppn'] ?? 0) > 0)
                        <tr>
                            <td>PPN</td>
                            <td class="totals-value">{{ $formatIdr($staffData['total_ppn']) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td>Piutang</td>
                        <td class="totals-value">
                            {{ $formatIdr($staffData['unpaid_amount'] ?? 0) }}
                            @if (($staffData['unpaid_count'] ?? 0) > 0)
                                <div class="cell-note">{{ $staffData['unpaid_count'] }} belum bayar</div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>Rata-rata Pesanan (AOV)</td>
                        <td class="totals-value">{{ $formatIdr($staffData['aov'] ?? 0) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if (! empty($staffData['all']))
        <p class="section-lead">Rincian Per Staff</p>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th class="edge-left">No.</th>
                    <th>Staff</th>
                    <th class="text-right">Pesanan</th>
                    <th class="text-right">Pendapatan</th>
                    <th class="text-right">Piutang</th>
                    <th class="text-right">AOV</th>
                </tr>
            </thead>
            <tbody>
                @if (count($staffItems) > 0)
                    @foreach ($staffItems as $staffIndex => $item)
                        <tr>
                            <td class="edge-left">{{ $staffIndex + 1 }}.</td>
                            <td class="item-name">{{ $item['staff_name'] ?? 'Unknown' }}</td>
                            <td class="text-right">{{ $item['total_orders'] ?? 0 }}</td>
                            <td class="text-right">{{ $formatIdr($item['total_revenue'] ?? 0) }}</td>
                            <td class="text-right">
                                {{ $formatIdr($item['unpaid_amount'] ?? 0) }}
                                @if (($item['unpaid_count'] ?? 0) > 0)
                                    <div class="cell-note">{{ $item['unpaid_count'] }} belum bayar</div>
                                @endif
                            </td>
                            <td class="text-right">{{ $formatIdr($item['aov'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                @else
                    <tr><td colspan="6" class="empty-cell">Tidak ada data pada periode ini</td></tr>
                @endif
            </tbody>
            <tfoot>
                <tr class="totals-total">
                    <td>Total</td>
                    <td></td>
                    <td class="text-right">{{ $staffData['total_orders'] ?? 0 }}</td>
                    <td class="text-right">{{ $formatIdr($staffData['total_revenue'] ?? 0) }}</td>
                    <td class="text-right">{{ $formatIdr($staffData['unpaid_amount'] ?? 0) }}</td>
                    <td class="text-right">{{ $formatIdr($staffData['aov'] ?? 0) }}</td>
                </tr>
            </tfoot>
        </table>
    @elseif (count($staffChips) > 0)
        <p class="section-lead">Status Pesanan</p>
        <p class="status-chip-line">{{ implode(' &middot; ', $staffChips) }}</p>
    @endif
@endif

@if (($type ?? null) === 'product')
    @php
        $productData = $productData ?? [];
        $productItems = $productData['items'] ?? [];
    @endphp

    <p class="section-lead">Ringkasan</p>
    <table class="layout-table">
        <tr>
            <td></td>
            <td style="width: 46%;">
                <table class="totals-table">
                    <tr>
                        <td>Pesanan</td>
                        <td class="totals-value">{{ $productData['total_orders'] ?? 0 }}</td>
                    </tr>
                    <tr>
                        <td>Pendapatan Kotor</td>
                        <td class="totals-value">{{ $formatIdr($productData['total_revenue'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td>Terjual</td>
                        <td class="totals-value">{{ $productData['total_qty'] ?? 0 }}</td>
                    </tr>
                    <tr>
                        <td>COGS</td>
                        <td class="totals-value">{{ $formatIdr($productData['total_cost'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td>Profit Bersih</td>
                        <td class="totals-value">{{ $formatIdr($productData['net_profit'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td>Pendapatan Bersih</td>
                        <td class="totals-value">{{ $formatIdr($productData['net_revenue'] ?? $productData['total_revenue'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td>Pengembalian (Retur)</td>
                        <td class="totals-value">{{ $formatIdr($productData['returns_total'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td>Terjual Bersih</td>
                        <td class="totals-value">{{ $productData['net_qty'] ?? $productData['total_qty'] ?? 0 }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if (! empty($productData['all']))
        <p class="section-lead">Peringkat Produk (Terlaris)</p>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th class="edge-left">#</th>
                    <th>Produk (satuan)</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Retur</th>
                    <th class="text-right">Pesanan</th>
                    <th class="text-right">Pendapatan</th>
                    <th class="text-right">Profit</th>
                </tr>
            </thead>
            <tbody>
                @if (count($productItems) > 0)
                    @foreach ($productItems as $item)
                        <tr>
                            <td class="edge-left">{{ $loop->iteration + 1 }}</td>
                            <td class="item-name">{{ $item['product_name'] ?? '-' }} ({{ $item['satuan'] ?? '' }})</td>
                            <td class="text-right">{{ $item['total_qty'] ?? 0 }}</td>
                            <td class="text-right">{{ $item['returned_qty'] ?? 0 }}</td>
                            <td class="text-right">{{ $item['total_orders'] ?? 0 }}</td>
                            <td class="text-right">{{ $formatIdr($item['total_revenue'] ?? 0) }}</td>
                            <td class="text-right">{{ $formatIdr($item['net_profit'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                @else
                    <tr><td colspan="7" class="empty-cell">Tidak ada data pada periode ini</td></tr>
                @endif
            </tbody>
        </table>
    @endif
@endif

@if (($type ?? null) === 'status')
    @php
        $statusData = $statusData ?? [];
        $statusRows = $statusData['statuses'] ?? [];
        $statusTotals = $statusData['totals'] ?? [];
        $statusGrandTotal = (float) collect($statusRows)->sum('total');
    @endphp

    <p class="section-lead">Ringkasan</p>
    <table class="layout-table">
        <tr>
            <td></td>
            <td style="width: 46%;">
                <table class="totals-table">
                    <tr>
                        <td>Pesanan Aktif</td>
                        <td class="totals-value">{{ $statusTotals['active_orders'] ?? 0 }}</td>
                    </tr>
                    <tr>
                        <td>Nilai Aktif</td>
                        <td class="totals-value">{{ $formatIdr($statusTotals['active_value'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td>Piutang Total</td>
                        <td class="totals-value">
                            {{ $formatIdr($statusTotals['piutang_amount'] ?? 0) }}
                            @if (($statusTotals['piutang_count'] ?? 0) > 0)
                                <div class="cell-note">{{ $statusTotals['piutang_count'] }} belum bayar</div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p class="section-lead">Order per Status</p>
    <table class="invoice-table">
        <thead>
            <tr>
                <th class="edge-left">Status</th>
                <th class="text-right">Pesanan</th>
                <th class="text-right">%</th>
                <th class="text-right">Nilai Pesanan</th>
                <th class="text-right">Piutang</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($statusRows as $row)
                @php
                    $statusCount = (int) ($row['count'] ?? 0);
                    $statusShare = $statusCount > 0 && ($statusTotals['orders'] ?? 0) > 0
                        ? number_format($statusCount / $statusTotals['orders'] * 100, 1, ',', '.').'%'
                        : '-';
                @endphp
                <tr>
                    <td class="edge-left item-name">{{ $statusLabels[$row['status']] ?? ($row['status'] ?? '-') }}</td>
                    <td class="text-right">{{ $statusCount }}</td>
                    <td class="text-right">{{ $statusShare }}</td>
                    <td class="text-right">{{ $formatIdr($row['total'] ?? 0) }}</td>
                    <td class="text-right">
                        {{ $formatIdr($row['unpaid_amount'] ?? 0) }}
                        @if (($row['unpaid_count'] ?? 0) > 0)
                            <div class="cell-note">{{ $row['unpaid_count'] }} belum bayar</div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="totals-total">
                <td>Total</td>
                <td class="text-right">{{ $statusTotals['orders'] ?? 0 }}</td>
                <td class="text-right">{{ ($statusTotals['orders'] ?? 0) > 0 ? '100,0%' : '-' }}</td>
                <td class="text-right">{{ $formatIdr($statusGrandTotal) }}</td>
                <td class="text-right">{{ $formatIdr($statusTotals['piutang_amount'] ?? 0) }}</td>
            </tr>
        </tfoot>
    </table>
@endif

@if (($type ?? null) === 'tax')
    @php
        $taxReportData = $taxReport ?? [];
        $taxMonths = $taxReportData['months'] ?? [];
        $taxTotals = $taxReportData['totals'] ?? [];
        $taxPph = $taxReportData['pph'] ?? [];
        $masaNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $pphModeLabel = ($taxPph['mode'] ?? 'umkm_non_pkp') === \App\Support\PphUkm::MODE_PKP_22
            ? 'UMKM PKP PPh 22 2,5%'
            : 'UMKM Non-PKP 0,5%/12%';
    @endphp

    <p class="section-lead">Rekap Pajak {{ $taxReportData['year'] ?? '' }} per Masa</p>
    <table class="invoice-table">
        <thead>
            <tr>
                <th class="edge-left">Masa</th>
                <th class="text-right">Transaksi</th>
                <th class="text-right">Omzet</th>
                <th class="text-right">Faktur</th>
                <th class="text-right">DPP</th>
                <th class="text-right">PPN</th>
                <th class="text-right">PPh</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($taxMonths as $taxMonth)
                <tr>
                    <td class="edge-left">{{ $masaNames[(int) ($taxMonth['month'] ?? 0)] ?? ($taxMonth['month'] ?? '-') }}</td>
                    <td class="text-right">{{ $taxMonth['orders'] ?? 0 }}</td>
                    <td class="text-right">{{ $formatIdr($taxMonth['omzet'] ?? 0) }}</td>
                    <td class="text-right">{{ $taxMonth['faktur_count'] ?? 0 }}</td>
                    <td class="text-right">{{ $formatIdr($taxMonth['dpp'] ?? 0) }}</td>
                    <td class="text-right">{{ $formatIdr($taxMonth['ppn'] ?? 0) }}</td>
                    <td class="text-right">{{ $formatIdr($taxMonth['pph'] ?? 0) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="totals-total">
                <td>Total</td>
                <td class="text-right">{{ $taxTotals['orders'] ?? 0 }}</td>
                <td class="text-right">{{ $formatIdr($taxTotals['omzet'] ?? 0) }}</td>
                <td class="text-right">{{ $taxTotals['faktur_count'] ?? 0 }}</td>
                <td class="text-right">{{ $formatIdr($taxTotals['dpp'] ?? 0) }}</td>
                <td class="text-right">{{ $formatIdr($taxTotals['ppn'] ?? 0) }}</td>
                <td class="text-right">{{ $formatIdr($taxPph['total'] ?? 0) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="payment-section">
        <p class="section-lead">Informasi Pajak</p>
        <table class="layout-table">
            <tr>
                <td></td>
                <td style="width: 46%;">
                    <table class="totals-table">
                        <tr>
                            <td>Mode PPh</td>
                            <td class="totals-value">{{ $pphModeLabel }}</td>
                        </tr>
                        @if (! empty($npwp))
                            <tr>
                                <td>NPWP</td>
                                <td class="totals-value">{{ $npwp }}</td>
                            </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>
        <p class="tax-note">Catatan: rekap PPN keluaran saja (PPN masukan tidak termasuk). PPh masukan/kredit pajak dan PPh 21 di luar cakupan laporan ini.</p>
    </div>
@endif

<div class="invoice-footer">
    @if ($tenant?->invoice_footer_text)
        <p>{{ $tenant->invoice_footer_text }}</p>
    @endif
    @if (! empty($showPlatformCredit))
        <p class="platform-credit">Powered by {{ $platformName }} · {{ $appName }}</p>
    @endif
</div>
</body>
</html>
