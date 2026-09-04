import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    CreditCard,
    MoreHorizontal,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Empty, EmptyDescription, EmptyTitle } from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import { PaymentBadge } from '@/components/ios/PaymentBadge';
import { StatusBadge } from '@/components/ios/StatusBadge';
import { formatCurrency } from '@/lib/format';
import { getAdvanceActionLabel, getAdvanceStatuses } from '@/lib/orderStatus';
import type { Order } from '@/lib/types';
import { cn } from '@/lib/utils';

interface OrderTableProps {
    orders: Order[];
    loading?: boolean;
    isOwner: boolean;
    sort: string;
    onSortChange: (sort: string) => void;
    onRowClick: (order: Order) => void;
    onPayment: (order: Order) => void;
    onAdvance: (order: Order, nextStatus: string) => void;
    onDelete: (order: Order) => void;
    advancing?: boolean;
}

type SortColumn = 'invoice' | 'date' | 'amount';

const COLUMN_COUNT = 8;
const SKELETON_ROWS = 8;

const TH_CLASS = 'px-3 py-2.5 min-[1600px]:px-4 min-[1600px]:py-3 text-left text-xs font-medium tracking-wide uppercase text-muted-foreground';

interface SortableHeaderProps {
    label: string;
    column: SortColumn;
    sort: string;
    onSortChange: (sort: string) => void;
    align?: 'left' | 'right';
}

function SortableHeader({ label, column, sort, onSortChange, align = 'left' }: SortableHeaderProps) {
    const ascending = `${column}_asc`;
    const descending = `${column}_desc`;
    const isActive = sort === ascending || sort === descending;
    const ariaSort = sort === ascending ? 'ascending' : sort === descending ? 'descending' : undefined;
    const SortIcon = sort === ascending ? ArrowUp : sort === descending ? ArrowDown : ArrowUpDown;

    return (
        <th
            scope="col"
            className={align === 'right' ? `${TH_CLASS} text-right` : TH_CLASS}
            aria-sort={ariaSort}
        >
            <Button
                type="button"
                variant="ghost"
                size="sm"
                className={cn(
                    '-ml-2 text-xs font-medium tracking-wide uppercase',
                    isActive && 'text-primary hover:text-primary',
                )}
                onClick={() => onSortChange(sort === ascending ? descending : ascending)}
            >
                {label}
                <SortIcon aria-hidden className={isActive ? 'text-primary/70' : undefined} />
            </Button>
        </th>
    );
}

