import { Skeleton } from '@/components/ui/skeleton';

/** Skeleton matching ProductTile shape (rounded-lg border card, animate-pulse). */
export function ProductTileSkeleton() {
    return (
        <div className="flex w-full flex-col gap-1 rounded-lg border p-2">
            <Skeleton className="h-4 w-3/4" />
            <Skeleton className="h-3 w-full" />
            <Skeleton className="h-3 w-2/3" />
            <div className="flex gap-1 pt-0.5">
                <Skeleton className="h-4 w-12 rounded" />
            </div>
        </div>
    );
}
