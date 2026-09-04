import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import type { ApiClient } from '@/lib/api';
import type { PaginatedResponse } from '@/lib/types';

/**
 * Bounded fetch-all for paginated list endpoints (products, suppliers,
 * categories, product-batches).
 *
 * These resources feed client-side grouping/filtering UIs (catalog grid
 * filters, category accordions, POS pickers, form dropdowns) that need the
 * complete dataset, but the API now paginates every list (per_page capped at
 * 100, mirroring /orders). Instead of one unbounded request, this helper walks
 * sequential pages of 100 rows and stops at MAX_PAGES, so each request stays
 * bounded while callers keep receiving a plain `T[]`.
 */
const PAGE_SIZE = 100;
const MAX_PAGES = 50;

export async function listAll<T>(
    api: ApiClient,
    resource: string,
    params: Record<string, string | undefined> = {},
): Promise<T[]> {
    const items: T[] = [];

    for (let page = 1; page <= MAX_PAGES; page++) {
        const res = await api.list<T[] | PaginatedResponse<T>>(resource, {
            ...params,
            page: String(page),
            per_page: String(PAGE_SIZE),
        });

        // Defensive: endpoint returned a legacy plain array — nothing more to walk.
        if (Array.isArray(res)) {
            return res;
        }

        items.push(...res.data);

        // A short page means the server has run out of rows — stop the walk
        // instead of requesting pages that can only come back empty.
        if (res.data.length < PAGE_SIZE) {
            break;
        }

        if (page >= res.last_page) {
            break;
        }
    }

    return items;
}

/**
 * `useCachedList`-shaped hook backed by `listAll`: full dataset via bounded
 * page walks, with the same `{data, loading, refreshing, refetch, setData}`
 * surface so panels that need every row keep their existing structure.
 */
export function useListAll<T>(
    resource: string,
    api: ApiClient,
    params: Record<string, string | undefined> = {},
) {
    const [data, setData] = useState<T[]>([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const hasDataRef = useRef(false);
    // Bumped whenever a new fetch starts or the consumer mutates data via
    // setData. An older page-walk that resolves after the latest bump is
    // discarded so it can't overwrite newer results/optimistic state.
    const epochRef = useRef(0);
    const paramsRef = useRef(params);
    paramsRef.current = params;
    // Serialise params so the effect key is a primitive string.
    const paramsKey = JSON.stringify(params);

    const refetch = useCallback(async () => {
        const epochAtStart = ++epochRef.current;
        setRefreshing(hasDataRef.current);
        try {
            const items = await listAll<T>(api, resource, paramsRef.current);
            if (epochAtStart !== epochRef.current) {
                // Stale walk raced a newer refetch/param change; discard it
                // without touching state or the loading/refreshing flags.
                return;
            }
            hasDataRef.current = items.length > 0;
            setData(items);
        } catch (err) {
            if (epochAtStart !== epochRef.current) {
                return;
            }
            if (err instanceof Error && err.message !== 'UNAUTHENTICATED') {
                toast.error(err.message);
            }
        } finally {
            if (epochAtStart === epochRef.current) {
                setLoading(false);
                setRefreshing(false);
            }
        }
    }, [api, resource]);

    useEffect(() => {
        void refetch();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [refetch, paramsKey]);

    // Consumer-facing setData: marks local state as newer than any in-flight
    // page-walk so stale responses are discarded (optimistic-update support).
    const setDataExternal = useCallback((updater: T[] | ((prev: T[]) => T[])) => {
        epochRef.current += 1;
        setData(updater);
    }, []);

    return { data, loading, refreshing, refetch, setData: setDataExternal };
}
