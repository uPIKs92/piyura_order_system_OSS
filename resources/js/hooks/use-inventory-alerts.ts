import { useCallback, useEffect, useState } from 'react';
import { useApi } from '@/lib/ApiProvider';
import type { ExpiryAlertsResponse, StockAlertsResponse } from '@/lib/types';

const POLL_MS = 30_000;

export function useInventoryAlerts(enabled = true) {
    const { api } = useApi();
    const [alerts, setAlerts] = useState<StockAlertsResponse>({ count: 0, items: [] });
    const [expiry, setExpiry] = useState<ExpiryAlertsResponse | null>(null);
    const [loading, setLoading] = useState(true);

    const refresh = useCallback(async () => {
        if (!enabled) {
            setAlerts({ count: 0, items: [] });
            setExpiry(null);
            setLoading(false);
            return;
        }
        try {
            const [stockData, expiryData] = await Promise.all([
                api.list<StockAlertsResponse>('inventory/alerts'),
                api.list<ExpiryAlertsResponse>('inventory/expiry-alerts').catch(() => null),
            ]);
            setAlerts(stockData);
            setExpiry(expiryData);
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

    return {
        alerts,
        expiry,
        expiredCount: expiry?.expired_count ?? 0,
        nearExpiryCount: expiry?.near_expiry_count ?? 0,
        loading,
        refresh,
    };
}
