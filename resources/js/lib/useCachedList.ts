import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import type { ApiClient } from '@/lib/api';
import type { PaginatedResponse } from '@/lib/types';

interface UseCachedListOptions {
    perPage?: number;
    debounceMs?: number;
}

interface UseCachedListResult<T> {
    data: T[];
    loading: boolean;
    refreshing: boolean;
    lastPage: number;
    total: number;
    refetch: () => Promise<void>;
    setData: (updater: T[] | ((prev: T[]) => T[])) => void;
}

function cacheKey(resource: string, params: Record<string, string | undefined>): string {
    const clean = Object.entries(params)
        .filter(([, v]) => v != null && v !== '')
        .sort((a, b) => a[0].localeCompare(b[0]))
        .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v!)}`)
        .join('&');
    return `${resource}?${clean}`;
}

/**
 * Generic paginated-list hook.
 *
 * Always fetches fresh data from the network (no stale cache render):
 *  - First load for a filter set shows the loading skeleton.
 *  - On filter changes the previous data stays visible (refreshing) until
 *    the fresh response lands — no stale-content flash, no skeleton churn.
 *
 * `loading` is true only when there is nothing to show yet.
 * `refreshing` is true during a background refetch while data is visible.
 */
export function useCachedList<T>(
    resource: string,
    api: ApiClient,
    params: Record<string, string | undefined>,
    options: UseCachedListOptions = {},
): UseCachedListResult<T> {
    const { perPage = 20, debounceMs = 300 } = options;
    const [data, setData] = useState<T[]>([]);
    const [lastPage, setLastPage] = useState(1);
    const [total, setTotal] = useState(0);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);

    // Serialise params so the effect key is a primitive string.
    const paramKey = cacheKey(resource, { ...params, per_page: String(perPage) });
    const searchValue = params.search ?? '';
    // Use refs so refetch() always reads the latest params without re-creating.
    const paramsRef = useRef(params);
    paramsRef.current = params;
    const resourceRef = useRef(resource);
    resourceRef.current = resource;
    const perPageRef = useRef(perPage);
    perPageRef.current = perPage;
    // Whether a fetch has completed for this list — drives the skeleton
    // decision so an empty result page doesn't re-flash skeletons later.
    const hasFetchedRef = useRef(false);
    // Bumped on every consumer-side setData (optimistic mutations). A fetch
    // that started BEFORE the latest bump may return pre-mutation data — its
    // response is discarded so it can't clobber the optimistic state.
    const mutationEpochRef = useRef(0);
    // Bumped whenever the fetch key (resource + params) changes. A fetch that
    // started BEFORE the latest bump belongs to an older filter/page set — its
    // response is discarded entirely so it can't overwrite newer results.
    const paramEpochRef = useRef(0);

    const doFetch = useCallback(async () => {
        const mutationEpochAtStart = mutationEpochRef.current;
        const paramEpochAtStart = paramEpochRef.current;
        let stale = false;
        try {
            const res = await api.list<T | T[] | PaginatedResponse<T>>(resourceRef.current, {
                ...paramsRef.current,
                per_page: String(perPageRef.current),
            });
            if (
                mutationEpochAtStart !== mutationEpochRef.current ||
                paramEpochAtStart !== paramEpochRef.current
            ) {
                // Stale response raced an optimistic mutation or a newer
                // filter/page fetch; discard it without touching state.
                stale = true;
                return;
            }
            // Normalize: support both paginated ({ data, total, last_page }) and
            // plain-array responses (products/categories return arrays, not paginated).
            const isPaginated =
                res != null && typeof res === 'object' && 'data' in res && 'total' in res;
            const list = isPaginated ? (res as PaginatedResponse<T>).data : (res as T[]);
            const total = isPaginated ? (res as PaginatedResponse<T>).total : list.length;
            const lastPage = isPaginated ? (res as PaginatedResponse<T>).last_page : 1;
            setData(list);
            hasFetchedRef.current = true;
            setLastPage(lastPage);
            setTotal(total);
        } catch (err) {
            if (
                mutationEpochAtStart !== mutationEpochRef.current ||
                paramEpochAtStart !== paramEpochRef.current
            ) {
                stale = true;
                return;
            }
            if (err instanceof Error && err.message !== 'UNAUTHENTICATED') {
                toast.error(err.message);
            }
        } finally {
            // A stale fetch must not clear the newer fetch's loading/refreshing
            // indicators — the newest in-flight fetch owns them.
            if (!stale) {
                setLoading(false);
                setRefreshing(false);
            }
        }
    }, [api]);

    // React to filter / page / search changes.
    useEffect(() => {
        // Invalidate any in-flight fetch from the previous param set before
        // scheduling the new one, so late responses can't overwrite newer data.
        paramEpochRef.current += 1;
        // Keep previous data visible while refreshing; skeleton only before
        // the first completed fetch (even if that fetch returned empty).
        if (hasFetchedRef.current) {
            setRefreshing(true);
        } else {
            setLoading(true);
            setRefreshing(false);
        }

        const delay = searchValue ? debounceMs : 0;
        const timer = setTimeout(() => {
            void doFetch();
        }, delay);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [paramKey]);

    const refetch = useCallback(async () => {
        setRefreshing(true);
        await doFetch();
    }, [doFetch]);

    // Consumer-facing setData: marks local state as newer than any in-flight
    // fetch so stale responses are discarded (optimistic-update support).
    const setDataExternal = useCallback((updater: T[] | ((prev: T[]) => T[])) => {
        mutationEpochRef.current += 1;
        setData(updater);
    }, []);

    return { data, loading, refreshing, lastPage, total, refetch, setData: setDataExternal };
}
