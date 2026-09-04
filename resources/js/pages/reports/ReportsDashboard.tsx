import { lazy, Suspense, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useApi } from '@/lib/ApiProvider';
import { listAll } from '@/lib/listAll';
import { daysAgoISO, formatCurrency, todayISO } from '@/lib/format';
import type { DailyReport, DailyTrendReport, Product, ProductReport, PphMode, StaffReport, StatusReport, TaxReport, User } from '@/lib/types';
import { StatusBadge } from '@/components/ios/StatusBadge';
import { ORDER_PIPELINE } from '@/lib/orderStatus';
import { IosPageHeader } from '@/components/ios/IosPageHeader';
import { Page } from '@/components/Page';
import { useMediaQuery } from '@/lib/useMediaQuery';
import { DeltaChip, MONTH_LABELS, percentShare, ProductRankingList, StaffRankingList, StatusBreakdownList, TaxMonthsList } from '@/pages/reports/ReportMobileRows';
import { ExpensesPanel } from '@/pages/reports/ExpensesPanel';
import { BukuBonPanel } from '@/pages/reports/BukuBonPanel';
import type { ProductSort, ProductSortKey } from '@/pages/reports/ReportMobileRows';

const ProfitTrendChart = lazy(() => import('@/pages/reports/ProfitTrendChart').then((m) => ({ default: m.ProfitTrendChart })));

const PPH_MODE_LABELS: Record<PphMode, string> = {
    umkm_non_pkp: 'UMKM Non-PKP (0,5% / 12%)',
    umkm_pkp_22: 'UMKM PKP (PPh 22 2,5%)',
};

function buildYearOptions(): string[] {
    const current = new Date().getFullYear();
    return Array.from({ length: 6 }, (_, index) => String(current - index));
}

const taxYearItems = buildYearOptions().map((year) => ({ value: year, label: year }));

const TAX_TH = 'px-3 py-2.5 text-left text-xs font-medium tracking-wide uppercase text-muted-foreground';
const TAX_TD = 'px-3 py-2.5 whitespace-nowrap';

function SortableProductTh({ label, sortKey, sort, onToggle }: {
    label: string;
    sortKey: ProductSortKey;
    sort: ProductSort;
    onToggle: (key: ProductSortKey) => void;
}) {
    const active = sort.key === sortKey;

    return (
        <th scope="col" className={`${TAX_TH} text-right`}>
            <button
                type="button"
                onClick={() => onToggle(sortKey)}
                className={`inline-flex items-center gap-0.5 uppercase transition-colors hover:text-foreground ${active ? 'text-foreground' : ''}`}
            >
                {label}
                <span aria-hidden className="text-[10px]">{active ? (sort.dir === 'desc' ? '↓' : '↑') : '↕'}</span>
            </button>
        </th>
    );
}

function nonzeroStatusCounts(byStatus: Record<string, number>) {
    return [...ORDER_PIPELINE, 'cancelled']
        .map((status) => ({ status, count: byStatus[status] ?? 0 }))
        .filter((row) => row.count > 0);
}

