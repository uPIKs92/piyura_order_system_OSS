import { useMemo, useState } from 'react';
import { SlidersHorizontal } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ResponsiveFormPanel } from '@/components/ResponsiveFormPanel';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Field, FieldLabel } from '@/components/ui/field';
import { cn } from '@/lib/utils';

interface OrderListToolbarProps {
    search: string;
    onSearchChange: (value: string) => void;
    statusFilter: string;
    onStatusChange: (value: string) => void;
    monthFilter: string;
    onMonthChange: (value: string) => void;
    yearFilter: string;
    onYearChange: (value: string) => void;
    sortFilter: string;
    onSortChange: (value: string) => void;
    statusCounts?: Record<string, number>;
}

const STATUS_FILTERS = ['', 'draft', 'pending', 'diproses', 'dikirim', 'selesai', 'cancelled'] as const;

const STATUS_FILTER_LABELS: Record<string, string> = {
    '': 'Semua',
    draft: 'Draft',
    pending: 'Menunggu',
    diproses: 'Diproses',
    dikirim: 'Dikirim',
    selesai: 'Selesai',
    cancelled: 'Batal',
};

const MONTH_LABELS = [
    { value: '1', label: 'Januari' },
    { value: '2', label: 'Februari' },
    { value: '3', label: 'Maret' },
    { value: '4', label: 'April' },
    { value: '5', label: 'Mei' },
    { value: '6', label: 'Juni' },
    { value: '7', label: 'Juli' },
    { value: '8', label: 'Agustus' },
    { value: '9', label: 'September' },
    { value: '10', label: 'Oktober' },
    { value: '11', label: 'November' },
    { value: '12', label: 'Desember' },
] as const;

const SORT_OPTIONS = [
    { value: 'date_desc', label: 'Tanggal terbaru' },
    { value: 'date_asc', label: 'Tanggal terlama' },
    { value: 'amount_desc', label: 'Total tertinggi' },
    { value: 'amount_asc', label: 'Total terendah' },
    { value: 'invoice_desc', label: 'Invoice terbaru' },
    { value: 'invoice_asc', label: 'Invoice terlama' },
] as const;

function currentMonthValue(): string {
    return String(new Date().getMonth() + 1);
}

function currentYearValue(): string {
    return String(new Date().getFullYear());
}

function buildYearOptions(): string[] {
    const current = new Date().getFullYear();
    return Array.from({ length: 6 }, (_, index) => String(current - index));
}

const monthSelectItems = [
    { value: '__all__', label: 'Semua bulan' },
    ...MONTH_LABELS.map((month) => ({ value: month.value, label: month.label })),
];

const sortSelectItems = SORT_OPTIONS.map((option) => ({ value: option.value, label: option.label }));

export function OrderListToolbar({
    search,
    onSearchChange,
    statusFilter,
    onStatusChange,
    monthFilter,
    onMonthChange,
    yearFilter,
    onYearChange,
    sortFilter,
    onSortChange,
    statusCounts,
}: OrderListToolbarProps) {
    const [open, setOpen] = useState(false);

    const yearSelectItems = useMemo(
        () => [
            { value: '__all__', label: 'Semua tahun' },
            ...buildYearOptions().map((year) => ({ value: year, label: year })),
        ],
        [],
    );

    const activeFilterCount =
        (monthFilter !== currentMonthValue() ? 1 : 0) +
        (yearFilter !== currentYearValue() ? 1 : 0) +
        (sortFilter !== 'date_desc' ? 1 : 0);

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-center gap-2">
                <Input
                    className="flex-1"
                    placeholder="Cari invoice / customer..."
                    value={search}
                    onChange={(e) => onSearchChange(e.target.value)}
                />
                <Button variant="outline" size="sm" onClick={() => setOpen(true)}>
                    <SlidersHorizontal data-icon="inline-start" />
                    Filter
                    {activeFilterCount > 0 && (
                        <span className="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-xs font-semibold text-primary-foreground">
                            {activeFilterCount}
                        </span>
                    )}
                </Button>
                <ResponsiveFormPanel
                    open={open}
                    onOpenChange={setOpen}
                    title="Filter"
                    footer={
                        <div className="flex w-full gap-2">
                            <Button
                                variant="outline"
                                className="flex-1"
                                onClick={() => {
                                    onMonthChange('');
                                    onYearChange('');
                                    onSortChange('date_desc');
                                }}
                            >
                                Reset
                            </Button>
                            <Button className="flex-1" onClick={() => setOpen(false)}>Terapkan</Button>
                        </div>
                    }
                >
                    <div className="flex flex-col gap-4">
                        <Field>
                            <FieldLabel>Bulan</FieldLabel>
                            <Select
                                value={monthFilter || '__all__'}
                                onValueChange={(value) => onMonthChange(value === '__all__' ? '' : value)}
                                items={monthSelectItems}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Bulan" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="__all__">Semua bulan</SelectItem>
                                        {MONTH_LABELS.map((month) => (
                                            <SelectItem key={month.value} value={month.value}>
                                                {month.label}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field>
                            <FieldLabel>Tahun</FieldLabel>
                            <Select
                                value={yearFilter || '__all__'}
                                onValueChange={(value) => onYearChange(value === '__all__' ? '' : value)}
                                items={yearSelectItems}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Tahun" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="__all__">Semua tahun</SelectItem>
                                        {buildYearOptions().map((year) => (
                                            <SelectItem key={year} value={year}>
                                                {year}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field>
                            <FieldLabel>Urutkan</FieldLabel>
                            <Select
                                value={sortFilter}
                                onValueChange={(value) => onSortChange(value)}
                                items={sortSelectItems}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Urutkan" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {SORT_OPTIONS.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </Field>
                    </div>
                </ResponsiveFormPanel>
            </div>
            <div className="flex flex-wrap gap-1.5">
                {STATUS_FILTERS.map((s) => {
                    const isActive = s === statusFilter;
                    const count = statusCounts?.[s] ?? 0;
                    return (
                        <button
                            key={s || 'all'}
                            type="button"
                            className={cn(
                                'inline-flex h-9 items-center rounded-full px-3 text-xs font-medium capitalize transition-colors',
                                isActive
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80',
                            )}
                            onClick={() => onStatusChange(s)}
                        >
                            {STATUS_FILTER_LABELS[s] || s}
                            {count > 0 && <span className="ml-1.5 text-[11px] opacity-70">{count}</span>}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
