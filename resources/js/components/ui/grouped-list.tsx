import { cn } from '@/lib/utils';
import { Separator } from '@/components/ui/separator';

export function GroupedList({ className, children }: { className?: string; children: React.ReactNode }) {
    return <div className={cn('overflow-hidden rounded-2xl border bg-card', className)}>{children}</div>;
}

export function GroupedListDivider() {
    return <Separator />;
}