export function OrderTable({
    orders,
    loading = false,
    isOwner,
    sort,
    onSortChange,
    onRowClick,
    onPayment,
    onAdvance,
    onDelete,
    advancing = false,
}: OrderTableProps) {
    const isEmpty = !loading && orders.length === 0;

    return (
        <div className="max-[1599px]:overflow-x-auto rounded-xl border bg-card text-sm">
            {isEmpty ? (
                <Empty className="p-8">
                    <EmptyTitle>Belum ada order</EmptyTitle>
                    <EmptyDescription>Buat order pertama Anda</EmptyDescription>
                </Empty>
            ) : (
                <table className="w-full border-collapse">
                    <thead className="sticky top-0 z-10 bg-card">
                        <tr className="border-b">
                            <SortableHeader label="Invoice" column="invoice" sort={sort} onSortChange={onSortChange} />
                            <SortableHeader label="Tanggal" column="date" sort={sort} onSortChange={onSortChange} />
                            <th scope="col" className={TH_CLASS}>
                                Customer
                            </th>
                            <th scope="col" className={cn(TH_CLASS, 'hidden min-[1600px]:table-cell')}>
                                Item
                            </th>
                            <SortableHeader label="Total" column="amount" sort={sort} onSortChange={onSortChange} align="right" />
                            <th scope="col" className={TH_CLASS}>
                                Pembayaran
                            </th>
                            <th scope="col" className={TH_CLASS}>
                                Status
                            </th>
                            <th scope="col" className="w-0 px-3 py-2.5 min-[1600px]:px-4 min-[1600px]:py-3">
                                <span className="sr-only">Aksi</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {loading ? (
                            Array.from({ length: SKELETON_ROWS }).map((_, i) => (
                                <tr key={i} className="border-t">
                                    <td colSpan={COLUMN_COUNT} className="px-4 py-4">
                                        <Skeleton className="h-4 w-full" />
                                    </td>
                                </tr>
                            ))
                        ) : (
                            orders.map((order) => {
                                const itemNames = (order.items ?? [])
                                    .map((item) => item.product_name)
                                    .filter((name): name is string => Boolean(name));
                                const itemCount = order.items?.length ?? 0;
                                const itemSummary =
                                    itemNames.length > 0
                                        ? `${itemCount} item: ${itemNames.slice(0, 2).join(', ')}${
                                              itemNames.length > 2 ? ` +${itemNames.length - 2}` : ''
                                          }`
                                        : itemCount > 0
                                          ? `${itemCount} item`
                                          : '—';
                                const canPay =
                                    ['pending', 'diproses', 'dikirim'].includes(order.status) &&
                                    order.payment_status !== 'paid';
                                const nextStatus = getAdvanceStatuses(order.status)[0];
                                const hasDue = order.payment_status !== 'paid' && order.grand_total > order.total_paid;

                                return (
                                    <tr
                                        key={order.id}
                                        tabIndex={0}
                                        className="group cursor-pointer border-t hover:bg-accent/50 focus-visible:bg-accent/50 focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/30 focus-visible:ring-inset"
                                        onClick={() => onRowClick(order)}
                                        onKeyDown={(e) => {
                                            // Activate only when the row itself
                                            // holds focus, so Enter/Space on the
                                            // inner action buttons keeps firing
                                            // the button instead of the row.
                                            if (e.target !== e.currentTarget) return;
                                            if (e.key === 'Enter' || e.key === ' ') {
                                                e.preventDefault();
                                                onRowClick(order);
                                            }
                                        }}
                                    >
                                        <td className="px-3 py-2.5 min-[1600px]:px-4 min-[1600px]:py-3 font-mono font-semibold whitespace-nowrap">
                                            {order.invoice_no}
                                        </td>
                                        <td className="px-3 py-2.5 min-[1600px]:px-4 min-[1600px]:py-3 whitespace-nowrap text-muted-foreground">
                                            {order.order_date?.slice(0, 10)}
                                        </td>
                                        <td className="max-w-48 px-3 py-2.5 min-[1600px]:px-4 min-[1600px]:py-3">
                                            <span className="block truncate">{order.customer_name || '—'}</span>
                                        </td>
                                        <td className="hidden max-w-56 px-3 py-2.5 min-[1600px]:table-cell min-[1600px]:px-4 min-[1600px]:py-3">
                                            <span className="block truncate text-muted-foreground">{itemSummary}</span>
                                        </td>
                                        <td className="px-3 py-2.5 min-[1600px]:px-4 min-[1600px]:py-3 text-right whitespace-nowrap">
                                            <p className="font-semibold">{formatCurrency(order.grand_total)}</p>
                                            {hasDue && (
                                                <p className="text-xs text-destructive">
                                                    Sisa {formatCurrency(order.grand_total - order.total_paid)}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-3 py-2.5 min-[1600px]:px-4 min-[1600px]:py-3">
                                            {order.payment_status ? (
                                                <PaymentBadge status={order.payment_status} compact />
                                            ) : (
                                                <span className="text-muted-foreground">—</span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2.5 min-[1600px]:px-4 min-[1600px]:py-3">
                                            <StatusBadge status={order.status} compact />
                                        </td>
                                        <td className="px-3 py-2.5 min-[1600px]:px-4 min-[1600px]:py-3 text-right">
                                            <div className="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                                                {canPay && (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            onPayment(order);
                                                        }}
                                                    >
                                                        <CreditCard /> Bayar
                                                    </Button>
                                                )}
                                                {nextStatus && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        disabled={advancing}
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            onAdvance(order, nextStatus);
                                                        }}
                                                    >
                                                        {getAdvanceActionLabel(order.status, nextStatus)}
                                                    </Button>
                                                )}
                                                {isOwner && (
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            render={
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon-sm"
                                                                    aria-label="Aksi baris"
                                                                    onClick={(e) => e.stopPropagation()}
                                                                >
                                                                    <MoreHorizontal />
                                                                </Button>
                                                            }
                                                        />
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem
                                                                variant="destructive"
                                                                onClick={() => onDelete(order)}
                                                            >
                                                                Hapus
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            )}
        </div>
    );
}
