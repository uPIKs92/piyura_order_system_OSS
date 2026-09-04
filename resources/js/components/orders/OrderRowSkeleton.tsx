import { Skeleton } from '@/components/ui/skeleton';

/**
 * Skeleton placeholder matching the OrderRow / IosListRow layout.
 * Used for initial page load (no cache) — renders a realistic row shape
 * instead of a generic grey block.
 */
export function OrderRowSkeleton() {
    return (
        <div className="flex w-full min-h-12 items-center gap-3 px-5 py-3.5 sm:px-6">
            <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                {/* Status badges */}
                <div className="flex items-center gap-1">
                    <Skeleton className="h-4 w-14 rounded-full" />
                    <Skeleton className="h-4 w-12 rounded-full" />
                </div>
                {/* Invoice number */}
                <Skeleton className="h-4 w-32" />
                {/* Customer name + date */}
                <Skeleton className="h-3 w-44" />
            </div>
            {/* Total price */}
            <div className="shrink-0">
                <Skeleton className="h-4 w-16" />
            </div>
        </div>
    );
}