export default function ReportsDashboard() {
    const { api } = useApi();
    const [loading, setLoading] = useState(true);
    const [printing, setPrinting] = useState(false);
    const [daily, setDaily] = useState<DailyReport>({ total_orders: 0, total_revenue: 0 });
    const [dailyTrend, setDailyTrend] = useState<DailyTrendReport | null>(null);
    const [trendLoading, setTrendLoading] = useState(false);
    const [statusReport, setStatusReport] = useState<StatusReport | null>(null);
    const [statusFrom, setStatusFrom] = useState('');
    const [statusTo, setStatusTo] = useState('');
    const [taxReport, setTaxReport] = useState<TaxReport | null>(null);
    const [taxYear, setTaxYear] = useState(String(new Date().getFullYear()));
    const [taxLoading, setTaxLoading] = useState(false);
    const [efakturMonth, setEfakturMonth] = useState<number | null>(null);
    const [staffList, setStaffList] = useState<User[]>([]);
    const [products, setProducts] = useState<Product[]>([]);
    const [from, setFrom] = useState(() => daysAgoISO(30));
    const [to, setTo] = useState(todayISO());
    const [staffId, setStaffId] = useState('all');
    const [productId, setProductId] = useState('all');
    const [staffReport, setStaffReport] = useState<StaffReport | null>(null);
    const [productReport, setProductReport] = useState<ProductReport | null>(null);
    const [productSort, setProductSort] = useState<ProductSort>({ key: 'net_qty', dir: 'desc' });
    const [activeTab, setActiveTab] = useState('daily');
    const [expenseDate, setExpenseDate] = useState(todayISO());
    const isDesktop = useMediaQuery('(min-width: 1024px)');

    const staffSelectItems = useMemo(
        () => [
            { value: 'all', label: 'Semua Staff' },
            ...staffList.map((u) => ({ value: String(u.id), label: u.name })),
        ],
        [staffList],
    );

    const productSelectItems = useMemo(
        () => [
            { value: 'all', label: 'Semua Produk' },
            ...products.map((p) => ({ value: String(p.id), label: p.nama })),
        ],
        [products],
    );

    const sortedProductItems = useMemo(() => {
        const items = productReport?.items ?? [];
        const dir = productSort.dir === 'desc' ? -1 : 1;
        return [...items].sort((a, b) => ((a[productSort.key] ?? 0) - (b[productSort.key] ?? 0)) * dir);
    }, [productReport, productSort]);

    function toggleProductSort(key: ProductSortKey) {
        setProductSort((prev) => (prev.key === key
            ? { key, dir: prev.dir === 'desc' ? 'asc' : 'desc' }
            : { key, dir: 'desc' }));
    }

    async function loadDailyTrend() {
        setTrendLoading(true);
        try {
            const data = await api.request<DailyTrendReport>(
                `/reports/daily-trend?from=${from}&to=${to}`,
            );
            setDailyTrend(data);
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memuat grafik harian');
        } finally {
            setTrendLoading(false);
        }
    }

    async function reloadDaily() {
        try {
            const data = await api.request<DailyReport>('/reports/daily');
            setDaily(data);
        } catch {
            // Silent: the daily card refreshes on next load anyway.
        }
    }

    useEffect(() => {
        Promise.all([
            api.request<DailyReport>('/reports/daily'),
            api.request<StatusReport>('/reports/status'),
            // Dropdowns list every staff/product; endpoints paginate, so walk
            // bounded pages.
            listAll<User>(api, 'users').catch(() => [] as User[]),
            listAll<Product>(api, 'products').catch(() => [] as Product[]),
        ])
            .then(([d, s, users, prods]) => {
                setDaily(d);
                setStatusReport(s);
                setStaffList(users);
                setProducts(prods);
            })
            .catch((err) => toast.error(err instanceof Error ? err.message : 'Gagal memuat laporan'))
            .finally(() => setLoading(false));
    }, [api]);

    async function loadStatusReport(from = statusFrom, to = statusTo) {
        try {
            const params = from && to ? `?from=${from}&to=${to}` : '';
            const data = await api.request<StatusReport>(`/reports/status${params}`);
            setStatusReport(data);
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memuat laporan status');
        }
    }

    async function loadStaffReport() {
        if (!staffId) return;
        try {
            const data = await api.request<StaffReport>(
                `/reports/staff?staff_id=${staffId}&from=${from}&to=${to}`,
            );
            setStaffReport(data);
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memuat laporan staff');
        }
    }

    async function loadProductReport() {
        if (!productId) return;
        try {
            const data = await api.request<ProductReport>(
                `/reports/product?product_id=${productId}&from=${from}&to=${to}`,
            );
            setProductReport(data);
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memuat laporan produk');
        }
    }

    async function loadTaxReport() {
        setTaxLoading(true);
        try {
            const data = await api.request<TaxReport>(`/reports/tax?year=${taxYear}`);
            setTaxReport(data);
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memuat laporan pajak');
        } finally {
            setTaxLoading(false);
        }
    }

    async function downloadEfaktur(month: number) {
        setEfakturMonth(month);
        try {
            const blob = await api.requestBlob(`/reports/efaktur?year=${taxYear}&month=${month}`);
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `efaktur-${taxYear}-${String(month).padStart(2, '0')}.csv`;
            a.click();
            URL.revokeObjectURL(url);
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal mengunduh e-Faktur');
        } finally {
            setEfakturMonth(null);
        }
    }

    async function printReport(download: boolean) {
        setPrinting(true);
        try {
            let query: string;
            if (activeTab === 'pengeluaran') {
                toast.info('Cetak tidak tersedia untuk tab Pengeluaran');
                return;
            } else if (activeTab === 'bon') {
                toast.info('Cetak tidak tersedia untuk tab Buku Bon');
                return;
            } else if (activeTab === 'pajak') {
                query = `type=tax&year=${taxYear}`;
            } else if (activeTab === 'status') {
                query = 'type=status';
                if (statusFrom && statusTo) query += `&from=${statusFrom}&to=${statusTo}`;
            } else {
                query = `type=${activeTab}&from=${from}&to=${to}`;
                if (activeTab === 'staff') query += `&staff_id=${staffId}`;
                if (activeTab === 'product') query += `&product_id=${productId}`;
            }
            const blob = await api.requestBlob(`/reports/print?${query}${download ? '&download=1' : ''}`);
            const url = URL.createObjectURL(blob);
            if (download) {
                const fileLabel = activeTab === 'pajak'
                    ? taxYear
                    : activeTab === 'status'
                        ? (statusFrom && statusTo ? `${statusFrom}_${statusTo}` : 'semua')
                        : `${from}_${to}`;
                const a = document.createElement('a');
                a.href = url;
                a.download = `laporan-${activeTab}-${fileLabel}.pdf`;
                a.click();
            } else {
                window.open(url, '_blank');
            }
            URL.revokeObjectURL(url);
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal mencetak laporan');
        } finally {
            setPrinting(false);
        }
    }

    useEffect(() => {
        if (!loading) {
            void loadDailyTrend();
            void loadStaffReport();
            void loadProductReport();
            void loadStatusReport();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [loading]);

    useEffect(() => {
        if (!loading) void loadTaxReport();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [loading, taxYear]);

    if (loading) {
        return (
            <Page>
                <IosPageHeader title="Laporan" description="Ringkasan bisnis dan performa penjualan" />
                <Skeleton className="h-8 w-48" />
                <div className="grid grid-cols-2 gap-3">
                    <Skeleton className="h-20" />
                    <Skeleton className="h-20" />
                </div>
            </Page>
        );
    }

    return (
        <Page>
            <IosPageHeader
                title="Laporan"
                description="Ringkasan bisnis dan performa penjualan"
                action={
                    <div className="flex flex-wrap gap-2">
                        <Button size="sm" onClick={() => printReport(false)} disabled={printing}>
                            {printing ? <Spinner data-icon="inline-start" /> : null}
                            Cetak
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => printReport(true)} disabled={printing}>
                            {printing ? <Spinner data-icon="inline-start" /> : null}
                            Unduh PDF
                        </Button>
                    </div>
                }
            />
        <Tabs value={activeTab} onValueChange={setActiveTab} className="flex flex-col gap-4">
            <TabsList variant="segment">
                <TabsTrigger value="daily">Harian</TabsTrigger>
                <TabsTrigger value="pengeluaran">Pengeluaran</TabsTrigger>
                <TabsTrigger value="bon">Buku Bon</TabsTrigger>
                <TabsTrigger value="staff">Staff</TabsTrigger>
                <TabsTrigger value="product">Produk</TabsTrigger>
                <TabsTrigger value="status">Status</TabsTrigger>
                <TabsTrigger value="pajak">Pajak</TabsTrigger>
            </TabsList>

            <TabsContent value="daily" className="flex flex-col gap-4">
                <div className="grid grid-cols-2 gap-3">
                    <Card>
                        <CardContent className="flex flex-col gap-1">
                            <p className="text-xs text-muted-foreground">Pesanan Hari Ini</p>
                            <p className="text-xl font-bold tabular-nums">{daily.total_orders ?? '-'}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex flex-col gap-1">
                            <p className="text-xs text-muted-foreground">Pendapatan Bersih</p>
                            <p className="text-xl font-bold tabular-nums">
                                {(daily.net_revenue ?? daily.total_revenue) ? formatCurrency(daily.net_revenue ?? daily.total_revenue) : '-'}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex flex-col gap-1">
                            <p className="text-xs text-muted-foreground">Retur</p>
                            <p className="text-xl font-bold tabular-nums">
                                {daily.returns_total ? formatCurrency(daily.returns_total) : '-'}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex flex-col gap-1">
                            <p className="text-xs text-muted-foreground">Pendapatan Kotor</p>
                            <p className="text-xl font-bold tabular-nums">
                                {daily.total_revenue ? formatCurrency(daily.total_revenue) : '-'}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex flex-col gap-1">
                            <p className="text-xs text-muted-foreground">Pengeluaran</p>
                            <p className="text-xl font-bold tabular-nums">
                                {daily.expenses_total != null ? formatCurrency(daily.expenses_total) : '-'}
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border-primary/40">
                        <CardContent className="flex flex-col gap-1">
                            <p className="text-xs text-muted-foreground">Laba Bersih</p>
                            <p className="text-xl font-bold tabular-nums text-primary">
                                {daily.laba_bersih != null ? formatCurrency(daily.laba_bersih) : '-'}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Tren Harian</CardTitle>
                        <CardDescription>
                            Sales turnover, purchasing cost (COGS), dan net profit per hari
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="grid grid-cols-3 gap-2 rounded-xl bg-muted/50 p-3">
                            <div className="min-w-0">
                                <p className="text-xs text-muted-foreground">Sales Turnover</p>
                                <p className="truncate text-sm font-semibold tabular-nums">
                                    {formatCurrency(dailyTrend?.totals.sales_turnover ?? 0)}
                                </p>
                            </div>
                            <div className="min-w-0">
                                <p className="text-xs text-muted-foreground">COGS</p>
                                <p className="truncate text-sm font-semibold tabular-nums">
                                    {formatCurrency(dailyTrend?.totals.purchasing_cost ?? 0)}
                                </p>
                            </div>
                            <div className="min-w-0">
                                <p className="text-xs text-muted-foreground">Net Profit</p>
                                <p className="truncate text-sm font-semibold tabular-nums">
                                    {formatCurrency(dailyTrend?.totals.net_profit ?? 0)}
                                </p>
                            </div>
                        </div>
                        <div className="grid grid-cols-3 gap-2 rounded-xl bg-muted/50 p-3">
                            <div className="min-w-0">
                                <p className="text-xs text-muted-foreground">Omzet Kotor</p>
                                <p className="truncate text-sm font-semibold tabular-nums">
                                    {formatCurrency(dailyTrend?.totals.gross_subtotal ?? 0)}
                                </p>
                            </div>
                            <div className="min-w-0">
                                <p className="text-xs text-muted-foreground">Diskon</p>
                                <p className="truncate text-sm font-semibold tabular-nums">
                                    {formatCurrency(dailyTrend?.totals.total_discount ?? 0)}
                                </p>
                            </div>
                            <div className="min-w-0">
                                <p className="text-xs text-muted-foreground">Rata-rata Pesanan</p>
                                <p className="truncate text-sm font-semibold tabular-nums">
                                    {formatCurrency(dailyTrend?.totals.aov ?? 0)}
                                </p>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-2 rounded-xl bg-muted/50 p-3">
                            <div className="min-w-0">
                                <p className="text-xs text-muted-foreground">Retur</p>
                                <p className="truncate text-sm font-semibold tabular-nums">
                                    {formatCurrency(dailyTrend?.totals.returns_total ?? 0)}
                                </p>
                            </div>
                            <div className="min-w-0">
                                <p className="text-xs text-muted-foreground">Pendapatan Bersih</p>
                                <p className="truncate text-sm font-semibold tabular-nums">
                                    {formatCurrency(dailyTrend?.totals.net_revenue ?? dailyTrend?.totals.total_revenue ?? 0)}
                                </p>
                            </div>
                            <div className="min-w-0">
                                <p className="text-xs text-muted-foreground">Pengeluaran</p>
                                <p className="truncate text-sm font-semibold tabular-nums">
                                    {formatCurrency(dailyTrend?.totals.expenses_total ?? 0)}
                                </p>
                            </div>
                            <div className="min-w-0">
                                <p className="text-xs text-muted-foreground">Laba Bersih</p>
                                <p className="truncate text-sm font-semibold tabular-nums text-primary">
                                    {formatCurrency(dailyTrend?.totals.laba_bersih ?? 0)}
                                </p>
                            </div>
                        </div>

                        <FieldGroup>
                            <div className="grid grid-cols-2 gap-2">
                                <Field>
                                    <FieldLabel>Dari</FieldLabel>
                                    <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
                                </Field>
                                <Field>
                                    <FieldLabel>Sampai</FieldLabel>
                                    <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
                                </Field>
                            </div>
                            <Button size="sm" onClick={loadDailyTrend} disabled={trendLoading}>
                                {trendLoading ? <Spinner data-icon="inline-start" /> : null}
                                {trendLoading ? 'Memuat...' : 'Perbarui Grafik'}
                            </Button>
                        </FieldGroup>

                        {trendLoading && !dailyTrend ? (
                            <Skeleton className="h-64 w-full" />
                        ) : activeTab === 'daily' ? (
                            <Suspense fallback={<Skeleton className="h-64 w-full" />}>
                                <ProfitTrendChart items={dailyTrend?.items ?? []} />
                            </Suspense>
                        ) : null}
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="pengeluaran" className="flex flex-col gap-4">
                <FieldGroup>
                    <Field>
                        <FieldLabel>Tanggal</FieldLabel>
                        <Input
                            type="date"
                            value={expenseDate}
                            max={todayISO()}
                            onChange={(e) => setExpenseDate(e.target.value)}
                        />
                    </Field>
                </FieldGroup>
                <ExpensesPanel date={expenseDate} onSaved={reloadDaily} />
            </TabsContent>

            <TabsContent value="bon" className="flex flex-col gap-4">
                <BukuBonPanel />
            </TabsContent>

            <TabsContent value="staff" className="flex flex-col gap-4">
                <FieldGroup>
                    <div className="grid grid-cols-2 gap-2">
                        <Field>
                            <FieldLabel>Dari</FieldLabel>
                            <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
                        </Field>
                        <Field>
                            <FieldLabel>Sampai</FieldLabel>
                            <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
                        </Field>
                    </div>
                    <Field>
                        <FieldLabel>Staff</FieldLabel>
                        <Select value={staffId} onValueChange={setStaffId} items={staffSelectItems}>
                            <SelectTrigger className="w-full"><SelectValue placeholder="Pilih staff" /></SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">Semua Staff</SelectItem>
                                    {staffList.map((u) => (
                                        <SelectItem key={u.id} value={String(u.id)}>{u.name}</SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </Field>
                    <Button size="sm" onClick={loadStaffReport}>Muat Laporan</Button>
                </FieldGroup>
                {staffReport && (
                    <div className="flex flex-col gap-3">
                        <div className="grid grid-cols-2 gap-3">
                            <Card>
                                <CardContent className="flex flex-col gap-1">
                                    <p className="text-xs text-muted-foreground">Pesanan</p>
                                    <p className="text-xl font-bold tabular-nums">{staffReport.total_orders}</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="flex flex-col gap-1">
                                    <p className="text-xs text-muted-foreground">Pendapatan</p>
                                    <p className="text-xl font-bold tabular-nums">{formatCurrency(staffReport.total_revenue)}</p>
                                    {staffReport.total_ppn != null && staffReport.total_ppn > 0 && (
                                        <p className="text-xs text-muted-foreground">PPN: {formatCurrency(staffReport.total_ppn)}</p>
                                    )}
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="flex flex-col gap-1">
                                    <p className="text-xs text-muted-foreground">Piutang</p>
                                    <p className="text-xl font-bold tabular-nums">{formatCurrency(staffReport.unpaid_amount)}</p>
                                    {staffReport.unpaid_count > 0 && (
                                        <p className="text-xs text-muted-foreground">{staffReport.unpaid_count} belum bayar</p>
                                    )}
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="flex flex-col gap-1">
                                    <p className="text-xs text-muted-foreground">Rata-rata Pesanan</p>
                                    <p className="text-xl font-bold tabular-nums">{formatCurrency(staffReport.aov)}</p>
                                </CardContent>
                            </Card>
                        </div>

                        {!staffReport.all && nonzeroStatusCounts(staffReport.by_status ?? {}).length > 0 && (
                            <Card>
                                <CardContent className="flex flex-wrap items-center gap-2">
                                    {nonzeroStatusCounts(staffReport.by_status ?? {}).map((row) => (
                                        <span key={row.status} className="flex items-center gap-1.5">
                                            <StatusBadge status={row.status} />
                                            <span className="text-sm tabular-nums text-muted-foreground">{row.count}</span>
                                        </span>
                                    ))}
                                </CardContent>
                            </Card>
                        )}

                        {staffReport.all && staffReport.items && staffReport.items.length > 0 && (
                            <Card>
                                <CardHeader className="border-b">
                                    <CardTitle>Peringkat Staff</CardTitle>
                                    <CardDescription>Diurutkan berdasarkan pendapatan tertinggi</CardDescription>
                                </CardHeader>
                                {isDesktop ? (
                                <div className="overflow-x-auto">
                                    <table className="w-full border-collapse text-sm">
                                        <thead>
                                            <tr className="border-b">
                                                <th scope="col" className={TAX_TH}>#</th>
                                                <th scope="col" className={TAX_TH}>Staff</th>
                                                <th scope="col" className={`${TAX_TH} text-right`}>Pesanan</th>
                                                <th scope="col" className={`${TAX_TH} text-right`}>Pendapatan</th>
                                                <th scope="col" className={`${TAX_TH} text-right`}>%</th>
                                                <th scope="col" className={`${TAX_TH} text-right`}>Piutang</th>
                                                <th scope="col" className={`${TAX_TH} text-right`}>AOV</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {staffReport.items.map((row, index) => (
                                                <tr key={row.staff_id} className="border-t">
                                                    <td className={`${TAX_TD} tabular-nums text-muted-foreground`}>{index + 1}</td>
                                                    <td className={TAX_TD}>{row.staff_name}</td>
                                                    <td className={`${TAX_TD} text-right tabular-nums`}>{row.total_orders}</td>
                                                    <td className={`${TAX_TD} text-right tabular-nums`}>{formatCurrency(row.total_revenue)}</td>
                                                    <td className={`${TAX_TD} text-right tabular-nums`}>{percentShare(row.total_revenue, staffReport.total_revenue)}</td>
                                                    <td className={`${TAX_TD} text-right tabular-nums`}>{formatCurrency(row.unpaid_amount)}</td>
                                                    <td className={`${TAX_TD} text-right tabular-nums`}>{formatCurrency(row.aov)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                        <tfoot>
                                            <tr className="border-t">
                                                <td className={TAX_TD}></td>
                                                <td className={`${TAX_TD} font-semibold`}>Total</td>
                                                <td className={`${TAX_TD} text-right font-semibold tabular-nums`}>{staffReport.total_orders}</td>
                                                <td className={`${TAX_TD} text-right font-semibold tabular-nums`}>{formatCurrency(staffReport.total_revenue)}</td>
                                                <td className={`${TAX_TD} text-right font-semibold tabular-nums`}>100%</td>
                                                <td className={`${TAX_TD} text-right font-semibold tabular-nums`}>{formatCurrency(staffReport.unpaid_amount)}</td>
                                                <td className={`${TAX_TD} text-right font-semibold tabular-nums`}>{formatCurrency(staffReport.aov)}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                ) : (
                                    <div className="px-3 pb-3">
                                        <StaffRankingList report={staffReport} />
                                    </div>
                                )}
                            </Card>
                        )}
                        {staffReport.all && staffReport.items?.length === 0 && (
                            <p className="text-sm text-muted-foreground">Tidak ada data staff pada periode ini</p>
                        )}
                    </div>
                )}
            </TabsContent>

            <TabsContent value="product" className="flex flex-col gap-4">
                <FieldGroup>
                    <div className="grid grid-cols-2 gap-2">
                        <Field>
                            <FieldLabel>Dari</FieldLabel>
                            <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
                        </Field>
                        <Field>
                            <FieldLabel>Sampai</FieldLabel>
                            <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
                        </Field>
                    </div>
                    <Field>
                        <FieldLabel>Produk</FieldLabel>
                        <Select value={productId} onValueChange={setProductId} items={productSelectItems}>
                            <SelectTrigger className="w-full"><SelectValue placeholder="Pilih produk" /></SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">Semua Produk</SelectItem>
                                    {products.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>{p.nama}</SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </Field>
                    <Button size="sm" onClick={loadProductReport}>Muat Laporan</Button>
                </FieldGroup>
                {productReport && (
                    <div className="flex flex-col gap-3">
                        <Card>
                            <CardContent className="flex flex-col gap-1 text-sm">
                                <p>Pesanan: <strong>{productReport.total_orders}</strong></p>
                                <p>Pendapatan Kotor: <strong>{formatCurrency(productReport.total_revenue)}</strong></p>
                                <p>Retur: <strong>{formatCurrency(productReport.returns_total ?? 0)}</strong> ({productReport.returned_qty ?? 0} item)</p>
                                <p>Pendapatan Bersih: <strong>{formatCurrency(productReport.net_revenue ?? productReport.total_revenue)}</strong></p>
                                <p>Terjual: <strong>{productReport.total_qty ?? 0}</strong></p>
                                <p>Terjual Bersih: <strong>{productReport.net_qty ?? productReport.total_qty ?? 0}</strong></p>
                                <p>COGS: <strong>{formatCurrency(productReport.total_cost ?? 0)}</strong></p>
                                <p>Profit Bersih: <strong>{formatCurrency(productReport.net_profit ?? 0)}</strong></p>
                            </CardContent>
                        </Card>
                        {productReport.all && productReport.items && productReport.items.length > 0 && (
                            <Card>
                                <CardHeader className="border-b">
                                    <CardTitle>Peringkat Produk</CardTitle>
                                    <CardDescription>
                                        Diurutkan berdasarkan penjualan terlaris; klik kolom untuk mengubah urutan
                                    </CardDescription>
                                </CardHeader>
                                {isDesktop ? (
                                <div className="overflow-x-auto">
                                    <table className="w-full border-collapse text-sm">
                                        <thead>
                                            <tr className="border-b">
                                                <th scope="col" className={TAX_TH}>#</th>
                                                <th scope="col" className={TAX_TH}>Produk</th>
                                                <SortableProductTh label="Terjual" sortKey="net_qty" sort={productSort} onToggle={toggleProductSort} />
                                                <SortableProductTh label="Pendapatan" sortKey="net_revenue" sort={productSort} onToggle={toggleProductSort} />
                                                <SortableProductTh label="Profit" sortKey="net_profit" sort={productSort} onToggle={toggleProductSort} />
                                                <th scope="col" className={`${TAX_TH} text-right`}>%</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {sortedProductItems.map((row, index) => (
                                                <tr key={`${row.product_id}-${row.satuan}`} className="border-t">
                                                    <td className={`${TAX_TD} tabular-nums text-muted-foreground`}>{index + 1}</td>
                                                    <td className={TAX_TD}>
                                                        <p className="font-medium">{row.product_name}</p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {row.satuan} • {row.total_orders} pesanan
                                                            {row.returned_qty ? ` • Retur: ${row.returned_qty}` : ''}
                                                        </p>
                                                    </td>
                                                    <td className={`${TAX_TD} text-right`}>
                                                        <div className="flex flex-col items-end">
                                                            <span className="tabular-nums">{row.net_qty ?? row.total_qty}</span>
                                                            <DeltaChip delta={row.delta_qty_pct} />
                                                        </div>
                                                    </td>
                                                    <td className={`${TAX_TD} text-right`}>
                                                        <div className="flex flex-col items-end">
                                                            <span className="tabular-nums">{formatCurrency(row.net_revenue ?? row.total_revenue)}</span>
                                                            <DeltaChip delta={row.delta_revenue_pct} />
                                                        </div>
                                                    </td>
                                                    <td className={`${TAX_TD} text-right tabular-nums`}>{formatCurrency(row.net_profit)}</td>
                                                    <td className={`${TAX_TD} text-right tabular-nums text-muted-foreground`}>
                                                        {percentShare(row.net_revenue ?? row.total_revenue, productReport.net_revenue ?? productReport.total_revenue)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                ) : (
                                    <div className="px-3 pb-3">
                                        <ProductRankingList
                                            items={sortedProductItems}
                                            sort={productSort}
                                            onKeyChange={(key) => setProductSort({ key, dir: 'desc' })}
                                            onToggleDir={() => setProductSort((prev) => ({ ...prev, dir: prev.dir === 'desc' ? 'asc' : 'desc' }))}
                                        />
                                    </div>
                                )}
                            </Card>
                        )}
                        {productReport.all && productReport.items?.length === 0 && (
                            <p className="text-sm text-muted-foreground">Tidak ada data produk pada periode ini</p>
                        )}
                    </div>
                )}
            </TabsContent>

            <TabsContent value="status" className="flex flex-col gap-4">
                <FieldGroup>
                    <div className="grid grid-cols-2 gap-2">
                        <Field>
                            <FieldLabel>Dari</FieldLabel>
                            <Input type="date" value={statusFrom} onChange={(e) => setStatusFrom(e.target.value)} />
                        </Field>
                        <Field>
                            <FieldLabel>Sampai</FieldLabel>
                            <Input type="date" value={statusTo} onChange={(e) => setStatusTo(e.target.value)} />
                        </Field>
                    </div>
                    <div className="flex gap-2">
                        <Button size="sm" onClick={() => void loadStatusReport()}>Muat Laporan</Button>
                        <Button size="sm" variant="outline" onClick={() => { setStatusFrom(''); setStatusTo(''); void loadStatusReport('', ''); }}>
                            Semua Waktu
                        </Button>
                    </div>
                </FieldGroup>

                <div className="grid grid-cols-2 gap-3">
                    <Card>
                        <CardContent className="flex flex-col gap-1">
                            <p className="text-xs text-muted-foreground">Pesanan Aktif</p>
                            <p className="text-xl font-bold tabular-nums">{statusReport?.totals.active_orders ?? '-'}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex flex-col gap-1">
                            <p className="text-xs text-muted-foreground">Nilai Aktif</p>
                            <p className="text-xl font-bold tabular-nums">
                                {statusReport ? formatCurrency(statusReport.totals.active_value) : '-'}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex flex-col gap-1">
                            <p className="text-xs text-muted-foreground">Piutang Total</p>
                            <p className="text-xl font-bold tabular-nums">
                                {statusReport ? formatCurrency(statusReport.totals.piutang_amount) : '-'}
                            </p>
                            {statusReport && statusReport.totals.piutang_count > 0 && (
                                <p className="text-xs text-muted-foreground">{statusReport.totals.piutang_count} belum bayar</p>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex flex-col gap-1">
                            <p className="text-xs text-muted-foreground">Total Pesanan</p>
                            <p className="text-xl font-bold tabular-nums">{statusReport?.totals.orders ?? '-'}</p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="border-b">
                        <CardTitle>Rincian per Status</CardTitle>
                        <CardDescription>
                            {statusReport?.period.from && statusReport?.period.to
                                ? `Periode ${statusReport.period.from} s/d ${statusReport.period.to}`
                                : 'Semua Waktu'}
                        </CardDescription>
                    </CardHeader>
                    {statusReport && statusReport.totals.orders > 0 ? (
                        isDesktop ? (
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-sm">
                                <thead>
                                    <tr className="border-b">
                                        <th scope="col" className={TAX_TH}>Status</th>
                                        <th scope="col" className={`${TAX_TH} text-right`}>Pesanan</th>
                                        <th scope="col" className={`${TAX_TH} text-right`}>%</th>
                                        <th scope="col" className={`${TAX_TH} text-right`}>Nilai Pesanan</th>
                                        <th scope="col" className={`${TAX_TH} text-right`}>Piutang</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {statusReport.statuses.map((row) => (
                                        <tr key={row.status} className="border-t">
                                            <td className={TAX_TD}><StatusBadge status={row.status} /></td>
                                            <td className={`${TAX_TD} text-right tabular-nums`}>{row.count}</td>
                                            <td className={`${TAX_TD} text-right tabular-nums`}>{percentShare(row.count, statusReport.totals.orders)}</td>
                                            <td className={`${TAX_TD} text-right tabular-nums`}>{formatCurrency(row.total)}</td>
                                            <td className={`${TAX_TD} text-right`}>
                                                <div className="flex flex-col">
                                                    <span className="tabular-nums">{formatCurrency(row.unpaid_amount)}</span>
                                                    {row.unpaid_count > 0 && (
                                                        <span className="text-xs text-muted-foreground">{row.unpaid_count} belum bayar</span>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr className="border-t">
                                        <td className={`${TAX_TD} font-semibold`}>Total</td>
                                        <td className={`${TAX_TD} text-right font-semibold tabular-nums`}>{statusReport.totals.orders}</td>
                                        <td className={`${TAX_TD} text-right font-semibold tabular-nums`}>100%</td>
                                        <td className={`${TAX_TD} text-right font-semibold tabular-nums`}>
                                            {formatCurrency(statusReport.statuses.reduce((acc, row) => acc + row.total, 0))}
                                        </td>
                                        <td className={`${TAX_TD} text-right font-semibold tabular-nums`}>
                                            {formatCurrency(statusReport.statuses.reduce((acc, row) => acc + row.unpaid_amount, 0))}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        ) : (
                        <div className="px-3 pb-3">
                            <StatusBreakdownList report={statusReport} />
                        </div>
                        )
                    ) : (
                        <CardContent>
                            <p className="text-sm text-muted-foreground">Tidak ada pesanan</p>
                        </CardContent>
                    )}
                </Card>
            </TabsContent>

            <TabsContent value="pajak" className="flex flex-col gap-4">
                <FieldGroup>
                    <Field>
                        <FieldLabel>Tahun Pajak</FieldLabel>
                        <Select value={taxYear} onValueChange={setTaxYear} items={taxYearItems}>
                            <SelectTrigger className="w-full"><SelectValue placeholder="Pilih tahun" /></SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {taxYearItems.map((y) => (
                                        <SelectItem key={y.value} value={y.value}>{y.label}</SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </Field>
                </FieldGroup>

                <div className="grid grid-cols-2 gap-3">
                    <Card>
                        <CardContent className="flex flex-col gap-1">
                            <p className="text-xs text-muted-foreground">Omzet {taxReport?.year ?? taxYear}</p>
                            <p className="text-xl font-bold tabular-nums">
                                {taxReport ? formatCurrency(taxReport.totals.omzet) : '-'}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex flex-col gap-1">
                            <p className="text-xs text-muted-foreground">DPP</p>
                            <p className="text-xl font-bold tabular-nums">
                                {taxReport ? formatCurrency(taxReport.totals.dpp) : '-'}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex flex-col gap-1">
                            <p className="text-xs text-muted-foreground">
                                PPN Keluaran{taxReport ? ` (${taxReport.ppn.percentage}%)` : ''}
                            </p>
                            <p className="text-xl font-bold tabular-nums">
                                {taxReport ? formatCurrency(taxReport.totals.ppn) : '-'}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex flex-col gap-1">
                            <p className="text-xs text-muted-foreground">
                                PPh Final{taxReport ? ` — ${PPH_MODE_LABELS[taxReport.pph.mode]}` : ''}
                            </p>
                            <p className="text-xl font-bold tabular-nums">
                                {taxReport ? formatCurrency(taxReport.pph.total) : '-'}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {taxLoading && !taxReport ? (
                    <Skeleton className="h-72 w-full" />
                ) : taxReport ? (
                    <Card>
                        <CardHeader className="border-b">
                            <CardTitle>Rekap Masa Pajak {taxReport.year}</CardTitle>
                            <CardDescription>
                                PPN keluaran dan PPh Final per masa pajak; tombol CSV mengunduh e-Faktur format impor DJP
                            </CardDescription>
                        </CardHeader>
                        {isDesktop ? (
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-sm">
                                <thead>
                                    <tr className="border-b">
                                        <th scope="col" className={TAX_TH}>Masa</th>
                                        <th scope="col" className={`${TAX_TH} text-right`}>Transaksi</th>
                                        <th scope="col" className={`${TAX_TH} text-right`}>Omzet</th>
                                        <th scope="col" className={`${TAX_TH} text-right`}>Faktur</th>
                                        <th scope="col" className={`${TAX_TH} text-right`}>DPP</th>
                                        <th scope="col" className={`${TAX_TH} text-right`}>PPN</th>
                                        <th scope="col" className={`${TAX_TH} text-right`}>PPh</th>
                                        <th scope="col" className={`${TAX_TH} text-right`}>e-Faktur</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {taxReport.months.map((row) => (
                                        <tr key={row.month} className="border-t">
                                            <td className={TAX_TD}>{MONTH_LABELS[row.month - 1]}</td>
                                            <td className={`${TAX_TD} text-right tabular-nums`}>{row.orders}</td>
                                            <td className={`${TAX_TD} text-right tabular-nums`}>{formatCurrency(row.omzet)}</td>
                                            <td className={`${TAX_TD} text-right tabular-nums`}>{row.faktur_count}</td>
                                            <td className={`${TAX_TD} text-right tabular-nums`}>{formatCurrency(row.dpp)}</td>
                                            <td className={`${TAX_TD} text-right tabular-nums`}>{formatCurrency(row.ppn)}</td>
                                            <td className={`${TAX_TD} text-right tabular-nums`}>{formatCurrency(row.pph)}</td>
                                            <td className={`${TAX_TD} text-right`}>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={row.faktur_count === 0 || efakturMonth !== null}
                                                    onClick={() => void downloadEfaktur(row.month)}
                                                >
                                                    {efakturMonth === row.month ? <Spinner data-icon="inline-start" /> : null}
                                                    CSV
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        ) : (
                            <div className="px-3 pb-3">
                                <TaxMonthsList
                                    report={taxReport}
                                    downloadingMonth={efakturMonth}
                                    onDownload={(month) => void downloadEfaktur(month)}
                                />
                            </div>
                        )}
                        {taxReport.totals.orders === 0 && (
                            <CardContent>
                                <p className="text-sm text-muted-foreground">Tidak ada data pajak pada tahun ini</p>
                            </CardContent>
                        )}
                    </Card>
                ) : null}
            </TabsContent>
        </Tabs>
        </Page>
    );
}
