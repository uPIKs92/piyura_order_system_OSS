import { CircleCheck, FileDown, Plus, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ResponsiveFormPanel } from '@/components/ResponsiveFormPanel';
import { OrderStatusActions } from '@/components/orders/OrderStatusActions';
import { PaymentBadge } from '@/components/ios/PaymentBadge';
import { StatusBadge } from '@/components/ios/StatusBadge';
import { formatCurrency } from '@/lib/format';
import { isTerminal } from '@/lib/orderStatus';
import type { Order, Payment, PaymentSettings } from '@/lib/types';

interface PaymentSuccessSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    order: Order | null;
    lastPayment: Payment | null;
    paymentSettings?: PaymentSettings | null;
    advancing?: boolean;
    onPrintReceipt: () => void;
    onDownloadInvoice: () => void;
    onAddPayment: () => void;
    onAdvance: (nextStatus: string) => void;
    onNewOrder: () => void;
}

const paymentMethodLabels: Record<string, string> = {
    cash: 'Cash',
    transfer: 'Transfer',
    qris: 'QRIS',
};

function formatPaymentMethod(metode: string): string {
    return paymentMethodLabels[metode] ?? metode.charAt(0).toUpperCase() + metode.slice(1);
}

export function PaymentSuccessSheet({
    open,
    onOpenChange,
    order,
    lastPayment,
    paymentSettings = null,
    advancing = false,
    onPrintReceipt,
    onDownloadInvoice,
    onAddPayment,
    onAdvance,
    onNewOrder,
}: PaymentSuccessSheetProps) {
    if (!order) return null;

    const remaining = order.remaining_amount ?? 0;
    const changeAmount = lastPayment?.change_amount ?? order.change_amount ?? 0;
    const fullyPaid = order.payment_status === 'paid' || remaining <= 0;
    // Cash overpay: show the buyer's handed-over money (Tunai), not the
    // credited amount, so it can't be confused with what should be paid.
    const cashOverpaid = lastPayment != null
        && ['cash', 'tunai'].includes((lastPayment.metode || '').toLowerCase())
        && Number(lastPayment.tendered ?? 0) > Number(lastPayment.amount ?? 0);

    return (
        <ResponsiveFormPanel
            open={open}
            onOpenChange={onOpenChange}
            title={
                <div className="flex flex-col gap-2">
                    <div className="flex flex-col items-center gap-2 md:flex-row md:gap-3">
                        <div className="flex size-11 shrink-0 items-center justify-center rounded-full bg-success/10 text-success">
                            <CircleCheck className="size-6" />
                        </div>
                        <div className="flex flex-col items-center gap-0.5 md:items-start">
                            <p className="text-sm font-medium text-success">Pembayaran Dicatat</p>
                            <span className="text-lg font-semibold leading-none tracking-tight">{order.invoice_no}</span>
                        </div>
                    </div>
                    <div className="flex items-center justify-center gap-2 pt-1 md:justify-start">
                        <StatusBadge status={order.status} compact />
                        {order.payment_status ? <PaymentBadge status={order.payment_status} compact /> : null}
                    </div>
                </div>
            }
            footer={
                <>
                    <Button className="w-full" onClick={onPrintReceipt}>
                        <Printer />
                        Cetak Struk
                    </Button>
                    <Button variant="outline" className="w-full" onClick={onDownloadInvoice}>
                        <FileDown />
                        Unduh Invoice PDF
                    </Button>
                    {fullyPaid && !isTerminal(order.status) && (
                        <OrderStatusActions
                            status={order.status}
                            onAdvance={onAdvance}
                            loading={advancing}
                            layout="inline"
                        />
                    )}
                    <Button variant="outline" className="w-full" onClick={onNewOrder}>
                        <Plus />
                        Pesanan Baru
                    </Button>
                    <Button variant="ghost" className="w-full" onClick={() => onOpenChange(false)}>
                        Tutup
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-3">
                <div className="flex flex-col gap-1 rounded-xl border bg-muted/30 p-3 text-sm">
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Total</span>
                        <span className="font-medium">{formatCurrency(order.grand_total)}</span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">
                            {cashOverpaid
                                ? 'Tunai'
                                : `Dibayar${lastPayment ? ` (${formatPaymentMethod(lastPayment.metode)})` : ''}`}
                        </span>
                        <span className="font-semibold text-primary">
                            {formatCurrency(cashOverpaid ? Number(lastPayment?.tendered) : (lastPayment?.amount ?? order.total_paid))}
                        </span>
                    </div>
                    {changeAmount > 0 && (
                        <div className="mt-2 flex items-center justify-between rounded-lg bg-success/10 px-3 py-2">
                            <span className="text-sm text-success">Kembalian</span>
                            <span className="text-base font-bold text-success">
                                {formatCurrency(changeAmount)}
                            </span>
                        </div>
                    )}
                    {remaining > 0 && (
                        <div className="mt-2 flex items-center justify-between rounded-lg bg-destructive/10 px-3 py-2 text-destructive">
                            <span className="text-sm font-medium">Sisa</span>
                            <span className="text-base font-bold">{formatCurrency(remaining)}</span>
                        </div>
                    )}
                </div>

                {remaining > 0 && (
                    <Button variant="outline" className="w-full" onClick={onAddPayment}>
                        Tambah Pembayaran
                    </Button>
                )}
            </div>
        </ResponsiveFormPanel>
    );
}
