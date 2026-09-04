import { useRef } from 'react';
import { Ban, CreditCard } from 'lucide-react';
import { IosSwipeRow } from '@/components/ios/IosSwipeRow';
import { IosListRow } from '@/components/ios/IosListRow';
import { StatusBadge } from '@/components/ios/StatusBadge';
import { PaymentBadge } from '@/components/ios/PaymentBadge';
import { useLongPress } from '@/hooks/use-long-press';
import { canCancel } from '@/lib/orderStatus';
import { formatCurrency, haptic } from '@/lib/format';
import type { Order } from '@/lib/types';

interface OrderRowProps {
    order: Order;
    isOwner: boolean;
    onEdit: (order: Order) => void;
    onPayment: (order: Order) => void;
    onCancel: (order: Order) => void;
    onDelete: (order: Order) => void;
}

export function OrderRow({ order, isOwner, onEdit, onPayment, onCancel, onDelete }: OrderRowProps) {
    const canPay = ['pending', 'diproses', 'dikirim'].includes(order.status) && order.payment_status !== 'paid';
    const cancellable = canCancel(order.status);
    const longPressFired = useRef(false);

    // Long-press → delete (owner only). Cancels on >10px move (scroll/swipe).
    // longPressFired suppresses the click that follows pointerup after a hold,
    // so tap→edit and hold→delete don't both fire.
    const longPress = useLongPress(() => {
        if (!isOwner) return;
        longPressFired.current = true;
        haptic();
        onDelete(order);
    });

    function handleClick() {
        if (longPressFired.current) {
            longPressFired.current = false;
            return;
        }
        haptic();
        onEdit(order);
    }

    const itemNames = (order.items ?? [])
        .map((item) => item.product_name)
        .filter((name): name is string => Boolean(name));
    const itemCount = order.items?.length ?? 0;
    const subtitle =
        itemNames.length > 0
            ? `${itemCount} item: ${itemNames.slice(0, 2).join(', ')}${
                  itemNames.length > 2 ? ` +${itemNames.length - 2}` : ''
              }`
            : `${order.customer_name || 'No name'} • ${order.order_date?.slice(0, 10)}${
                  itemCount > 0 ? ` • ${itemCount} item` : ''
              }`;

    return (
        <IosSwipeRow
            onSwipeLeft={() => {
                if (canPay) onPayment(order);
            }}
            onSwipeRight={() => {
                if (cancellable) onCancel(order);
            }}
            leftActions={
                canPay ? (
                    <button
                        type="button"
                        onClick={() => onPayment(order)}
                        className="action-pay flex h-full items-center gap-1.5 px-5 text-xs font-semibold"
                    >
                        <CreditCard className="size-4 shrink-0" />
                        Bayar
                    </button>
                ) : undefined
            }
            rightActions={
                cancellable ? (
                    <button
                        type="button"
                        onClick={() => onCancel(order)}
                        className="action-cancel flex h-full items-center gap-1.5 px-5 text-xs font-semibold"
                    >
                        <Ban className="size-4 shrink-0" />
                        Batal
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
                subtitle={subtitle}
                onClick={handleClick}
                trailing={
                    <p className="text-sm font-semibold">{formatCurrency(order.grand_total)}</p>
                }
                {...longPress}
            />
        </IosSwipeRow>
    );
}
