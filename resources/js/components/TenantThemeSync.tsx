import { useEffect } from 'react';
import { useTheme } from 'next-themes';
import { useApi } from '@/lib/ApiProvider';
import { applyTenantAppearance } from '@/lib/theme';

export function TenantThemeSync() {
    const { tenant } = useApi();
    const { setTheme } = useTheme();

    useEffect(() => {
        if (!tenant) return;
        applyTenantAppearance(tenant, setTheme);
    }, [tenant, setTheme]);

    return null;
}
