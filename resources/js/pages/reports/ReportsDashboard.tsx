import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardRow, CardTitle } from '@/components/ui/card';
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
import { formatCurrency, todayISO } from '@/lib/format';
import type { DailyReport, DailyTrendReport, Product, ProductReport, StaffReport, User } from '@/lib/types';
import { ProfitTrendChart } from '@/pages/reports/ProfitTrendChart';
import { IosPageHeader } from '@/components/ios/IosPageHeader';
import { Page } from '@/components/Page';

export default function ReportsDashboard() {
    const { api } = useApi();
    const [loading, setLoading] = useState(true);
    const [daily, setDaily] = useState<DailyReport>({ total_orders: 0, total_revenue: 0 });
    const [dailyTrend, setDailyTrend] = useState<DailyTrendReport | null>(null);
    const [trendLoading, setTrendLoading] = useState(false);
    const [byStatus, setByStatus] = useState<Record<string, number>>({});
    const [tax, setTax] = useState<{ year?: number; total_ppn?: number }>({});
    const [staffList, setStaffList] = useState<User[]>([]);
    const [products, setProducts] = useState<Product[]>([]);
    const [from, setFrom] = useState(() => {
        const d = new Date();
        d.setDate(d.getDate() - 30);
        return d.toISOString().slice(0, 10);
    });
    const [to, setTo] = useState(todayISO());
    const [staffId, setStaffId] = useState('all');
    const [productId, setProductId] = useState('all');
    const [staffReport, setStaffReport] = useState<StaffReport | null>(null);
    const [productReport, setProductReport] = useState<ProductReport | null>(null);
    const [activeTab, setActiveTab] = useState('daily');

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

    useEffect(() => {
        Promise.all([
            api.request<DailyReport>('/reports/daily'),
            api.request<Record<string, number>>('/reports/status'),
            api.request<{ year?: number; total_ppn?: number }>('/reports/tax'),
            api.list<User[]>('users').catch(() => []),
            api.list<Product[]>('products').catch(() => []),
        ])
            .then(([d, s, t, users, prods]) => {
                setDaily(d);
                setByStatus(s);
                setTax(t);
                setStaffList(users);
                setProducts(prods);
            })
            .catch((err) => toast.error(err instanceof Error ? err.message : 'Gagal memuat laporan'))
            .finally(() => setLoading(false));
    }, [api]);

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

    useEffect(() => {
        if (!loading) {
            void loadDailyTrend();
            void loadStaffReport();
            void loadProductReport();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [loading]);

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
            <IosPageHeader title="Laporan" description="Ringkasan bisnis dan performa penjualan" />
        <Tabs value={activeTab} onValueChange={setActiveTab} className="flex flex-col gap-4">
            <TabsList variant="segment">
                <TabsTrigger value="daily">Harian</TabsTrigger>
                <TabsTrigger value="staff">Staff</TabsTrigger>
                <TabsTrigger value="product">Produk</TabsTrigger>
                <TabsTrigger value="status">Status</TabsTrigger>
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
                            <p className="text-xs text-muted-foreground">Pendapatan Hari Ini</p>
                            <p className="text-xl font-bold tabular-nums">
                                {daily.total_revenue ? formatCurrency(daily.total_revenue) : '-'}
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
                            <ProfitTrendChart items={dailyTrend?.items ?? []} />
                        ) : null}
                    </CardContent>
                </Card>

                <Card className="bg-muted/50">
                    <CardContent className="flex flex-col gap-1">
                        <p className="text-xs text-muted-foreground">PPN {tax.year}</p>
                        <p className="text-lg font-bold tabular-nums">
                            {tax.total_ppn ? formatCurrency(tax.total_ppn) : '-'}
                        </p>
                    </CardContent>
                </Card>
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
                        <Card>
                            <CardContent className="flex flex-col gap-1 text-sm">
                                <p>Pesanan: <strong>{staffReport.total_orders}</strong></p>
                                <p>Pendapatan: <strong>{formatCurrency(staffReport.total_revenue)}</strong></p>
                                {staffReport.total_ppn != null && (
                                    <p>PPN: <strong>{formatCurrency(staffReport.total_ppn)}</strong></p>
                                )}
                            </CardContent>
                        </Card>
                        {staffReport.all && staffReport.items && staffReport.items.length > 0 && (
                            <Card>
                                {staffReport.items.map((row) => (
                                    <CardRow key={row.staff_id}>
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">{row.staff_name}</p>
                                            <p className="text-xs text-muted-foreground">{row.total_orders} pesanan</p>
                                        </div>
                                        <span className="shrink-0 font-medium">{formatCurrency(row.total_revenue)}</span>
                                    </CardRow>
                                ))}
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
                                <p>Pendapatan: <strong>{formatCurrency(productReport.total_revenue)}</strong></p>
                            </CardContent>
                        </Card>
                        {productReport.all && productReport.items && productReport.items.length > 0 && (
                            <Card>
                                {productReport.items.map((row) => (
                                    <CardRow key={row.product_id}>
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">{row.product_name}</p>
                                            <p className="text-xs text-muted-foreground">{row.total_orders} pesanan</p>
                                        </div>
                                        <span className="shrink-0 font-medium">{formatCurrency(row.total_revenue)}</span>
                                    </CardRow>
                                ))}
                            </Card>
                        )}
                        {productReport.all && productReport.items?.length === 0 && (
                            <p className="text-sm text-muted-foreground">Tidak ada data produk pada periode ini</p>
                        )}
                    </div>
                )}
            </TabsContent>

            <TabsContent value="status">
                <Card>
                    <CardHeader className="border-b">
                        <CardTitle>Order per Status</CardTitle>
                    </CardHeader>
                    {Object.entries(byStatus).map(([status, count]) => (
                        <CardRow key={status}>
                            <span className="capitalize">{status}</span>
                            <span className="font-medium">{count}</span>
                        </CardRow>
                    ))}
                    {Object.keys(byStatus).length === 0 && (
                        <CardContent>
                            <p className="text-sm text-muted-foreground">Tidak ada data</p>
                        </CardContent>
                    )}
                </Card>
            </TabsContent>
        </Tabs>
        </Page>
    );
}
