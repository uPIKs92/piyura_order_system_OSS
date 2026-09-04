import { useCallback, useRef, useState } from 'react';

const PULL_THRESHOLD = 80;

/**
 * Pull-to-refresh hook. Spread `{...bind}` on the scroll container element.
 *
 * `refetch` is stored in a ref and invoked after release, so it always calls
 * the latest callback without stale-closure issues. Pass a composite callback
 * (e.g. `() => Promise.all([refetchA(), refetchB()])`) when multiple sources
 * need refreshing.
 *
 * Lifted from the duplicated touch handler pattern that previously appeared in
 * Orders.tsx, ProductsPanel.tsx, CategoriesPanel.tsx, and StockPanel.tsx.
 *
 * Returns:
 * - `pulling`    — true while the finger has dragged past the threshold (show the hint)
 * - `refreshing` — true during the async refetch after release (show shimmer)
 * - `bind`       — onTouchStart/Move/End handlers for the container element
 */
export function usePullToRefresh(refetch: () => void | Promise<void>) {
    const pullStartY = useRef(0);
    const pullActive = useRef(false);
    const scrollerRef = useRef<HTMLElement | null>(null);
    const [pulling, setPulling] = useState(false);
    const [refreshing, setRefreshing] = useState(false);

    const refetchRef = useRef(refetch);
    refetchRef.current = refetch;

    const onTouchStart = useCallback((e: React.TouchEvent) => {
        pullStartY.current = e.touches[0].clientY;
        pullActive.current = false;
        // Cache once so touchmove doesn't DOM-walk every frame.
        scrollerRef.current = e.currentTarget.closest('main') as HTMLElement | null;
    }, []);

    const onTouchMove = useCallback((e: React.TouchEvent) => {
        if (pullActive.current) return;
        const delta = e.touches[0].clientY - pullStartY.current;
        if (delta <= PULL_THRESHOLD) return;
        if (scrollerRef.current && scrollerRef.current.scrollTop > 0) return;
        pullActive.current = true;
        setPulling(true);
    }, []);

    const onTouchEnd = useCallback(async () => {
        if (!pullActive.current) return;
        pullActive.current = false;
        setRefreshing(true);
        try {
            await refetchRef.current();
        } finally {
            setRefreshing(false);
            setPulling(false);
        }
    }, []);

    return {
        pulling,
        refreshing,
        bind: { onTouchStart, onTouchMove, onTouchEnd },
    };
}

/**
 * Standard pull-to-refresh indicator: a "Memuat ulang..." hint while the finger
 * is past the threshold, then a shimmer bar while the refetch runs.
 *
 * Pass the combined `refreshing` flag when a data hook (e.g. `useCachedList`)
 * also reports background revalidation, so a single shimmer covers both
 * pull-initiated and automatic refreshes:
 *
 *   <PullToRefreshIndicator pulling={pulling} refreshing={refreshing || pullRefreshing} />
 */
export function PullToRefreshIndicator({
    pulling,
    refreshing,
}: {
    pulling: boolean;
    refreshing: boolean;
}) {
    return (
        <>
            {pulling && (
                <p className="py-2 text-center text-xs text-muted-foreground">Memuat ulang...</p>
            )}
            {refreshing && !pulling && <div className="shimmer-bar mb-3" />}
        </>
    );
}
