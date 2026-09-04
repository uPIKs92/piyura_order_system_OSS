import { ArrowDown, ArrowUp } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { GroupedList, GroupedListDivider } from '@/components/ui/grouped-list';
import { IosListRow } from '@/components/ios/IosListRow';
import { StatusBadge } from '@/components/ios/StatusBadge';
import { formatCurrency } from '@/lib/format';
import type { ProductReportRow, StaffReport, StatusReport, TaxReport } from '@/lib/types';

export const MONTH_LABELS = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

export function percentShare(value: number, total: number): string {
    if (total <= 0) return '-';
    return `${((value / total) * 100).toFixed(1)}%`;
}

export type ProductSortKey = 'net_qty' | 'net_revenue' | 'net_profit';

export interface ProductSort {
    key: ProductSortKey;
    dir: 'asc' | 'desc';
}

const PRODUCT_SORT_ITEMS: { value: ProductSortKey; label: string }[] = [
    { value: 'net_qty', label: 'Terjual' },
    { value: 'net_revenue', label: 'Pendapatan' },
    { value: 'net_profit', label: 'Profit' },
];

export function DeltaChip({ delta }: { delta?: number | null }) {
    if (delta === null || delta === undefined) {
        return <span className="text-[10px] font-medium text-muted-foreground">Baru</span>;
    }

    return (
        <span className={`text-[10px] font-medium tabular-nums ${delta >= 0 ? 'text-emerald-600' : 'text-red-600'}`}>
            {delta >= 0 ? '+' : ''}{delta}%
        </span>
    );
}

export function StaffRankingList({ report }: { report: StaffReport }) {
    return (
        <div className="flex flex-col gap-3">
            <GroupedList>
                {(report.items ?? []).map((row, index) => (
                    <div key={row.staff_id}>
                        {index > 0 && <GroupedListDivider />}
                        <IosListRow
                            title={`${index + 1}. ${row.staff_name}`}
                            subtitle={`${row.total_orders} pesanan${row.unpaid_amount > 0 ? ` • Piutang ${formatCurrency(row.unpaid_amount)}` : ''} • AOV ${formatCurrency(row.aov)}`}
                            trailing={
                                <div className="flex flex-col items-end gap-1">
                                    <span className="text-sm font-semibold tabular-nums">{formatCurrency(row.total_revenue)}</span>
                                    <span className="text-xs text-muted-foreground tabular-nums">{percentShare(row.total_revenue, report.total_revenue)}</span>
                                </div>
                            }
                        />
                    </div>
                ))}
            </GroupedList>
            <p className="px-1 text-xs text-muted-foreground tabular-nums">
                Total {report.total_orders} pesanan • {formatCurrency(report.total_revenue)}
            </p>
        </div>
    );
}

export function ProductRankingList({ items, sort, onKeyChange, onToggleDir }: {
    items: ProductReportRow[];
    sort: ProductSort;
    onKeyChange: (key: ProductSortKey) => void;
    onToggleDir: () => void;
}) {
    return (
        <div className="flex flex-col gap-3">
            <div className="flex items-center gap-2">
                <Select value={sort.key} onValueChange={(v) => onKeyChange(v as ProductSortKey)} items={PRODUCT_SORT_ITEMS}>
                    <SelectTrigger className="w-40"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            {PRODUCT_SORT_ITEMS.map((item) => (
                                <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    aria-label={sort.dir === 'desc' ? 'Urutan menurun' : 'Urutan naik'}
                    title={sort.dir === 'desc' ? 'Urutan menurun' : 'Urutan naik'}
                    onClick={onToggleDir}
                >
                    {sort.dir === 'desc' ? <ArrowDown className="size-4" /> : <ArrowUp className="size-4" />}
                </Button>
            </div>
            <GroupedList>
                {items.map((row, index) => (
                    <div key={`${row.product_id}-${row.satuan}`}>
                        {index > 0 && <GroupedListDivider />}
                        <IosListRow
                            title={`${index + 1}. ${row.product_name}`}
                            subtitle={`${row.satuan} • ${row.total_orders} pesanan${row.returned_qty ? ` • Retur: ${row.returned_qty}` : ''}`}
                            trailing={
                                <div className="flex flex-col items-end gap-1">
                                    <span className="text-sm font-semibold tabular-nums">{formatCurrency(row.net_revenue ?? row.total_revenue)}</span>
                                    <DeltaChip delta={row.delta_revenue_pct} />
                                </div>
                            }
                        >
                            <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground tabular-nums">
                                <span>Terjual {row.net_qty ?? row.total_qty} <DeltaChip delta={row.delta_qty_pct} /></span>
                                <span>Profit {formatCurrency(row.net_profit)}</span>
                            </div>
                        </IosListRow>
                    </div>
                ))}
            </GroupedList>
        </div>
    );
}

export function StatusBreakdownList({ report }: { report: StatusReport }) {
    return (
        <div className="flex flex-col gap-3">
            <GroupedList>
                {report.statuses.map((row, index) => (
                    <div key={row.status}>
                        {index > 0 && <GroupedListDivider />}
                        <IosListRow
                            prefix={<StatusBadge status={row.status} />}
                            title={`${row.count} pesanan (${percentShare(row.count, report.totals.orders)})`}
                            subtitle={row.unpaid_count > 0 ? `${row.unpaid_count} belum bayar` : undefined}
                            trailing={<span className="text-sm font-semibold tabular-nums">{formatCurrency(row.total)}</span>}
                        />
                    </div>
                ))}
            </GroupedList>
            <p className="px-1 text-xs text-muted-foreground tabular-nums">
                Total {report.totals.orders} pesanan • {formatCurrency(report.statuses.reduce((acc, r) => acc + r.total, 0))} • Piutang {formatCurrency(report.statuses.reduce((acc, r) => acc + r.unpaid_amount, 0))}
            </p>
        </div>
    );
}

export function TaxMonthsList({ report, downloadingMonth, onDownload }: {
    report: TaxReport;
    downloadingMonth: number | null;
    onDownload: (month: number) => void;
}) {
    return (
        <div className="flex flex-col gap-3">
            <GroupedList>
                {report.months.map((row, index) => (
                    <div key={row.month}>
                        {index > 0 && <GroupedListDivider />}
                        <IosListRow
                            title={MONTH_LABELS[row.month - 1]}
                            subtitle={`${row.orders} transaksi • ${row.faktur_count} faktur`}
                            trailing={
                                <div className="flex flex-col items-end gap-1">
                                    <span className="text-xs text-muted-foreground">PPN</span>
                                    <span className="text-sm font-semibold tabular-nums">{formatCurrency(row.ppn)}</span>
                                </div>
                            }
                        >
                            <div className="flex items-center justify-between gap-3">
                                <p className="min-w-0 truncate text-xs text-muted-foreground tabular-nums">
                                    Omzet {formatCurrency(row.omzet)} • DPP {formatCurrency(row.dpp)} • PPh {formatCurrency(row.pph)}
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="shrink-0"
                                    disabled={row.faktur_count === 0 || downloadingMonth !== null}
                                    onClick={() => onDownload(row.month)}
                                >
                                    {downloadingMonth === row.month ? <Spinner data-icon="inline-start" /> : null}
                                    CSV
                                </Button>
                            </div>
                        </IosListRow>
                    </div>
                ))}
            </GroupedList>
        </div>
    );
}
