import type { AppBranding, TenantBranding } from '@/lib/types';

declare global {
    interface Window {
        __APP_BRANDING__?: AppBranding;
        __TENANT_BRANDING__?: LoginTenantBranding | null;
    }
}

export type LoginTenantBranding = Pick<TenantBranding, 'slug' | 'name' | 'tagline' | 'logo_url'>;

/** Allow same-origin relative paths and HTTP(S) logo URLs only. */
export function safeLogoUrl(url?: string | null): string | undefined {
    if (!url) {
        return undefined;
    }

    if (url.startsWith('/')) {
        return url;
    }

    try {
        const parsed = new URL(url);
        if (parsed.protocol === 'https:') {
            return url;
        }
        if (parsed.protocol === 'http:' && import.meta.env.DEV) {
            return url;
        }
    } catch {
        return undefined;
    }

    return undefined;
}

const FALLBACK: AppBranding = {
    app_name: 'Simple Order Systems',
    platform_name: 'Piyuralabs',
    show_platform_credit_on_invoice: true,
};

export function getAppBranding(): AppBranding {
    if (typeof window !== 'undefined' && window.__APP_BRANDING__) {
        return window.__APP_BRANDING__;
    }

    return FALLBACK;
}

export function getLoginTenantBranding(): LoginTenantBranding | null {
    if (typeof window !== 'undefined' && window.__TENANT_BRANDING__) {
        return window.__TENANT_BRANDING__;
    }

    return null;
}
