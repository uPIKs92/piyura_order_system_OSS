import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { STATUS_LABELS } from '@/lib/orderStatus';

export function StatusBadge({ status, compact }: { status: string; compact?: boolean }) {
    return (
        <Badge
            variant="outline"
            className={cn('capitalize', compact && 'h-4 px-1.5 text-[10px]', `status-${status}`)}
        >
            {STATUS_LABELS[status] || status}
        </Badge>
    );
}
