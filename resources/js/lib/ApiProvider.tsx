import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';
import { useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import type { TenantBranding, User } from '@/lib/types';

interface ApiContextValue {
    api: typeof api;
    user: User | null;
    tenant: TenantBranding | null;
    loading: boolean;
    refreshUser: () => Promise<void>;
    refreshTenant: () => Promise<void>;
    logout: () => Promise<void>;
}

const ApiContext = createContext<ApiContextValue | null>(null);

export function ApiProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(null);
    const [tenant, setTenant] = useState<TenantBranding | null>(null);
    const [loading, setLoading] = useState(true);
    const navigate = useNavigate();

    const handleSessionExpired = useCallback(() => {
        api.clearSession();
        setUser(null);
        setTenant(null);
        const path = window.location.pathname;
        if (path === '/login' || path === '/') {
            return;
        }
        toast.error('Sesi berakhir. Silakan masuk lagi.');
        navigate('/login', { replace: true });
    }, [navigate]);

    const refreshUser = useCallback(async () => {
        try {
            const data = await api.getCurrentUser();
            setUser(data.user);
            setTenant(data.tenant);
        } catch (err) {
            setUser(null);
            setTenant(null);
            if (err instanceof Error && err.message === 'UNAUTHENTICATED') {
                api.clearSession();
            }
        } finally {
            setLoading(false);
        }
    }, []);

    const refreshTenant = useCallback(async () => {
        if (!user) {
            return;
        }
        try {
            const t = await api.getTenantSettings();
            setTenant(t);
            api.cacheTenantBranding(t);
        } catch (err) {
            if (err instanceof Error && err.message === 'UNAUTHENTICATED') {
                handleSessionExpired();
            }
        }
    }, [handleSessionExpired, user]);

    useEffect(() => {
        void refreshUser();
    }, [refreshUser]);

    useEffect(() => {
        const onUnauthorized = () => handleSessionExpired();
        window.addEventListener('order-tracker:unauthorized', onUnauthorized);
        return () => window.removeEventListener('order-tracker:unauthorized', onUnauthorized);
    }, [handleSessionExpired]);

    const logout = useCallback(async () => {
        await api.logout();
        setUser(null);
        setTenant(null);
        navigate('/login');
    }, [navigate]);

    const value = useMemo(
        () => ({ api, user, tenant, loading, refreshUser, refreshTenant, logout }),
        [user, tenant, loading, refreshUser, refreshTenant, logout]
    );

    return <ApiContext.Provider value={value}>{children}</ApiContext.Provider>;
}

export function useApi() {
    const ctx = useContext(ApiContext);
    if (!ctx) throw new Error('useApi must be used within ApiProvider');
    return ctx;
}
