import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { GroupedList, GroupedListDivider } from '@/components/ui/grouped-list';
import { IosListRow } from '@/components/ios/IosListRow';
import { ResponsiveFormPanel } from '@/components/ResponsiveFormPanel';
import { useApi } from '@/lib/ApiProvider';
import { formatCurrency, haptic } from '@/lib/format';
import type { ReceivableGroup, ReceivablesReport } from '@/lib/types';

const AGING_BADGE_CLASSES: Record<ReceivableGroup['aging_bucket'], string> = {
    '<=7': 'bg-secondary text-secondary-foreground',
    '8-14': 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
    '15-30': 'bg-orange-500/15 text-orange-700 dark:text-orange-400',
    '>30': 'bg-destructive/15 text-destructive',
};

const AGING_LABELS: Record<ReceivableGroup['aging_bucket'], string> = {
    '<=7': '≤7 hari',
    '8-14': '8–14 hari',
    '15-30': '15–30 hari',
    '>30': '>30 hari',
};

const PAYMENT_METHODS = [
    { value: 'cash', label: 'Cash' },
    { value: 'transfer', label: 'Transfer' },
    { value: 'qris', label: 'QRIS' },
] as const;

type PaymentMethod = (typeof PAYMENT_METHODS)[number]['value'];

function groupKey(group: ReceivableGroup): string {
    return String(group.customer_id ?? group.phone ?? group.name);
}

function AgingBadge({ bucket }: { bucket: ReceivableGroup['aging_bucket'] }) {
    return (
        <span className={`rounded-full px-2 py-0.5 text-[11px] font-medium ${AGING_BADGE_CLASSES[bucket]}`}>
            {AGING_LABELS[bucket]}
        </span>
    );
}

