import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const paymentLabels: Record<string, string> = {
    unpaid: 'Belum bayar',
    partial: 'Sebagian',
    paid: 'Lunas',
    overpaid: 'Lebih bayar',
};

export function PaymentBadge({ status, compact }: { status: string; compact?: boolean }) {
    return (
        <Badge
            variant="outline"
            className={cn(compact && 'h-4 px-1.5 text-[10px]', `payment-${status}`)}
        >
            {paymentLabels[status] || status}
        </Badge>
    );
}
