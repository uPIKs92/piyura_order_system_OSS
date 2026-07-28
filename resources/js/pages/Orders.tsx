import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { GroupedList, GroupedListDivider } from '@/components/ui/grouped-list';
import { RowActions } from '@/components/ui/row-actions';
import { Empty, EmptyDescription, EmptyTitle } from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Drawer,
    DrawerContent,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Separator } from '@/components/ui/separator';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { IosPageHeader } from '@/components/ios/IosPageHeader';
import { Page } from '@/components/Page';
import { ProductSearchSelect } from '@/components/ios/ProductSearchSelect';
import { IosListRow } from '@/components/ios/IosListRow';
import { IosSwipeRow } from '@/components/ios/IosSwipeRow';
import { StatusBadge } from '@/components/ios/StatusBadge';
import { PaymentBadge } from '@/components/ios/PaymentBadge';
import { OrderStatusStepper } from '@/components/orders/OrderStatusStepper';
import { OrderStatusActions } from '@/components/orders/OrderStatusActions';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useApi } from '@/lib/ApiProvider';
import { formatActivityDiff, formatCurrency, haptic, todayISO } from '@/lib/format';
import { isMeaningfulDraft } from '@/lib/draft';
import { getStatusLabel } from '@/lib/orderStatus';
import type { ActivityLogEntry, Order, OrderFormData, OrderReturnResult, Product } from '@/lib/types';

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

const emptyForm = (): OrderFormData => ({
    customer_name: '',
    customer_phone: '',
    order_date: todayISO(),
    notes: '',
    items: [{ product_unit_id: '', quantity: 1 }],
    status: 'draft',
    version: 1,
});

