import { useCallback, useEffect, useState } from 'react';
import { useApi } from '@/lib/ApiProvider';
import type { StockAlertsResponse } from '@/lib/types';

const POLL_MS = 30_000;

export function useInventoryAlerts(enabled = true) {
    const { api } = useApi();
    const [alerts, setAlerts] = useState<StockAlertsResponse>({ count: 0, items: [] });
    const [loading, setLoading] = useState(true);

    const refresh = useCallback(async () => {
        if (!enabled) {
            setAlerts({ count: 0, items: [] });
            setLoading(false);
            return;
        }
        try {
            const data = await api.list<StockAlertsResponse>('inventory/alerts');
            setAlerts(data);
        } catch {
            // ignore polling errors
        } finally {
            setLoading(false);
        }
    }, [api, enabled]);

    useEffect(() => {
        refresh();
        if (!enabled) return;
        const id = window.setInterval(refresh, POLL_MS);
        return () => window.clearInterval(id);
    }, [refresh, enabled]);

    return { alerts, loading, refresh };
}