export function BukuBonPanel() {
    const { api } = useApi();
    const [report, setReport] = useState<ReceivablesReport | null>(null);
    const [loading, setLoading] = useState(true);
    const [selected, setSelected] = useState<ReceivableGroup | null>(null);
    const [sheetOpen, setSheetOpen] = useState(false);
    const [payingOrderId, setPayingOrderId] = useState<number | null>(null);
    const [payAmount, setPayAmount] = useState('');
    const [payMethod, setPayMethod] = useState<PaymentMethod>('cash');
    const [submitting, setSubmitting] = useState(false);

    const load = useCallback(async () => {
        try {
            const data = await api.request<ReceivablesReport>('/receivables');
            setReport(data);
            return data;
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memuat buku bon');
            return null;
        } finally {
            setLoading(false);
        }
    }, [api]);

    useEffect(() => {
        void load();
    }, [load]);

    function openDebtor(group: ReceivableGroup) {
        haptic();
        setSelected(group);
        setPayingOrderId(null);
        setSheetOpen(true);
    }

    function startPayment(orderId: number, dueAmount: number) {
        haptic();
        setPayingOrderId(orderId);
        setPayAmount(String(dueAmount));
        setPayMethod('cash');
    }

    async function submitPayment(orderId: number) {
        const amount = Number(payAmount);
        if (!payAmount || Number.isNaN(amount) || amount <= 0) {
            toast.error('Nominal bayar harus lebih dari 0');
            return;
        }
        if (submitting) return;
        setSubmitting(true);
        try {
            await api.request(`/orders/${orderId}/payments`, {
                method: 'POST',
                body: { amount, metode: payMethod },
            });
            haptic();
            toast.success('Pembayaran dicatat');
            setPayingOrderId(null);

            const updated = await load();
            const key = selected ? groupKey(selected) : null;
            const refreshed = key && updated
                ? updated.groups.find((group) => groupKey(group) === key)
                : undefined;

            if (refreshed) {
                setSelected(refreshed);
            } else {
                setSheetOpen(false);
                setSelected(null);
            }
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal mencatat pembayaran');
        } finally {
            setSubmitting(false);
        }
    }

    if (loading) {
        return <Skeleton className="h-16 w-full" />;
    }

    if (!report || report.totals.debtor_count === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Belum ada bon</CardTitle>
                    <CardDescription>
                        Buku Bon mencatat pelanggan yang masih memiliki sisa pembayaran. Bon tercatat
                        otomatis dari pesanan yang belum lunas — buat pesanan di tab Pesanan,
                        sisakan sisa pembayarannya, lalu catat pembayarannya di sini.
                    </CardDescription>
                </CardHeader>
            </Card>
        );
    }

    const totals = report.totals;

    return (
        <>
            <div className="grid grid-cols-2 gap-3">
                <Card>
                    <CardContent className="flex flex-col gap-1">
                        <p className="text-xs text-muted-foreground">Total Piutang</p>
                        <p className="text-xl font-bold tabular-nums">{formatCurrency(totals.unpaid_amount)}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="flex flex-col gap-1">
                        <p className="text-xs text-muted-foreground">Pesanan Belum Lunas</p>
                        <p className="text-xl font-bold tabular-nums">{totals.unpaid_count}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="flex flex-col gap-1">
                        <p className="text-xs text-muted-foreground">Jumlah Debitur</p>
                        <p className="text-xl font-bold tabular-nums">{totals.debtor_count}</p>
                    </CardContent>
                </Card>
            </div>

            <GroupedList>
                {report.groups.map((group, index) => (
                    <div key={groupKey(group)}>
                        {index > 0 && <GroupedListDivider />}
                        <IosListRow
                            title={group.name}
                            subtitle={group.phone || undefined}
                            onClick={() => openDebtor(group)}
                        >
                            <div className="flex items-center gap-2">
                                <AgingBadge bucket={group.aging_bucket} />
                                <span className="text-sm font-semibold tabular-nums">
                                    {formatCurrency(group.unpaid_amount)}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {group.unpaid_count} pesanan
                                </span>
                            </div>
                        </IosListRow>
                    </div>
                ))}
            </GroupedList>

            <p className="text-sm text-muted-foreground">
                Bon tercatat otomatis dari pesanan yang belum lunas. Buat pesanan di tab Pesanan,
                sisakan sisa pembayaran, lalu catat pembayarannya di sini.
            </p>

            <ResponsiveFormPanel
                open={sheetOpen}
                onOpenChange={setSheetOpen}
                title={selected ? `Bon ${selected.name}` : 'Buku Bon'}
                description={selected?.phone ?? undefined}
            >
                <GroupedList>
                    {selected?.orders.map((order, index) => (
                        <div key={order.id}>
                            {index > 0 && <GroupedListDivider />}
                            <div className="flex flex-col gap-2 p-3">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium">{order.order_date}</p>
                                        <p className="text-xs text-muted-foreground">
                                            Total {formatCurrency(order.grand_total)} · Sisa{' '}
                                            <span className="font-semibold tabular-nums">{formatCurrency(order.due_amount)}</span>
                                        </p>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => startPayment(order.id, order.due_amount)}
                                    >
                                        Catat Bayar
                                    </Button>
                                </div>
                                {payingOrderId === order.id && (
                                    <div className="flex flex-col gap-2 rounded-lg border bg-muted/40 p-2.5">
                                        <div className="grid grid-cols-2 gap-2">
                                            <Input
                                                type="number"
                                                inputMode="numeric"
                                                min={0}
                                                value={payAmount}
                                                onChange={(e) => setPayAmount(e.target.value)}
                                                placeholder="Nominal"
                                            />
                                            <Select
                                                value={payMethod}
                                                onValueChange={(value) => setPayMethod(value as PaymentMethod)}
                                                items={PAYMENT_METHODS.map((method) => ({ value: method.value, label: method.label }))}
                                            >
                                                <SelectTrigger className="w-full">
                                                    <SelectValue placeholder="Metode" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        {PAYMENT_METHODS.map((method) => (
                                                            <SelectItem key={method.value} value={method.value}>
                                                                {method.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                className="flex-1"
                                                onClick={() => setPayingOrderId(null)}
                                            >
                                                Batal
                                            </Button>
                                            <Button
                                                className="flex-1"
                                                disabled={submitting}
                                                onClick={() => submitPayment(order.id)}
                                            >
                                                {submitting ? 'Menyimpan...' : 'Simpan'}
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                </GroupedList>
            </ResponsiveFormPanel>
        </>
    );
}