export default function Orders() {
    const { api, user } = useApi();
    const [orders, setOrders] = useState<Order[]>([]);
    const [products, setProducts] = useState<Product[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [customerSuggestions, setCustomerSuggestions] = useState<string[]>([]);
    const [statusFilter, setStatusFilter] = useState('');
    const [pulling, setPulling] = useState(false);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [paymentOpen, setPaymentOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [draftRestoreOpen, setDraftRestoreOpen] = useState(false);
    const [editing, setEditing] = useState<Order | null>(null);
    const [detail, setDetail] = useState<Order | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<Order | null>(null);
    const [forceDelete, setForceDelete] = useState(false);
    const [form, setForm] = useState<OrderFormData>(emptyForm());
    const [paymentForm, setPaymentForm] = useState({ amount: '', metode: 'cash' });
    const [paymentOrder, setPaymentOrder] = useState<Order | null>(null);
    const [mode, setMode] = useState<'form' | 'detail'>('form');
    const [activityLogs, setActivityLogs] = useState<ActivityLogEntry[]>([]);
    const [returnQtys, setReturnQtys] = useState<Record<number, number>>({});
    const [returnReason, setReturnReason] = useState('');
    const [lastReturn, setLastReturn] = useState<OrderReturnResult | null>(null);
    const [advancing, setAdvancing] = useState(false);

    const load = useCallback(async () => {
        try {
            const data = await api.list<Order[]>('orders', {
                search: search || undefined,
                status: statusFilter || undefined,
            });
            setOrders(data);
        } catch (err) {
            if (err instanceof Error && err.message !== 'UNAUTHENTICATED') {
                toast.error(err.message);
            }
        } finally {
            setLoading(false);
            setPulling(false);
        }
    }, [api, search, statusFilter]);

    useEffect(() => {
        const t = setTimeout(load, search ? 300 : 0);
        return () => clearTimeout(t);
    }, [load, search]);

    useEffect(() => {
        api.list<Product[]>('products').then(setProducts).catch(() => {});
    }, [api]);

    useEffect(() => {
        if (!orders.length) return;
        const names = [...new Set(orders.map((o) => o.customer_name).filter(Boolean) as string[])];
        setCustomerSuggestions(names.slice(0, 20));
    }, [orders]);

    useEffect(() => {
        if (!user) return;

        const key = `draft_order_${user.id}`;
        const saved = localStorage.getItem(key);
        if (!saved) return;

        try {
            const parsed = JSON.parse(saved) as OrderFormData;
            if (!isMeaningfulDraft(parsed)) {
                localStorage.removeItem(key);
            }
        } catch {
            localStorage.removeItem(key);
        }
    }, [user]);

    useEffect(() => {
        if (!drawerOpen || !user) return;
        const timer = setInterval(() => {
            if (!isMeaningfulDraft(form)) return;
            localStorage.setItem(`draft_order_${user.id}`, JSON.stringify(form));
            api.create('drafts', { draft_data: form }).catch(() => {});
        }, 5000);
        return () => clearInterval(timer);
    }, [drawerOpen, form, user, api]);

    function hydrateForm(order: Order) {
        setForm({
            customer_name: order.customer_name || '',
            customer_phone: order.customer_phone || '',
            order_date: order.order_date?.slice(0, 10) || todayISO(),
            notes: order.notes || '',
            items: order.items?.map((i) => ({
                product_unit_id: i.product_unit_id ?? i.product_unit?.id ?? '',
                quantity: i.quantity,
                discount_type: i.discount_type,
                discount_value: i.discount_value,
            })) || [
                { product_unit_id: '', quantity: 1 },
            ],
            status: order.status,
            version: order.version,
        });
    }

    function hasValidItems(items: OrderFormData['items']) {
        return items.some((item) => item.product_unit_id);
    }

    async function loadDraftCandidate(): Promise<OrderFormData | null> {
        if (!user) return null;

        const key = `draft_order_${user.id}`;
        const saved = localStorage.getItem(key);
        if (saved) {
            try {
                const parsed = JSON.parse(saved) as OrderFormData;
                if (isMeaningfulDraft(parsed)) return parsed;
                localStorage.removeItem(key);
            } catch {
                localStorage.removeItem(key);
            }
        }

        try {
            const res = await api.request<{ draft_data: OrderFormData | null }>('/drafts/restore');
            if (res.draft_data && isMeaningfulDraft(res.draft_data)) {
                return res.draft_data;
            }
        } catch {
            // ignore
        }

        return null;
    }

    async function discardDraft() {
        if (!user) return;
        localStorage.removeItem(`draft_order_${user.id}`);
        await api.request('/drafts', { method: 'DELETE' }).catch(() => {});
    }

    async function openDetail(order: Order) {
        try {
            const full = await api.get<Order>('orders', order.id);
            const logs = await api.request<ActivityLogEntry[]>(`/orders/${order.id}/activity`);
            setDetail(full);
            setActivityLogs(logs);
            setEditing(full);
            setMode('detail');
            setDrawerOpen(true);
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memuat detail');
        }
    }

    async function openCreate() {
        setEditing(null);
        setDetail(null);
        setMode('form');

        const draft = await loadDraftCandidate();
        if (draft) {
            setDraftRestoreOpen(true);
            return;
        }

        setForm(emptyForm());
        setDrawerOpen(true);
    }

    async function openEdit(order: Order) {
        try {
            const full = await api.get<Order>('orders', order.id);
            setEditing(full);
            setDetail(null);
            hydrateForm(full);
            setMode('form');
            setDrawerOpen(true);
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memuat pesanan');
        }
    }

    async function downloadInvoice(preview = false) {
        if (!detail) return;
        try {
            const blob = await api.requestBlob(
                `/orders/${detail.id}/invoice${preview ? '/preview' : ''}`,
            );
            const url = URL.createObjectURL(blob);
            if (preview) {
                window.open(url, '_blank');
            } else {
                const a = document.createElement('a');
                a.href = url;
                a.download = `invoice-${detail.invoice_no}.pdf`;
                a.click();
            }
            URL.revokeObjectURL(url);
            haptic();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal unduh invoice');
        }
    }

    async function submitReturn() {
        if (!detail) return;
        const items = Object.entries(returnQtys)
            .filter(([, qty]) => qty > 0)
            .map(([orderItemId, quantity]) => ({
                order_item_id: Number(orderItemId),
                quantity,
                reason: returnReason || undefined,
            }));

        if (!items.length || !returnReason.trim()) {
            toast.error('Isi alasan dan jumlah retur');
            return;
        }

        try {
            const result = await api.request<OrderReturnResult>(`/orders/${detail.id}/returns`, {
                method: 'POST',
                body: { reason: returnReason, items },
            });
            setLastReturn(result);
            setReturnQtys({});
            setReturnReason('');
            haptic();
            toast.success(`Retur ${result.return_no} berhasil`);
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal proses retur');
        }
    }

    async function advanceStatus(order: Order, nextStatus: string) {
        if (!order.version) {
            toast.error('Versi pesanan tidak ditemukan. Muat ulang dan coba lagi.');
            return;
        }

        setAdvancing(true);
        try {
            await api.update('orders', order.id, {
                version: order.version,
                status: nextStatus,
            });
            const full = await api.get<Order>('orders', order.id);
            const logs = await api.request<ActivityLogEntry[]>(`/orders/${order.id}/activity`);
            setDetail(full);
            setEditing(full);
            hydrateForm(full);
            haptic();
            toast.success(`Status: ${getStatusLabel(full.status)}`);
            await load();
            setActivityLogs(logs);
        } catch (err) {
            haptic([50, 50, 50]);
            toast.error(err instanceof Error ? err.message : 'Gagal ubah status');
        } finally {
            setAdvancing(false);
        }
    }

    function buildOrderPayload(nextStatus?: string) {
        return {
            customer_name: form.customer_name,
            customer_phone: form.customer_phone,
            order_date: form.order_date,
            notes: form.notes,
            status: nextStatus ?? form.status,
            version: form.version ?? editing?.version,
            items: form.items
                .filter((item) => item.product_unit_id)
                .map((item) => ({
                    product_unit_id: Number(item.product_unit_id),
                    quantity: item.quantity,
                    ...(item.discount_type ? { discount_type: item.discount_type } : {}),
                    discount_value: item.discount_value ?? 0,
                })),
        };
    }

    async function save(nextStatus?: string) {
        try {
            const payload = buildOrderPayload(nextStatus);
            if (editing) {
                if (!payload.version) {
                    throw new Error('Versi pesanan tidak ditemukan. Muat ulang dan coba lagi.');
                }
                await api.update('orders', editing.id, payload);
            } else {
                const { version: _version, status: _status, ...createPayload } = payload;
                await api.create('orders', createPayload);
                if (user) localStorage.removeItem(`draft_order_${user.id}`);
                api.request('/drafts', { method: 'DELETE' }).catch(() => {});
            }
            setDrawerOpen(false);
            haptic();
            toast.success(nextStatus === 'pending' ? 'Pesanan dikirim' : 'Disimpan');
            await load();
        } catch (err) {
            haptic([50, 50, 50]);
            toast.error(err instanceof Error ? err.message : 'Gagal menyimpan');
        }
    }

    async function submitAndSend() {
        if (!hasValidItems(form.items)) {
            toast.error('Pilih minimal satu produk');
            return;
        }

        try {
            const payload = buildOrderPayload('pending');
            if (editing) {
                if (!payload.version) {
                    throw new Error('Versi pesanan tidak ditemukan. Muat ulang dan coba lagi.');
                }
                await api.update('orders', editing.id, payload);
            } else {
                const { version: _version, status: _status, ...createPayload } = payload;
                const created = await api.create<Order>('orders', createPayload);
                await api.update('orders', created.id, {
                    version: created.version,
                    status: 'pending',
                });
                if (user) localStorage.removeItem(`draft_order_${user.id}`);
                api.request('/drafts', { method: 'DELETE' }).catch(() => {});
            }
            setDrawerOpen(false);
            haptic();
            toast.success('Pesanan dikirim');
            await load();
        } catch (err) {
            haptic([50, 50, 50]);
            toast.error(err instanceof Error ? err.message : 'Gagal kirim pesanan');
        }
    }

    function openPayment(order: Order) {
        setPaymentOrder(order);
        setPaymentForm({ amount: '', metode: 'cash' });
        setPaymentOpen(true);
    }

    async function savePayment() {
        if (!paymentOrder) return;
        try {
            await api.request(`/orders/${paymentOrder.id}/payments`, {
                method: 'POST',
                body: { amount: Number(paymentForm.amount), metode: paymentForm.metode },
            });
            setPaymentOpen(false);
            haptic();
            toast.success('Pembayaran dicatat');
            await load();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal mencatat pembayaran');
        }
    }

    function confirmDelete(order: Order) {
        setDeleteTarget(order);
        setForceDelete(false);
        setDeleteOpen(true);
    }

    async function handleDelete() {
        if (!deleteTarget) return;
        try {
            await api.destroy('orders', deleteTarget.id, forceDelete ? { force: '1' } : undefined);
            setDeleteOpen(false);
            haptic();
            toast.success('Order dihapus');
            await load();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menghapus');
        }
    }

    async function restoreDraft() {
        if (!user) return;
        const key = `draft_order_${user.id}`;

        try {
            const saved = localStorage.getItem(key);
            if (saved) {
                setForm(JSON.parse(saved));
            } else {
                const res = await api.request<{ draft_data: OrderFormData | null }>('/drafts/restore');
                if (res.draft_data) {
                    setForm(res.draft_data);
                    localStorage.setItem(key, JSON.stringify(res.draft_data));
                }
            }
            setEditing(null);
            setMode('form');
            setDrawerOpen(true);
            toast.success('Draft dipulihkan');
        } catch {
            toast.error('Gagal memulihkan draft');
        }
        setDraftRestoreOpen(false);
    }

    async function discardDraftAndCreate() {
        await discardDraft();
        setForm(emptyForm());
        setMode('form');
        setDrawerOpen(true);
        setDraftRestoreOpen(false);
    }

    const pullStart = { y: 0 };
    function onTouchStart(e: React.TouchEvent) {
        pullStart.y = e.touches[0].clientY;
    }
    function onTouchMove(e: React.TouchEvent) {
        if (window.scrollY === 0 && e.touches[0].clientY - pullStart.y > 80) setPulling(true);
    }
    async function onTouchEnd() {
        if (pulling) await load();
    }

    const isOwner = user?.role === 'owner';
    const statusLogs = detail?.statusLogs || detail?.status_logs || [];
    const dateHistories = detail?.dateHistories || detail?.date_histories || [];

    return (
        <Page>
            <IosPageHeader
                title="Pesanan"
                description="Kelola pesanan harian"
                action={
                    <Button size="sm" onClick={openCreate}>
                        <Plus data-icon="inline-start" />
                        Baru
                    </Button>
                }
            />

            <Input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Cari invoice / customer..."
            />

            <div className="w-full overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <ToggleGroup
                    value={[statusFilter || '__all__']}
                    onValueChange={(values) => {
                        const next = values[0];
                        if (next !== undefined) setStatusFilter(next === '__all__' ? '' : next);
                    }}
                    variant="outline"
                    spacing={2}
                    className="w-max flex-nowrap"
                >
                    {STATUS_FILTERS.map((s) => (
                        <ToggleGroupItem
                            key={s || 'all'}
                            value={s || '__all__'}
                            className="h-8 shrink-0 px-2.5 text-xs capitalize"
                        >
                            {STATUS_FILTER_LABELS[s] || s}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
            </div>

            <div onTouchStart={onTouchStart} onTouchMove={onTouchMove} onTouchEnd={onTouchEnd}>
            {pulling && <p className="py-2 text-center text-xs text-muted-foreground">Memuat ulang...</p>}

            {loading ? (
                <div className="flex flex-col gap-3">
                    <Skeleton className="h-16 w-full" />
                    <Skeleton className="h-16 w-full" />
                </div>
            ) : orders.length === 0 ? (
                <Empty>
                    <EmptyTitle>Belum ada order</EmptyTitle>
                    <EmptyDescription>Buat order pertama Anda</EmptyDescription>
                </Empty>
            ) : (
                <GroupedList>
                    {orders.map((order, index) => (
                        <div key={order.id}>
                            {index > 0 && <GroupedListDivider />}
                            <IosSwipeRow
                            key={order.id}
                            onSwipeLeft={() => openEdit(order)}
                            onSwipeRight={() => {
                                if (order.status === 'pending' && order.payment_status !== 'paid') {
                                    openPayment(order);
                                }
                            }}
                            leftActions={
                                <div className="flex h-full">
                                    <button
                                        type="button"
                                        onClick={() => openEdit(order)}
                                        className="action-edit px-4 text-xs font-medium"
                                    >
                                        Ubah
                                    </button>
                                    {isOwner ? (
                                        <button
                                            type="button"
                                            onClick={() => confirmDelete(order)}
                                            className="action-delete px-4 text-xs font-medium"
                                        >
                                            Hapus
                                        </button>
                                    ) : null}
                                </div>
                            }
                            rightActions={
                                order.status === 'pending' && order.payment_status !== 'paid' ? (
                                    <button
                                        type="button"
                                        onClick={() => openPayment(order)}
                                        className="action-pay h-full px-4 text-xs font-medium"
                                    >
                                        Bayar
                                    </button>
                                ) : undefined
                            }
                        >
                            <IosListRow
                                prefix={
                                    <>
                                        <StatusBadge status={order.status} compact />
                                        {order.payment_status ? (
                                            <PaymentBadge status={order.payment_status} compact />
                                        ) : null}
                                    </>
                                }
                                title={order.invoice_no}
                                subtitle={`${order.customer_name || 'No name'} • ${order.order_date?.slice(0, 10)}`}
                                onClick={() => openDetail(order)}
                                trailing={
                                    <div className="flex items-center gap-1 text-right">
                                        <p className="text-sm font-semibold">{formatCurrency(order.grand_total)}</p>
                                        <RowActions
                                            actions={[
                                                { label: 'Detail', onClick: () => openDetail(order) },
                                                { label: 'Ubah', onClick: () => openEdit(order) },
                                                ...(order.status === 'pending' && order.payment_status !== 'paid'
                                                    ? [{ label: 'Bayar', onClick: () => openPayment(order) }]
                                                    : []),
                                                { label: 'Hapus', onClick: () => confirmDelete(order), destructive: true },
                                            ]}
                                        />
                                    </div>
                                }
                            />
                        </IosSwipeRow>
                        </div>
                    ))}
                </GroupedList>
            )}
            </div>

            {/* Order form / detail drawer */}
            <Drawer open={drawerOpen} onOpenChange={setDrawerOpen} showSwipeHandle>
                <DrawerContent className="max-h-[90vh]">
                    <DrawerHeader>
                        <DrawerTitle>
                            {mode === 'detail' ? detail?.invoice_no : editing ? 'Ubah Pesanan' : 'Pesanan Baru'}
                        </DrawerTitle>
                    </DrawerHeader>
                    <div className="overflow-y-auto px-4 pb-4 flex-1">
                        {mode === 'detail' && detail ? (
                            <div className="flex flex-col gap-4">
                                <OrderStatusStepper status={detail.status} />
                                <div className="flex items-center gap-2">
                                    <StatusBadge status={detail.status} compact />
                                    {detail.payment_status ? <PaymentBadge status={detail.payment_status} compact /> : null}
                                </div>
                                <div className="flex flex-col gap-1 rounded-xl border bg-muted/30 p-3 text-sm">
                                    <p><span className="text-muted-foreground">Pelanggan:</span> {detail.customer_name || '-'}</p>
                                    <p><span className="text-muted-foreground">Telepon:</span> {detail.customer_phone || '-'}</p>
                                    <p><span className="text-muted-foreground">Tanggal:</span> {detail.order_date?.slice(0, 10)}</p>
                                    <p className="font-semibold">Total: {formatCurrency(detail.grand_total)}</p>
                                </div>

                                <Tabs defaultValue="ringkasan">
                                    <TabsList variant="segment" className="w-full">
                                        <TabsTrigger value="ringkasan">Ringkasan</TabsTrigger>
                                        <TabsTrigger value="riwayat">Riwayat</TabsTrigger>
                                        {user?.role === 'owner' && detail.status === 'selesai' && (
                                            <TabsTrigger value="retur">Retur</TabsTrigger>
                                        )}
                                    </TabsList>

                                    <TabsContent value="ringkasan" className="mt-3 flex flex-col gap-4">
                                        <Card size="sm" className="shadow-none">
                                            <CardHeader>
                                                <CardTitle>Invoice</CardTitle>
                                            </CardHeader>
                                            <CardContent className="flex flex-col gap-1 text-sm">
                                                <div className="flex justify-between"><span>Subtotal</span><span>{formatCurrency(detail.subtotal)}</span></div>
                                                <div className="flex justify-between"><span>Diskon</span><span>{formatCurrency(detail.discount_total)}</span></div>
                                                <div className="flex justify-between"><span>PPN ({detail.ppn_percentage}%)</span><span>{formatCurrency(detail.ppn_amount)}</span></div>
                                                <Separator className="my-2" />
                                                <div className="flex justify-between font-semibold"><span>Total</span><span>{formatCurrency(detail.grand_total)}</span></div>
                                                <div className="flex justify-between text-primary"><span>Dibayar</span><span>{formatCurrency(detail.total_paid)}</span></div>
                                                {detail.remaining_amount != null && detail.remaining_amount > 0 && (
                                                    <div className="flex justify-between text-destructive"><span>Sisa</span><span>{formatCurrency(detail.remaining_amount)}</span></div>
                                                )}
                                            </CardContent>
                                        </Card>
                                        <div className="flex gap-2">
                                            <Button size="sm" variant="outline" onClick={() => downloadInvoice(true)}>Pratinjau PDF</Button>
                                            <Button size="sm" onClick={() => downloadInvoice(false)}>Unduh PDF</Button>
                                        </div>
                                        {detail.items && detail.items.length > 0 && (
                                            <Card size="sm" className="shadow-none">
                                                <CardHeader>
                                                    <CardTitle>Item</CardTitle>
                                                </CardHeader>
                                                <CardContent className="flex flex-col gap-1 text-sm">
                                                    {detail.items.map((item, i) => (
                                                        <div key={i} className="flex justify-between py-1">
                                                            <span>{item.product_name}{item.satuan ? ` (${item.satuan})` : ''} × {item.quantity}</span>
                                                            <span>{formatCurrency(item.subtotal || 0)}</span>
                                                        </div>
                                                    ))}
                                                </CardContent>
                                            </Card>
                                        )}
                                        {detail.payments && detail.payments.length > 0 && (
                                            <Card size="sm" className="shadow-none">
                                                <CardHeader>
                                                    <CardTitle>Pembayaran</CardTitle>
                                                </CardHeader>
                                                <CardContent className="flex flex-col gap-1 text-sm">
                                                    {detail.payments.map((p) => (
                                                        <div key={p.id} className="flex justify-between py-1">
                                                            <span className="capitalize">{p.metode}</span>
                                                            <span>{formatCurrency(p.amount)}</span>
                                                        </div>
                                                    ))}
                                                </CardContent>
                                            </Card>
                                        )}
                                    </TabsContent>

                                    <TabsContent value="riwayat" className="mt-3 flex flex-col gap-4">
                                        {statusLogs.length > 0 && (
                                            <Card size="sm" className="shadow-none">
                                                <CardHeader>
                                                    <CardTitle>Riwayat Status</CardTitle>
                                                </CardHeader>
                                                <CardContent className="flex flex-col gap-1 text-sm">
                                                    {statusLogs.map((log) => (
                                                        <div key={log.id} className="py-1 text-xs">
                                                            <span>{getStatusLabel(log.from_status)} → {getStatusLabel(log.to_status)}</span>
                                                            <span className="text-muted-foreground ml-2">{log.created_at?.slice(0, 16)}</span>
                                                        </div>
                                                    ))}
                                                </CardContent>
                                            </Card>
                                        )}
                                        {dateHistories.length > 0 && (
                                            <Card size="sm" className="shadow-none">
                                                <CardHeader>
                                                    <CardTitle>Riwayat Tanggal</CardTitle>
                                                </CardHeader>
                                                <CardContent className="flex flex-col gap-1 text-sm">
                                                    {dateHistories.map((h) => (
                                                        <div key={h.id} className="py-1 text-xs">
                                                            {h.old_date?.slice(0, 10)} → {h.new_date?.slice(0, 10)}
                                                            {h.reason && <span className="text-muted-foreground ml-1">({h.reason})</span>}
                                                        </div>
                                                    ))}
                                                </CardContent>
                                            </Card>
                                        )}
                                        {activityLogs.length > 0 && (
                                            <Card size="sm" className="shadow-none">
                                                <CardHeader>
                                                    <CardTitle>Log Aktivitas</CardTitle>
                                                </CardHeader>
                                                <CardContent className="flex flex-col text-sm">
                                                    {activityLogs.map((log, index) => {
                                                        const diff = formatActivityDiff(log.properties);
                                                        return (
                                                            <div key={log.id}>
                                                                {index > 0 && <Separator className="my-2" />}
                                                                <div className="py-1 text-xs">
                                                                    <span className="font-medium">{log.description}</span>
                                                                    <span className="text-muted-foreground ml-2">
                                                                        {log.causer?.name || 'System'} · {log.created_at?.slice(0, 16)}
                                                                    </span>
                                                                    {diff && (
                                                                        <p className="text-muted-foreground mt-0.5">{diff}</p>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        );
                                                    })}
                                                </CardContent>
                                            </Card>
                                        )}
                                        {statusLogs.length === 0 && dateHistories.length === 0 && activityLogs.length === 0 && (
                                            <p className="text-sm text-muted-foreground">Belum ada riwayat.</p>
                                        )}
                                    </TabsContent>

                                    {user?.role === 'owner' && detail.status === 'selesai' && (
                                        <TabsContent value="retur" className="mt-3">
                                            <Card size="sm" className="shadow-none">
                                                <CardHeader>
                                                    <CardTitle>Retur</CardTitle>
                                                </CardHeader>
                                                <CardContent className="flex flex-col gap-2 text-sm">
                                                    {lastReturn && (
                                                        <p className="text-xs text-primary">
                                                            {lastReturn.return_no} — refund {formatCurrency(lastReturn.refund_amount)}
                                                        </p>
                                                    )}
                                                    <Input
                                                        placeholder="Alasan retur"
                                                        value={returnReason}
                                                        onChange={(e) => setReturnReason(e.target.value)}
                                                    />
                                                    {detail.items?.map((item) => (
                                                        <div key={item.id} className="flex items-center gap-2">
                                                            <span className="flex-1 truncate">{item.product_name}</span>
                                                            <Input
                                                                type="number"
                                                                min={0}
                                                                max={item.quantity}
                                                                className="w-16"
                                                                value={returnQtys[item.id!] ?? 0}
                                                                onChange={(e) => setReturnQtys({
                                                                    ...returnQtys,
                                                                    [item.id!]: Number(e.target.value),
                                                                })}
                                                            />
                                                        </div>
                                                    ))}
                                                    <Button size="sm" onClick={submitReturn}>Proses Retur</Button>
                                                </CardContent>
                                            </Card>
                                        </TabsContent>
                                    )}
                                </Tabs>
                            </div>
                        ) : (
                            <FieldGroup>
                                {editing && form.status !== 'draft' && (
                                    <div className="flex flex-col gap-2 rounded-xl border bg-muted/30 p-3">
                                        <OrderStatusStepper status={form.status || 'draft'} compact />
                                        <p className="text-xs text-muted-foreground">
                                            Status diubah dari tampilan detail pesanan.
                                        </p>
                                    </div>
                                )}
                                <Field>
                                    <FieldLabel>Nama Pelanggan</FieldLabel>
                                    <Input
                                        list="customer-suggestions"
                                        value={form.customer_name}
                                        onChange={(e) => setForm({ ...form, customer_name: e.target.value })}
                                    />
                                    <datalist id="customer-suggestions">
                                        {customerSuggestions.map((name) => (
                                            <option key={name} value={name} />
                                        ))}
                                    </datalist>
                                </Field>
                                <Field>
                                    <FieldLabel>Telepon</FieldLabel>
                                    <Input value={form.customer_phone} onChange={(e) => setForm({ ...form, customer_phone: e.target.value })} />
                                </Field>
                                <Field>
                                    <FieldLabel>Tanggal Pesanan</FieldLabel>
                                    <Input type="date" value={form.order_date} onChange={(e) => setForm({ ...form, order_date: e.target.value })} />
                                </Field>
                                <Field>
                                    <FieldLabel>Catatan</FieldLabel>
                                    <Input value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} />
                                </Field>
                                <div className="flex flex-col gap-3">
                                    <div className="flex items-center justify-between">
                                        <p className="text-sm font-medium">Item Pesanan</p>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={() => setForm({ ...form, items: [...form.items, { product_unit_id: '', quantity: 1 }] })}
                                        >
                                            <Plus data-icon="inline-start" />
                                            Item
                                        </Button>
                                    </div>
                                    {form.items.map((item, idx) => (
                                        <Card key={idx} size="sm" className="shadow-none">
                                            <CardHeader>
                                                <CardTitle>Item {idx + 1}</CardTitle>
                                                {form.items.length > 1 ? (
                                                    <CardAction>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            className="text-destructive"
                                                            onClick={() => setForm({ ...form, items: form.items.filter((_, i) => i !== idx) })}
                                                        >
                                                            <Trash2 data-icon="inline-start" />
                                                            Hapus
                                                        </Button>
                                                    </CardAction>
                                                ) : null}
                                            </CardHeader>
                                            <CardContent className="flex flex-col gap-2">
                                                <Field>
                                                    <FieldLabel>Produk</FieldLabel>
                                                    <ProductSearchSelect
                                                        products={products}
                                                        value={item.product_unit_id}
                                                        onChange={(productUnitId) => {
                                                            const items = [...form.items];
                                                            items[idx] = { ...items[idx], product_unit_id: productUnitId };
                                                            setForm({ ...form, items });
                                                        }}
                                                    />
                                                </Field>
                                                <div className="grid grid-cols-2 gap-2">
                                                    <Field>
                                                        <FieldLabel>Jumlah</FieldLabel>
                                                        <Input
                                                            type="number"
                                                            min={1}
                                                            value={item.quantity}
                                                            onChange={(e) => {
                                                                const items = [...form.items];
                                                                items[idx] = { ...items[idx], quantity: Number(e.target.value) };
                                                                setForm({ ...form, items });
                                                            }}
                                                        />
                                                    </Field>
                                                    <Field>
                                                        <FieldLabel>Tipe Diskon</FieldLabel>
                                                        <Select
                                                            value={item.discount_type || 'fixed'}
                                                            onValueChange={(v) => {
                                                                const items = [...form.items];
                                                                items[idx] = { ...items[idx], discount_type: v };
                                                                setForm({ ...form, items });
                                                            }}
                                                        >
                                                            <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                                                            <SelectContent>
                                                                <SelectGroup>
                                                                    <SelectItem value="fixed">Rp</SelectItem>
                                                                    <SelectItem value="percent">%</SelectItem>
                                                                </SelectGroup>
                                                            </SelectContent>
                                                        </Select>
                                                    </Field>
                                                </div>
                                                <Field>
                                                    <FieldLabel>Nilai Diskon</FieldLabel>
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        placeholder="0"
                                                        value={item.discount_value ?? ''}
                                                        onChange={(e) => {
                                                            const items = [...form.items];
                                                            items[idx] = { ...items[idx], discount_value: Number(e.target.value) };
                                                            setForm({ ...form, items });
                                                        }}
                                                    />
                                                </Field>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            </FieldGroup>
                        )}
                    </div>
                    <DrawerFooter className="flex-col gap-3">
                        {mode === 'detail' && detail ? (
                            <>
                                <OrderStatusActions
                                    status={detail.status}
                                    loading={advancing}
                                    onAdvance={(nextStatus) => advanceStatus(detail, nextStatus)}
                                />
                                <div className="flex gap-2">
                                    <Button variant="outline" className="flex-1" onClick={() => openEdit(detail)}>Ubah</Button>
                                    {detail.status === 'pending' && detail.payment_status !== 'paid' && (
                                        <Button className="flex-1" onClick={() => { setDrawerOpen(false); openPayment(detail); }}>Bayar</Button>
                                    )}
                                </div>
                            </>
                        ) : (
                            <div className="flex w-full flex-col gap-2">
                                <div className="flex gap-2">
                                    <Button variant="outline" className="flex-1" onClick={() => setDrawerOpen(false)}>Batal</Button>
                                    <Button className="flex-1" onClick={() => save()}>
                                        {form.status === 'draft' ? 'Simpan Draft' : 'Simpan'}
                                    </Button>
                                    {form.status === 'draft' && hasValidItems(form.items) && (
                                        <Button className="flex-1" variant="secondary" onClick={submitAndSend}>Kirim Pesanan</Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </DrawerFooter>
                </DrawerContent>
            </Drawer>

            {/* Payment drawer */}
            <Drawer open={paymentOpen} onOpenChange={setPaymentOpen} showSwipeHandle>
                <DrawerContent>
                    <DrawerHeader><DrawerTitle>Tambah Pembayaran</DrawerTitle></DrawerHeader>
                    <div className="px-4 pb-4">
                        <FieldGroup>
                            <Field>
                                <FieldLabel>Jumlah</FieldLabel>
                                <Input type="number" min={1} value={paymentForm.amount} onChange={(e) => setPaymentForm({ ...paymentForm, amount: e.target.value })} />
                            </Field>
                            <Field>
                                <FieldLabel>Metode</FieldLabel>
                                <Select value={paymentForm.metode} onValueChange={(v) => setPaymentForm({ ...paymentForm, metode: v })}>
                                    <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="cash">Cash</SelectItem>
                                            <SelectItem value="transfer">Transfer</SelectItem>
                                            <SelectItem value="qris">QRIS</SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                        </FieldGroup>
                    </div>
                    <DrawerFooter>
                        <Button className="w-full" onClick={savePayment}>Catat Pembayaran</Button>
                    </DrawerFooter>
                </DrawerContent>
            </Drawer>

            {/* Delete confirm */}
            <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus order?</AlertDialogTitle>
                        <AlertDialogDescription>
                            {isOwner ? 'Pilih soft delete atau force delete permanen.' : 'Order akan di-soft delete.'}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    {isOwner && (
                        <Field orientation="horizontal">
                            <Checkbox
                                id="force-delete"
                                checked={forceDelete}
                                onCheckedChange={(checked) => setForceDelete(checked === true)}
                            />
                            <FieldLabel htmlFor="force-delete">Force delete (permanen)</FieldLabel>
                        </Field>
                    )}
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDelete} className="bg-destructive text-destructive-foreground">Hapus</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Draft restore */}
            <AlertDialog open={draftRestoreOpen} onOpenChange={setDraftRestoreOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Pulihkan draft?</AlertDialogTitle>
                        <AlertDialogDescription>Ditemukan draft order tersimpan sebelumnya.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => { void discardDraftAndCreate(); }}>Buang</AlertDialogCancel>
                        <AlertDialogAction onClick={restoreDraft}>Pulihkan</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </Page>
    );
}
