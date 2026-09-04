import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { GroupedList, GroupedListDivider } from '@/components/ui/grouped-list';
import { Empty, EmptyDescription, EmptyTitle } from '@/components/ui/empty';
import { OrderRowSkeleton } from '@/components/orders/OrderRowSkeleton';
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
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Separator } from '@/components/ui/separator';
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
import { StatusBadge } from '@/components/ios/StatusBadge';
import { PaymentBadge } from '@/components/ios/PaymentBadge';
import { OrderStatusStepper } from '@/components/orders/OrderStatusStepper';
import { OrderStatusActions } from '@/components/orders/OrderStatusActions';
import { OrderRow } from '@/components/orders/OrderRow';
import { OrderDraftBanner } from '@/components/orders/OrderDraftBanner';
import { OrderListToolbar } from '@/components/orders/OrderListToolbar';
import { OrderKpiStrip } from '@/components/orders/OrderKpiStrip';
import { CartBar } from '@/components/orders/CartBar';
import { CartReviewSheet } from '@/components/orders/CartReviewSheet';
import { PosProductGrid } from '@/components/orders/PosProductGrid';
import { OrderDesktopPanel } from '@/components/orders/OrderDesktopPanel';
import { OrderTable } from '@/components/orders/OrderTable';
import { ResponsiveFormPanel } from '@/components/ResponsiveFormPanel';
import { PaymentSuccessSheet } from '@/components/orders/PaymentSuccessSheet';
import { flattenProductUnits } from '@/lib/products';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useApi } from '@/lib/ApiProvider';
import { useCachedList } from '@/lib/useCachedList';
import { listAll } from '@/lib/listAll';
import { useMediaQuery } from '@/lib/useMediaQuery';
import { usePullToRefresh, PullToRefreshIndicator } from '@/hooks/use-pull-to-refresh';
import { formatActivityDiff, formatCurrency, haptic, todayISO } from '@/lib/format';
import { isMeaningfulDraft } from '@/lib/draft';
import { deliveryMethodLabel } from '@/lib/delivery';
import { canEditOrder, getStatusLabel } from '@/lib/orderStatus';
import { printReceipt } from '@/lib/receipt';
import { getAppBranding } from '@/lib/branding';
import type { ActivityLogEntry, MayarLinkResponse, Order, OrderFormData, OrderReturnResult, OrderSummary, Payment, PaymentSettings, Product, ProductUnit, User } from '@/lib/types';

function currentMonthValue(): string {
    return String(new Date().getMonth() + 1);
}

function currentYearValue(): string {
    return String(new Date().getFullYear());
}

const emptyForm = (): OrderFormData => ({
    customer_name: '',
    customer_phone: '',
    customer_address: '',
    delivery_method: 'diambil',
    order_date: todayISO(),
    notes: '',
    items: [{ product_unit_id: '', quantity: 1 }],
    status: 'draft',
    version: 1,
});

function isPayableOrder(order: Order) {
    return ['pending', 'diproses', 'dikirim'].includes(order.status) && order.payment_status !== 'paid';
}

// Draft forms hold customer PII; a localStorage draft older than this (or
// one saved before savedAt existed) is dropped instead of restored.
const DRAFT_MAX_AGE_MS = 7 * 24 * 60 * 60 * 1000;

function draftExpired(savedAt: unknown): boolean {
    if (typeof savedAt !== 'number' || !Number.isFinite(savedAt)) return true;
    return Date.now() - savedAt > DRAFT_MAX_AGE_MS;
}

export default function Orders() {
    const { api, user, tenant } = useApi();
    const [products, setProducts] = useState<Product[]>([]);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [monthFilter, setMonthFilter] = useState(currentMonthValue);
    const [yearFilter, setYearFilter] = useState(currentYearValue);
    const [sortFilter, setSortFilter] = useState('date_desc');
    const [summary, setSummary] = useState<OrderSummary | null>(null);
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(20);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [reviewOpen, setReviewOpen] = useState(false);
    const [paymentOpen, setPaymentOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [pendingDraft, setPendingDraft] = useState<OrderFormData | null>(null);
    const [editing, setEditing] = useState<Order | null>(null);
    const [detail, setDetail] = useState<Order | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<Order | null>(null);
    const [forceDelete, setForceDelete] = useState(false);
    const [paymentDeleteOpen, setPaymentDeleteOpen] = useState(false);
    const [paymentDeleteTarget, setPaymentDeleteTarget] = useState<Payment | null>(null);
    const [reassignOpen, setReassignOpen] = useState(false);
    const [reassignUserId, setReassignUserId] = useState('');
    const [users, setUsers] = useState<User[]>([]);
    const [form, setForm] = useState<OrderFormData>(emptyForm());
    const [paymentForm, setPaymentForm] = useState<{ amount: string; metode: string; tendered: string }>({ amount: '', metode: 'cash', tendered: '' });
    const [paymentOrder, setPaymentOrder] = useState<Order | null>(null);
    const [successOpen, setSuccessOpen] = useState(false);
    const [lastPayment, setLastPayment] = useState<Payment | null>(null);
    const [mode, setMode] = useState<'form' | 'detail'>('form');
    const [activityLogs, setActivityLogs] = useState<ActivityLogEntry[]>([]);
    const [returnQtys, setReturnQtys] = useState<Record<number, number>>({});
    const [returnReason, setReturnReason] = useState('');
    const [lastReturn, setLastReturn] = useState<OrderReturnResult | null>(null);
    const [advancing, setAdvancing] = useState(false);
    const [paymentSettings, setPaymentSettings] = useState<PaymentSettings | null>(null);
    const [mayarQr, setMayarQr] = useState<MayarLinkResponse | null>(null);
    const [mayarLoading, setMayarLoading] = useState(false);
    const [mayarAttempted, setMayarAttempted] = useState(false);
    // Guards the autosave interval against firing once save()/submitAndSend()
    // has started its API chain (cleared again whenever the drawer opens).
    const submittingRef = useRef(false);

    const {
        data: orders,
        loading,
        refreshing,
        lastPage,
        total: totalOrders,
        refetch,
    } = useCachedList<Order>(
        'orders',
        api,
        {
            search: search || undefined,
            status: statusFilter || undefined,
            month: monthFilter || undefined,
            year: yearFilter || undefined,
            sort: sortFilter,
            page: String(page),
        },
        { perPage },
    );

    useEffect(() => {
        // POS picker needs every product unit; endpoint paginates, so walk bounded pages.
        listAll<Product>(api, 'products').then(setProducts).catch(() => {});
    }, [api]);

    useEffect(() => {
        // Preload so cetakStruk never awaits a fetch before printing; the
        // desktop window path must run inside the click gesture.
        api.getPaymentSettings().then(setPaymentSettings).catch(() => setPaymentSettings(null));
    }, [api]);

    useEffect(() => {
        let cancelled = false;
        api.request<OrderSummary>(`/orders/summary?month=${monthFilter}&year=${yearFilter}`)
            .then((data) => { if (!cancelled) setSummary(data); })
            .catch(() => { /* summary strip is non-critical; leave stale/hidden */ });
        return () => { cancelled = true; };
    }, [api, monthFilter, yearFilter]);

    useEffect(() => {
        if (!drawerOpen) setReviewOpen(false);
    }, [drawerOpen]);

    useEffect(() => {
        if (!user) return;

        const key = `draft_order_${user.id}`;
        const saved = localStorage.getItem(key);
        if (!saved) return;

        try {
            const parsed = JSON.parse(saved) as OrderFormData & { savedAt?: unknown };
            if (!isMeaningfulDraft(parsed) || draftExpired(parsed.savedAt)) {
                localStorage.removeItem(key);
            }
        } catch {
            localStorage.removeItem(key);
        }
    }, [user]);

    useEffect(() => {
        // Autosave drafts only while creating a new order — editing an
        // existing order must not re-write the new-order draft.
        if (!drawerOpen || !user || editing) return;
        const timer = setInterval(() => {
            if (submittingRef.current) return;
            if (!isMeaningfulDraft(form)) return;
            localStorage.setItem(`draft_order_${user.id}`, JSON.stringify({ ...form, savedAt: Date.now() }));
            api.create('drafts', { draft_data: form }).catch(() => {});
        }, 5000);
        return () => clearInterval(timer);
    }, [drawerOpen, editing, form, user, api]);

    function hydrateForm(order: Order) {
        setForm({
            customer_name: order.customer_name || '',
            customer_phone: order.customer_phone || '',
            customer_address: order.customer_address || '',
            delivery_method: order.delivery_method === 'diantar' ? 'diantar' : 'diambil',
            delivery_distance_km: order.delivery_distance_km != null
                ? String(order.delivery_distance_km)
                : '',
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
                const parsed = JSON.parse(saved) as OrderFormData & { savedAt?: unknown };
                if (isMeaningfulDraft(parsed) && !draftExpired(parsed.savedAt)) {
                    const { savedAt: _savedAt, ...draft } = parsed;
                    return draft;
                }
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
        submittingRef.current = false;
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
        submittingRef.current = false;
        setEditing(null);
        setDetail(null);
        setMode('form');

        const draft = await loadDraftCandidate();
        if (draft) {
            setForm(emptyForm());
            setPendingDraft(draft);
            setDrawerOpen(true);
            return;
        }

        setForm(emptyForm());
        setDrawerOpen(true);
    }

    async function openEdit(order: Order) {
        if (!canEditOrder(order.status)) {
            toast.error('Pesanan yang sudah diproses tidak bisa diubah lagi.');
            return;
        }
        submittingRef.current = false;
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

    async function downloadInvoice(order: Order, preview = false) {
        try {
            const blob = await api.requestBlob(
                `/orders/${order.id}/invoice${preview ? '/preview' : ''}`,
            );
            const url = URL.createObjectURL(blob);
            if (preview) {
                window.open(url, '_blank');
                // Firefox/Safari resolve the blob document asynchronously;
                // revoking synchronously kills the preview. Keep it alive
                // briefly, then release.
                setTimeout(() => URL.revokeObjectURL(url), 60000);
            } else {
                const a = document.createElement('a');
                a.href = url;
                a.download = `invoice-${order.invoice_no}.pdf`;
                a.click();
                URL.revokeObjectURL(url);
            }
            haptic();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal unduh invoice');
        }
    }

    async function cetakStruk(order: Order) {
        let settings = paymentSettings;
        if (!settings) {
            try {
                settings = await api.getPaymentSettings();
                setPaymentSettings(settings);
            } catch {
                settings = null;
            }
        }
        try {
            printReceipt(order, tenant, getAppBranding(), settings);
            haptic();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal mencetak struk');
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
            setPaymentOrder(full);
            setEditing(full);
            hydrateForm(full);
            haptic();
            toast.success(`Status: ${getStatusLabel(full.status)}`);
            await refetch();
            setActivityLogs(logs);
        } catch (err) {
            haptic([50, 50, 50]);
            toast.error(err instanceof Error ? err.message : 'Gagal ubah status');
        } finally {
            setAdvancing(false);
        }
    }

    function buildOrderPayload(nextStatus?: string) {
        // Server ignores delivery money fields on non-draft updates; mirror
        // that guard so payloads only carry them while still editable.
        const deliveryEditable = !editing || editing.status === 'draft';
        const isDiantar = form.delivery_method === 'diantar';
        const distanceKm = form.delivery_distance_km != null && String(form.delivery_distance_km).trim() !== ''
            ? Number(form.delivery_distance_km)
            : undefined;
        const feeOverride = isDiantar && form.delivery_fee_override != null && String(form.delivery_fee_override).trim() !== ''
            ? Number(form.delivery_fee_override)
            : undefined;
        return {
            customer_name: form.customer_name,
            customer_phone: form.customer_phone,
            customer_address: form.customer_address,
            order_date: form.order_date,
            notes: form.notes,
            ...(deliveryEditable ? {
                delivery_method: isDiantar ? 'diantar' : 'diambil',
                ...(isDiantar && distanceKm !== undefined && Number.isFinite(distanceKm)
                    ? { delivery_distance_km: distanceKm }
                    : {}),
                ...(feeOverride !== undefined && Number.isFinite(feeOverride) && feeOverride >= 0
                    ? { delivery_fee: feeOverride }
                    : {}),
            } : {}),
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
        submittingRef.current = true;
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
            await refetch();
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

        submittingRef.current = true;
        // Set once the create step succeeded — on a later failure the order
        // already exists as a draft, so we must not let the user blindly
        // resubmit (that would duplicate it).
        let createdOrderId: number | null = null;
        try {
            const payload = buildOrderPayload('pending');
            let orderId: number;
            if (editing) {
                if (!payload.version) {
                    throw new Error('Versi pesanan tidak ditemukan. Muat ulang dan coba lagi.');
                }
                await api.update('orders', editing.id, payload);
                orderId = editing.id;
            } else {
                const { version: _version, status: _status, ...createPayload } = payload;
                const created = await api.create<Order>('orders', createPayload);
                createdOrderId = created.id;
                if (user) localStorage.removeItem(`draft_order_${user.id}`);
                api.request('/drafts', { method: 'DELETE' }).catch(() => {});
                await api.update('orders', created.id, {
                    version: created.version,
                    status: 'pending',
                });
                orderId = created.id;
            }
            const fresh = await api.get<Order>('orders', orderId);
            setDrawerOpen(false);
            haptic();
            toast.success('Pesanan dibuat');
            openPayment(fresh);
            await refetch();
        } catch (err) {
            haptic([50, 50, 50]);
            if (createdOrderId !== null) {
                setDrawerOpen(false);
                toast.error('Pesanan tersimpan sebagai draf, gagal mengirim — periksa daftar pesanan');
                await refetch();
            } else {
                toast.error(err instanceof Error ? err.message : 'Gagal kirim pesanan');
            }
        }
    }

    function openPayment(order: Order) {
        setPaymentOrder(order);
        const remaining = Math.max(0, Number(order.remaining_amount ?? Number(order.grand_total) - Number(order.total_paid ?? 0)));
        setPaymentForm({ amount: remaining.toFixed(2), metode: 'cash', tendered: '' });
        setMayarQr(null);
        setMayarLoading(false);
        setMayarAttempted(false);
        setPaymentOpen(true);
        api.getPaymentSettings().then(setPaymentSettings).catch(() => setPaymentSettings(null));
    }

    async function loadMayarQr() {
        if (!paymentOrder) return;
        setMayarAttempted(true);
        setMayarLoading(true);
        try {
            const res = await api.createMayarLink(paymentOrder.id);
            setMayarQr(res);
            if (res.enabled && !res.qr_url) {
                toast.error(res.message ?? 'Gagal membuat QR Mayar');
            }
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal membuat QR Mayar');
        } finally {
            setMayarLoading(false);
        }
    }

    useEffect(() => {
        if (!paymentOpen || paymentForm.metode !== 'qris') return;
        if (!paymentSettings?.mayar_enabled || paymentSettings.qris_image_url) return;
        if (mayarAttempted || mayarLoading) return;
        loadMayarQr();
    }, [paymentOpen, paymentForm.metode, paymentSettings, mayarAttempted, mayarLoading]);

    async function savePayment() {
        if (!paymentOrder) return;
        const amount = Number(paymentForm.amount);
        if (!Number.isFinite(amount) || amount < 1) {
            toast.error('Isi jumlah pembayaran');
            return;
        }
        try {
            const body: { amount: number; metode: string; tendered?: number } = { amount, metode: paymentForm.metode };
            if (paymentForm.metode === 'cash' && paymentForm.tendered !== '') {
                body.tendered = Number(paymentForm.tendered);
            }
            const payment = await api.request<Payment>(`/orders/${paymentOrder.id}/payments`, { method: 'POST', body });
            const fresh = await api.get<Order>('orders', paymentOrder.id);
            setLastPayment(payment);
            setPaymentOrder(fresh);
            setPaymentOpen(false);
            setSuccessOpen(true);
            haptic();
            toast.success('Pembayaran dicatat');
            await refetch();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal mencatat pembayaran');
        }
    }

    const paymentRemaining = paymentOrder
        ? Math.max(0, Number(paymentOrder.grand_total) - Number(paymentOrder.total_paid ?? 0))
        : 0;
    const effectiveTendered = paymentForm.tendered !== '' && Number(paymentForm.tendered) > 0
        ? Number(paymentForm.tendered)
        : (Number(paymentForm.amount) || 0);
    const paymentChange = Math.max(0, effectiveTendered - paymentRemaining);
    // Cash paid with money beyond what's owed: the detail card shows the
    // buyer's handed-over money (Tunai) instead of Dibayar, which otherwise
    // reads like the buyer paid the credited amount.
    const detailCashChangePayment = detail?.payments?.find(
        (p) => ['cash', 'tunai'].includes((p.metode || '').toLowerCase())
            && Number(p.tendered ?? 0) > Number(p.amount ?? 0),
    );
    const mayarDynamicQr = paymentForm.metode === 'qris'
        && !!paymentSettings?.mayar_enabled
        && !paymentSettings?.qris_image_url;

    useEffect(() => {
        if (!mayarDynamicQr) return;
        setPaymentForm((prev) => (prev.amount === paymentRemaining.toFixed(2)
            ? prev
            : { ...prev, amount: paymentRemaining.toFixed(2) }));
    }, [mayarDynamicQr, paymentRemaining]);

    async function quickCancel(order: Order) {
        if (!order.version) {
            toast.error('Versi pesanan tidak ditemukan. Muat ulang dan coba lagi.');
            return;
        }
        try {
            await api.update('orders', order.id, {
                version: order.version,
                status: 'cancelled',
            });
            haptic();
            toast.success('Pesanan dibatalkan');
            await refetch();
        } catch (err) {
            haptic([50, 50, 50]);
            toast.error(err instanceof Error ? err.message : 'Gagal membatalkan');
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
            await refetch();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menghapus');
        }
    }

    function confirmDeletePayment(payment: Payment) {
        setPaymentDeleteTarget(payment);
        setPaymentDeleteOpen(true);
    }

    async function handleDeletePayment() {
        if (!paymentDeleteTarget || !detail) return;
        try {
            await api.request(`/orders/${detail.id}/payments/${paymentDeleteTarget.id}`, { method: 'DELETE' });
            setPaymentDeleteOpen(false);
            const full = await api.get<Order>('orders', detail.id);
            setDetail(full);
            setPaymentOrder(full);
            setEditing(full);
            hydrateForm(full);
            haptic();
            toast.success('Pembayaran dihapus');
            await refetch();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menghapus pembayaran');
        }
    }

    async function openReassign() {
        if (!detail) return;
        setReassignUserId(String(detail.user_id));
        setReassignOpen(true);
        try {
            // /users paginates; the reassign dropdown needs every staff member.
            setUsers(await listAll<User>(api, 'users'));
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memuat pengguna');
        }
    }

    async function submitReassign() {
        if (!detail || !reassignUserId) return;
        try {
            await api.request('/orders/reassign', {
                method: 'POST',
                body: {
                    from_user_id: detail.user_id,
                    to_user_id: Number(reassignUserId),
                },
            });
            setReassignOpen(false);
            const full = await api.get<Order>('orders', detail.id);
            const logs = await api.request<ActivityLogEntry[]>(`/orders/${detail.id}/activity`);
            setDetail(full);
            setPaymentOrder(full);
            setEditing(full);
            hydrateForm(full);
            setActivityLogs(logs);
            haptic();
            toast.success('Pesanan ditugaskan ulang');
            await refetch();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menugaskan ulang');
        }
    }

    function applyDraft() {
        if (pendingDraft) {
            setForm(pendingDraft);
            toast.success('Draft dipulihkan');
        }
        setPendingDraft(null);
    }

    async function clearDraft() {
        await discardDraft();
        setPendingDraft(null);
    }

    const { pulling, refreshing: pullRefreshing, bind } = usePullToRefresh(refetch);

    const isDesktop = useMediaQuery('(min-width: 1024px)');

    const isOwner = user?.role === 'owner';
    const canDeletePayments = !!user && (user.role === 'owner' || detail?.user_id === user.id);
    const activeUsers = users.filter((u) => u.is_active);
    const reassignCandidates = detail && !activeUsers.some((u) => u.id === detail.user_id)
        ? [users.find((u) => u.id === detail.user_id), ...activeUsers].filter((u): u is User => u !== undefined)
        : activeUsers;
    const reassignItems = reassignCandidates.map((u) => ({
        value: String(u.id),
        label: `${u.name} — ${u.role === 'owner' ? 'Owner' : 'Staff'}`,
    }));
    const statusLogs = detail?.statusLogs || detail?.status_logs || [];
    const dateHistories = detail?.dateHistories || detail?.date_histories || [];
    const formTotal = useMemo(() => {
        const unitPrice = new Map<number, number>();
        for (const product of products) {
            for (const unit of product.units ?? []) {
                unitPrice.set(unit.id, unit.harga_jual);
            }
        }
        return form.items.reduce((sum, item) => {
            if (!item.product_unit_id) return sum;
            const price = unitPrice.get(Number(item.product_unit_id)) ?? 0;
            const gross = price * item.quantity;
            const discount = item.discount_type === 'percent'
                ? (gross * (item.discount_value ?? 0)) / 100
                : (item.discount_value ?? 0);
            return sum + Math.max(0, gross - discount);
        }, 0);
    }, [form.items, products]);

    const unitMap = useMemo(() => {
        const map = new Map<number, ProductUnit>();
        for (const unit of flattenProductUnits(products)) {
            map.set(unit.id, unit);
        }
        return map;
    }, [products]);

    const cartQuantities = useMemo(() => {
        const quantities: Record<number, number> = {};
        for (const item of form.items) {
            const id = Number(item.product_unit_id);
            if (id) quantities[id] = (quantities[id] ?? 0) + item.quantity;
        }
        return quantities;
    }, [form.items]);

    const cartCount = Object.values(cartQuantities).reduce((sum, qty) => sum + qty, 0);

    const cartItems = useMemo(
        () => form.items.map((item, idx) => ({ item, idx })).filter(({ item }) => item.product_unit_id),
        [form.items],
    );

    const addToCart = useCallback((productUnitId: number) => {
        const id = Number(productUnitId);
        setForm((prev) => {
            const existing = prev.items.findIndex((it) => Number(it.product_unit_id) === id);
            if (existing >= 0) {
                const items = [...prev.items];
                items[existing] = { ...items[existing], quantity: items[existing].quantity + 1 };
                return { ...prev, items };
            }
            const blankIdx = prev.items.findIndex((it) => !it.product_unit_id);
            const newItem = { product_unit_id: id as number | string, quantity: 1 };
            if (blankIdx >= 0) {
                const items = [...prev.items];
                items[blankIdx] = newItem;
                return { ...prev, items };
            }
            return { ...prev, items: [...prev.items, newItem] };
        });
    }, []);

    const updateCartItem = useCallback((idx: number, patch: Partial<OrderFormData['items'][number]>) => {
        setForm((prev) => {
            const items = [...prev.items];
            items[idx] = { ...items[idx], ...patch };
            return { ...prev, items };
        });
    }, []);

    const removeCartItem = useCallback((idx: number) => {
        setForm((prev) => ({ ...prev, items: prev.items.filter((_, i) => i !== idx) }));
    }, []);

    const detailBody = mode === 'detail' && detail ? (
        <div className="flex flex-col gap-4">
            <OrderStatusStepper status={detail.status} />
            <div className="flex items-center gap-2">
                <StatusBadge status={detail.status} compact />
                {detail.payment_status ? <PaymentBadge status={detail.payment_status} compact /> : null}
            </div>
            <div className="flex flex-col gap-1 rounded-xl border bg-muted/30 p-3 text-sm">
                <p><span className="text-muted-foreground">Pelanggan:</span> {detail.customer_name || '-'}</p>
                <p><span className="text-muted-foreground">Telepon:</span> {detail.customer_phone || '-'}</p>
                <p><span className="text-muted-foreground">Pengiriman:</span> {deliveryMethodLabel(detail.delivery_method, detail.delivery_distance_km)}</p>
                <p><span className="text-muted-foreground">Tanggal:</span> {detail.order_date?.slice(0, 10)}</p>
                <p className="font-semibold">Total: {formatCurrency(detail.grand_total)}</p>
            </div>

            <Tabs defaultValue="ringkasan">
                    <TabsList variant="segment">
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
                            {Number(detail.delivery_fee ?? 0) > 0 && (
                                <div className="flex justify-between"><span>Ongkir</span><span>{formatCurrency(detail.delivery_fee ?? 0)}</span></div>
                            )}
                            <Separator className="my-2" />
                            <div className="flex justify-between font-semibold"><span>Total</span><span>{formatCurrency(detail.grand_total)}</span></div>
                            {detailCashChangePayment ? (
                                <div className="flex justify-between text-primary"><span>Tunai</span><span>{formatCurrency(Number(detailCashChangePayment.tendered))}</span></div>
                            ) : (
                                <div className="flex justify-between text-primary"><span>Dibayar</span><span>{formatCurrency(detail.total_paid)}</span></div>
                            )}
                            {detail.remaining_amount != null && detail.remaining_amount > 0 && (
                                <div className="flex justify-between text-destructive"><span>Sisa</span><span>{formatCurrency(detail.remaining_amount)}</span></div>
                            )}
                            {detail.change_amount != null && detail.change_amount > 0 && (
                                <div className="flex justify-between text-success"><span>Kembalian</span><span>{formatCurrency(detail.change_amount)}</span></div>
                            )}
                        </CardContent>
                    </Card>
                    <div className="flex flex-wrap gap-2">
                        <Button size="sm" variant="outline" onClick={() => downloadInvoice(detail, true)}>Pratinjau PDF</Button>
                        <Button size="sm" onClick={() => downloadInvoice(detail, false)}>Unduh PDF</Button>
                        <Button size="sm" variant="outline" onClick={() => cetakStruk(detail)}>Cetak Struk</Button>
                    </div>
                    <div className="flex items-center justify-between gap-2 rounded-xl border bg-muted/30 p-3 text-sm">
                        <span className="min-w-0 truncate"><span className="text-muted-foreground">Staff:</span> {detail.user?.name ?? '-'}</span>
                        {isOwner && (
                            <Button size="sm" variant="ghost" className="shrink-0" onClick={() => openReassign()}>Tugaskan ulang</Button>
                        )}
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
                                    <div key={p.id} className="flex items-center justify-between gap-2 py-1">
                                        <span className="capitalize">{p.metode}</span>
                                        <div className="flex items-center gap-1">
                                            <span>{formatCurrency(p.amount)}</span>
                                            {canDeletePayments && (
                                                <button
                                                    type="button"
                                                    onClick={() => confirmDeletePayment(p)}
                                                    aria-label="Hapus pembayaran"
                                                    className="-mr-1 shrink-0 rounded-md p-1 text-muted-foreground hover:text-destructive"
                                                >
                                                    <Trash2 className="size-4" />
                                                </button>
                                            )}
                                        </div>
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
    ) : null;

    const paginationFooter = lastPage > 1 ? (
        <div className="flex flex-col items-center gap-2">
            {refreshing && (
                <p className="animate-pulse text-xs text-muted-foreground">Memuat...</p>
            )}
            <div className="flex items-center gap-2">
                <p className="text-xs text-muted-foreground">
                    {((page - 1) * perPage) + 1}–{Math.min(page * perPage, totalOrders)} dari {totalOrders}
                </p>
                <Select value={String(perPage)} onValueChange={(v) => { setPage(1); setPerPage(Number(v)); }}>
                    <SelectTrigger className="w-20" size="sm"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value="20">20</SelectItem>
                            <SelectItem value="50">50</SelectItem>
                            <SelectItem value="100">100</SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </div>
            <div className="flex justify-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={page <= 1}
                    onClick={() => {
                        setPage((p) => p - 1);
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }}
                >
                    Sebelumnya
                </Button>
                <span className="flex items-center text-sm text-muted-foreground">
                    {page} / {lastPage}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={page >= lastPage}
                    onClick={() => {
                        setPage((p) => p + 1);
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }}
                >
                    Berikutnya
                </Button>
            </div>
        </div>
    ) : null;

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

            {isDesktop && summary && (<OrderKpiStrip summary={summary} />)}

            <OrderListToolbar
                search={search}
                onSearchChange={(value) => {
                    setPage(1);
                    setSearch(value);
                }}
                statusFilter={statusFilter}
                onStatusChange={(value) => {
                    setPage(1);
                    setStatusFilter(value);
                }}
                monthFilter={monthFilter}
                onMonthChange={(value) => {
                    setPage(1);
                    setMonthFilter(value);
                }}
                yearFilter={yearFilter}
                onYearChange={(value) => {
                    setPage(1);
                    setYearFilter(value);
                }}
                sortFilter={sortFilter}
                onSortChange={(value) => {
                    setPage(1);
                    setSortFilter(value);
                }}
                statusCounts={summary?.status_counts}
            />

            <div {...bind}>
            <PullToRefreshIndicator pulling={pulling} refreshing={refreshing || pullRefreshing} />

            {isDesktop ? (
                <>
                    <OrderTable
                        orders={orders}
                        loading={loading}
                        isOwner={isOwner}
                        sort={sortFilter}
                        onSortChange={(s) => { setPage(1); setSortFilter(s); }}
                        onRowClick={(order) => order.status === 'draft' ? openEdit(order) : openDetail(order)}
                        onPayment={openPayment}
                        onAdvance={(order, nextStatus) => void advanceStatus(order, nextStatus)}
                        onDelete={confirmDelete}
                        advancing={advancing}
                    />
                    {paginationFooter}
                </>
            ) : loading ? (
                <div className="flex flex-col">
                    {Array.from({ length: 6 }).map((_, i) => (
                        <OrderRowSkeleton key={i} />
                    ))}
                </div>
            ) : orders.length === 0 ? (
                <Empty>
                    <EmptyTitle>Belum ada order</EmptyTitle>
                    <EmptyDescription>Buat order pertama Anda</EmptyDescription>
                </Empty>
            ) : (
                <div className="flex flex-col gap-3">
                <GroupedList>
                    {orders.map((order, index) => (
                        <div key={order.id}>
                            {index > 0 && <GroupedListDivider />}
                            <OrderRow
                                order={order}
                                isOwner={isOwner}
                                onEdit={(order) => order.status === 'draft' ? openEdit(order) : openDetail(order)}
                                onPayment={openPayment}
                                onCancel={quickCancel}
                                onDelete={confirmDelete}
                            />
                        </div>
                    ))}
                </GroupedList>
                {paginationFooter}
                </div>
            )}
            </div>

            {/* Order form / detail drawer */}
            {isDesktop ? (
                <OrderDesktopPanel
                    open={drawerOpen}
                    onOpenChange={setDrawerOpen}
                    mode={mode}
                    title={mode === 'detail' ? detail?.invoice_no : editing ? 'Ubah Pesanan' : 'Pesanan Baru'}
                    detailBody={detailBody}
                    detailFooter={mode === 'detail' && detail ? (
                        <>
                            <OrderStatusActions
                                status={detail.status}
                                loading={advancing}
                                onAdvance={(nextStatus) => advanceStatus(detail, nextStatus)}
                            />
                            <div className="flex gap-2">
                                {detail && canEditOrder(detail.status) && (
                                    <Button variant="outline" className="flex-1" onClick={() => openEdit(detail)}>Ubah</Button>
                                )}
                                {isPayableOrder(detail) && (
                                    <Button className="flex-1" onClick={() => { setDrawerOpen(false); openPayment(detail); }}>Bayar</Button>
                                )}
                            </div>
                        </>
                    ) : undefined}
                    formAside={(
                        <div className="flex flex-col gap-2 px-4 pt-1 pb-3">
                            {pendingDraft && (
                                <OrderDraftBanner
                                    onRestore={applyDraft}
                                    onDiscard={() => { void clearDraft(); }}
                                />
                            )}
                            {editing && form.status !== 'draft' && (
                                <div className="flex flex-col gap-2 rounded-xl border bg-muted/30 p-3">
                                    <OrderStatusStepper status={form.status || 'draft'} compact />
                                    <p className="text-xs text-muted-foreground">
                                        Status diubah dari tampilan detail pesanan.
                                    </p>
                                </div>
                            )}
                        </div>
                    )}
                    products={products}
                    cartQuantities={cartQuantities}
                    onAdd={addToCart}
                    cartItems={cartItems}
                    unitMap={unitMap}
                    total={formTotal}
                    form={form}
                    onFormChange={(patch) => setForm((prev) => ({ ...prev, ...patch }))}
                    onUpdateItem={updateCartItem}
                    onRemoveItem={removeCartItem}
                    onSave={() => { void save(); }}
                    onSubmit={submitAndSend}
                    canSubmit={hasValidItems(form.items)}
                    storedDeliveryFee={editing?.delivery_fee}
                    storedDeliveryKm={editing?.delivery_distance_km}
                />
            ) : (
                <Drawer open={drawerOpen} onOpenChange={setDrawerOpen} showSwipeHandle>
                    <DrawerContent className="max-h-[90vh]">
                        <DrawerHeader>
                            <DrawerTitle>
                                {mode === 'detail' ? detail?.invoice_no : editing ? 'Ubah Pesanan' : 'Pesanan Baru'}
                            </DrawerTitle>
                        </DrawerHeader>
                        {mode === 'detail' && detail ? (
                            <div className="overflow-y-auto px-4 pb-4 flex-1">
                                {detailBody}
                            </div>
                        ) : (
                            <>
                                <div className="shrink-0 px-4 pt-2 flex flex-col gap-2">
                                    {pendingDraft && (
                                        <OrderDraftBanner
                                            onRestore={applyDraft}
                                            onDiscard={() => { void clearDraft(); }}
                                        />
                                    )}
                                    {editing && form.status !== 'draft' && (
                                        <div className="flex flex-col gap-2 rounded-xl border bg-muted/30 p-3">
                                            <OrderStatusStepper status={form.status || 'draft'} compact />
                                            <p className="text-xs text-muted-foreground">
                                                Status diubah dari tampilan detail pesanan.
                                            </p>
                                        </div>
                                    )}
                                </div>
                                <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                                    <PosProductGrid products={products} cartQuantities={cartQuantities} onAdd={addToCart} />
                                </div>
                            </>
                        )}
                        <DrawerFooter className={mode === 'detail' ? 'flex-col gap-3' : undefined}>
                            {mode === 'detail' && detail ? (
                                <>
                                    <OrderStatusActions
                                        status={detail.status}
                                        loading={advancing}
                                        onAdvance={(nextStatus) => advanceStatus(detail, nextStatus)}
                                    />
                                    <div className="flex gap-2">
                                        {detail && canEditOrder(detail.status) && (
                                            <Button variant="outline" className="flex-1" onClick={() => openEdit(detail)}>Ubah</Button>
                                        )}
                                        {isPayableOrder(detail) && (
                                            <Button className="flex-1" onClick={() => { setDrawerOpen(false); openPayment(detail); }}>Bayar</Button>
                                        )}
                                    </div>
                                </>
                            ) : (
                                <CartBar count={cartCount} total={formTotal} onOpen={() => setReviewOpen(true)} />
                            )}
                        </DrawerFooter>
                        {mode === 'form' && (
                            <CartReviewSheet
                                open={reviewOpen}
                                onOpenChange={setReviewOpen}
                                cartItems={cartItems}
                                unitMap={unitMap}
                                total={formTotal}
                                form={form}
                                onFormChange={(patch) => setForm((prev) => ({ ...prev, ...patch }))}
                                onUpdateItem={updateCartItem}
                                onRemoveItem={removeCartItem}
                                onSave={() => { void save(); }}
                                onSubmit={submitAndSend}
                                canSubmit={hasValidItems(form.items)}
                                storedDeliveryFee={editing?.delivery_fee}
                                storedDeliveryKm={editing?.delivery_distance_km}
                            />
                        )}
                    </DrawerContent>
                </Drawer>
            )}

            {/* Payment panel */}
            <ResponsiveFormPanel
                open={paymentOpen}
                onOpenChange={setPaymentOpen}
                title="Tambah Pembayaran"
                footer={<Button className="w-full" onClick={savePayment}>Catat Pembayaran</Button>}
            >
                <div className="flex flex-col gap-4">
                    <FieldGroup>
                        <Field>
                            <FieldLabel>Jumlah</FieldLabel>
                            <Input type="number" min={1} value={mayarDynamicQr ? paymentRemaining.toFixed(2) : paymentForm.amount} readOnly={mayarDynamicQr} onChange={(e) => setPaymentForm({ ...paymentForm, amount: e.target.value })} />
                            {paymentRemaining > 0 && (
                                <button type="button" className="mt-1 w-fit rounded-full border bg-muted px-3 py-1 text-xs font-medium text-muted-foreground hover:bg-accent" onClick={() => setPaymentForm({ ...paymentForm, amount: paymentRemaining.toFixed(2) })}>Sisa {formatCurrency(paymentRemaining)}</button>
                            )}
                        </Field>
                        <Field>
                            <FieldLabel>Metode</FieldLabel>
                            <Select value={paymentForm.metode} onValueChange={(v) => {
                                setPaymentForm((prev) => ({ ...prev, metode: v, ...(v === 'transfer' ? { amount: paymentRemaining.toFixed(2) } : {}) }));
                            }}>
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
                        {paymentForm.metode === 'cash' && (
                            <Field>
                                <FieldLabel>Uang Diterima</FieldLabel>
                                <Input type="number" min={0} value={paymentForm.tendered} placeholder="Opsional" onChange={(e) => setPaymentForm({ ...paymentForm, tendered: e.target.value })} />
                                <button type="button" className="mt-1 w-fit rounded-full border bg-muted px-3 py-1 text-xs font-medium text-muted-foreground hover:bg-accent" onClick={() => setPaymentForm({ ...paymentForm, tendered: Number(paymentForm.amount || 0).toFixed(2) })}>Uang Pas</button>
                            </Field>
                        )}
                    </FieldGroup>
                    {paymentForm.metode === 'qris' && (
                        <div className="mt-4 flex flex-col items-center gap-2 rounded-xl border p-3">
                            {paymentSettings?.qris_image_url ? (
                                <>
                                    <img src={paymentSettings.qris_image_url} alt="QRIS" className="h-52 w-52 object-contain" />
                                    <p className="text-xs text-muted-foreground">Scan QRIS statis di atas</p>
                                </>
                            ) : paymentSettings?.mayar_enabled ? (
                                mayarLoading ? (
                                    <p className="py-8 text-sm text-muted-foreground">Membuat QR Mayar…</p>
                                ) : mayarQr?.qr_url ? (
                                    <>
                                        <img src={mayarQr.qr_url} alt="QRIS Mayar" className="h-52 w-52 object-contain" />
                                        <p className="text-xs text-muted-foreground">
                                            Scan untuk bayar {mayarQr.amount ? formatCurrency(mayarQr.amount) : ''}
                                        </p>
                                        <Button size="sm" variant="ghost" onClick={loadMayarQr}>Muat ulang QR</Button>
                                    </>
                                ) : (
                                    <p className="py-4 text-sm text-muted-foreground">QR belum tersedia.</p>
                                )
                            ) : (
                                <p className="py-2 text-center text-xs text-muted-foreground">
                                    QRIS belum dikonfigurasi.{' '}
                                    <a href="/settings/payment" className="font-medium text-foreground underline">Atur di Pengaturan</a>.
                                </p>
                            )}
                        </div>
                    )}
                    {paymentForm.metode === 'transfer' && (
                        <div className="mt-4 flex flex-col gap-2 rounded-xl border p-3">
                            {paymentSettings?.bank_account_number ? (
                                <>
                                    <p className="text-xs font-medium text-muted-foreground">Transfer ke rekening berikut</p>
                                    {paymentSettings.bank_name && (
                                        <div className="flex justify-between text-sm"><span className="text-muted-foreground">Bank</span><span className="font-medium">{paymentSettings.bank_name}</span></div>
                                    )}
                                    {paymentSettings.bank_account_name && (
                                        <div className="flex justify-between text-sm"><span className="text-muted-foreground">Atas Nama</span><span className="font-medium">{paymentSettings.bank_account_name}</span></div>
                                    )}
                                    <div className="flex items-center justify-between gap-2 text-sm">
                                        <span className="text-muted-foreground">No. Rekening</span>
                                        <div className="flex items-center gap-2">
                                            <span className="font-mono font-semibold">{paymentSettings.bank_account_number}</span>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => {
                                                    navigator.clipboard?.writeText(paymentSettings.bank_account_number ?? '');
                                                    haptic();
                                                    toast.success('Rekening disalin');
                                                }}
                                            >
                                                Salin
                                            </Button>
                                        </div>
                                    </div>
                                </>
                            ) : (
                                <p className="py-2 text-center text-xs text-muted-foreground">
                                    Rekening belum dikonfigurasi.{' '}
                                    <a href="/settings/payment" className="font-medium text-foreground underline">Atur di Pengaturan</a>.
                                </p>
                            )}
                        </div>
                    )}
                    {paymentForm.metode === 'cash' && paymentForm.amount && paymentChange > 0 && (
                        <div className="mt-3 flex justify-between rounded-lg bg-success/10 px-3 py-2 text-sm text-success">
                            <span>Kembalian</span>
                            <span className="font-semibold">{formatCurrency(paymentChange)}</span>
                        </div>
                    )}
                </div>
            </ResponsiveFormPanel>

            <PaymentSuccessSheet
                open={successOpen}
                onOpenChange={setSuccessOpen}
                order={paymentOrder}
                lastPayment={lastPayment}
                paymentSettings={paymentSettings}
                advancing={advancing}
                onPrintReceipt={() => { if (paymentOrder) void cetakStruk(paymentOrder); }}
                onDownloadInvoice={() => { if (paymentOrder) void downloadInvoice(paymentOrder, false); }}
                onAddPayment={() => { if (paymentOrder) { setSuccessOpen(false); openPayment(paymentOrder); } }}
                onAdvance={(nextStatus) => { if (paymentOrder) void advanceStatus(paymentOrder, nextStatus); }}
                onNewOrder={() => { setSuccessOpen(false); void openCreate(); }}
            />

            {/* Reassign panel */}
            <ResponsiveFormPanel
                open={reassignOpen}
                onOpenChange={setReassignOpen}
                title="Tugaskan ulang pesanan"
                description={detail ? `Pesanan ${detail.invoice_no}` : undefined}
                footer={
                    <div className="flex gap-2">
                        <Button variant="outline" className="flex-1" onClick={() => setReassignOpen(false)}>Batal</Button>
                        <Button
                            className="flex-1"
                            disabled={!reassignUserId || (!!detail && Number(reassignUserId) === detail.user_id)}
                            onClick={submitReassign}
                        >
                            Simpan
                        </Button>
                    </div>
                }
            >
                <FieldGroup>
                    <Field>
                        <FieldLabel>Pengguna baru</FieldLabel>
                        <Select value={reassignUserId} onValueChange={setReassignUserId} items={reassignItems}>
                            <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {reassignCandidates.map((u) => (
                                        <SelectItem key={u.id} value={String(u.id)}>
                                            {u.name} — {u.role === 'owner' ? 'Owner' : 'Staff'}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <p className="text-xs text-muted-foreground">
                            Semua pesanan milik pengguna saat ini akan dipindahkan ke pengguna baru.
                        </p>
                    </Field>
                </FieldGroup>
            </ResponsiveFormPanel>

            {/* Delete confirm */}
            <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus order?</AlertDialogTitle>
                        <AlertDialogDescription>
                            {deleteTarget && deleteTarget.status !== 'draft' && deleteTarget.status !== 'cancelled'
                                ? `${isOwner ? 'Pilih soft delete atau force delete permanen.' : 'Order akan di-soft delete.'} Stok produk akan dikembalikan.`
                                : isOwner ? 'Pilih soft delete atau force delete permanen.' : 'Order akan di-soft delete.'}
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

            {/* Payment delete confirm */}
            <AlertDialog open={paymentDeleteOpen} onOpenChange={setPaymentDeleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus pembayaran?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Pembayaran akan dihapus dari pesanan ini. Total dibayar, sisa, dan status pembayaran akan diperbarui.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDeletePayment} className="bg-destructive text-destructive-foreground">Hapus</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </Page>
    );
}
