import type {
    AppBranding,
    AuthResponse,
    MayarLinkResponse,
    MayarTestResponse,
    PaletteColor,
    PaymentSettings,
    TenantBranding,
    ThemeMode,
    User,
} from '@/lib/types';

const TENANT_CACHE_KEY = 'tenant_branding';

/**
 * Branding cache is scoped per host: one tenant per (sub)domain, so a cached
 * entry must never leak into another host's login screen.
 */
function tenantCacheKey(): string {
    return `${TENANT_CACHE_KEY}:${window.location.hostname}`;
}

function readCsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : null;
}

async function ensureCsrfCookie(): Promise<void> {
    await fetch('/sanctum/csrf-cookie', {
        credentials: 'include',
        headers: { Accept: 'application/json' },
    });
}

/**
 * Request options with a JSON-friendly body: `request()` JSON.stringifies
 * plain objects, so callers may pass objects directly instead of pre-serializing.
 */
type RequestOptions = Omit<RequestInit, 'body'> & {
    body?: BodyInit | object;
};

type TenantSettingsWithOrigin = TenantBranding & {
    latitude?: number | null;
    longitude?: number | null;
};

class ApiClient {
    private sessionKnown = false;

    private async buildHeaders(extra: Record<string, string> = {}, json = true): Promise<Record<string, string>> {
        const headers: Record<string, string> = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...extra,
        };

        if (json) {
            headers['Content-Type'] = 'application/json';
        }

        const csrf = readCsrfToken();
        if (csrf) {
            headers['X-XSRF-TOKEN'] = csrf;
        }

        return headers;
    }

    private async handleUnauthorized(): Promise<never> {
        this.sessionKnown = false;
        this.clearTenantCache();
        window.dispatchEvent(new Event('order-tracker:unauthorized'));
        throw new Error('UNAUTHENTICATED');
    }

    async requestBlob(endpoint: string, options: RequestInit = {}): Promise<Blob> {
        const url = '/api' + endpoint;
        const response = await fetch(url, {
            credentials: 'include',
            headers: await this.buildHeaders((options.headers as Record<string, string>) ?? {}, false),
            ...options,
        });

        if (response.status === 401) {
            return this.handleUnauthorized();
        }

        if (!response.ok) {
            throw new Error('Request failed');
        }

        return response.blob();
    }

    async upload<T = unknown>(endpoint: string, formData: FormData): Promise<T> {
        const url = '/api' + endpoint;
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'include',
            headers: await this.buildHeaders({}, false),
            body: formData,
        });

        if (response.status === 401) {
            return this.handleUnauthorized();
        }

        const data = await response.json();

        if (!response.ok) {
            let message = data.message || 'Request failed';
            if (data.errors) {
                message = Object.values(data.errors as Record<string, string[]>).flat().join('\n');
            }
            throw new Error(message);
        }

        return data as T;
    }

    async request<T = unknown>(endpoint: string, options: RequestOptions = {}): Promise<T> {
        const url = '/api' + endpoint;
        const headers = await this.buildHeaders((options.headers as Record<string, string>) ?? {});
        const { body, ...rest } = options;
        const config: RequestInit = {
            credentials: 'include',
            headers,
            ...rest,
        };
        if (body !== undefined && body !== null) {
            config.body = body as BodyInit;
        }

        if (config.body && typeof config.body === 'object' && !(config.body instanceof FormData)) {
            config.body = JSON.stringify(config.body);
        }

        const response = await fetch(url, config);

        if (response.status === 401) {
            return this.handleUnauthorized();
        }

        const data = await response.json();

        if (!response.ok) {
            // Some endpoints (e.g. delivery estimate) reject with {reason}.
            let message = data.message || data.reason || 'Request failed';
            if (data.errors) {
                message = Object.values(data.errors as Record<string, string[]>).flat().join('\n');
            }
            throw new Error(message);
        }

        this.sessionKnown = true;
        return data as T;
    }

    clearSession(): void {
        this.sessionKnown = false;
        this.clearTenantCache();
        // Drop cached API responses (orders etc.) so the next session on this
        // device never serves the previous user's data from the SW cache.
        if (typeof navigator !== 'undefined' && 'serviceWorker' in navigator) {
            navigator.serviceWorker.controller?.postMessage({ type: 'CLEAR_API_CACHE' });
        }
    }

    isAuthenticated(): boolean {
        return this.sessionKnown;
    }

    markAuthenticated(): void {
        this.sessionKnown = true;
    }

    async list<T = unknown>(resource: string, params: Record<string, string | undefined> = {}): Promise<T> {
        const query = new URLSearchParams(
            Object.fromEntries(Object.entries(params).filter(([, v]) => v !== undefined && v !== '')) as Record<string, string>
        ).toString();
        const endpoint = '/' + resource + (query ? '?' + query : '');
        return this.request<T>(endpoint, { method: 'GET' });
    }

    async get<T = unknown>(resource: string, id: number | string): Promise<T> {
        return this.request<T>('/' + resource + '/' + id, { method: 'GET' });
    }

    async create<T = unknown>(resource: string, data: object): Promise<T> {
        return this.request<T>('/' + resource, { method: 'POST', body: data });
    }

    async update<T = unknown>(resource: string, id: number | string, data: object): Promise<T> {
        return this.request<T>('/' + resource + '/' + id, { method: 'PUT', body: data });
    }

    async destroy<T = unknown>(resource: string, id: number | string, params?: Record<string, string>): Promise<T> {
        const query = params ? '?' + new URLSearchParams(params).toString() : '';
        return this.request<T>('/' + resource + '/' + id + query, { method: 'DELETE' });
    }

    async login(email: string, password: string, deviceName = 'api-token'): Promise<AuthResponse> {
        await ensureCsrfCookie();

        const response = await fetch('/api/login', {
            method: 'POST',
            credentials: 'include',
            headers: await this.buildHeaders(),
            body: JSON.stringify({
                email,
                password,
                device_name: deviceName,
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            let message = data.message || 'Login failed';
            if (data.errors) {
                message = Object.values(data.errors as Record<string, string[]>).flat().join('\n');
            }
            throw new Error(message);
        }

        this.markAuthenticated();
        if (data.tenant) {
            this.cacheTenantBranding(data.tenant);
        }

        return data as AuthResponse;
    }

    async logout(): Promise<void> {
        try {
            await this.request('/logout', { method: 'POST' });
        } finally {
            this.clearSession();
        }
    }

    async getCurrentUser(): Promise<AuthResponse> {
        const data = await this.request<AuthResponse>('/user', { method: 'GET' });
        if (data.tenant) {
            this.cacheTenantBranding(data.tenant);
        }
        return data;
    }

    getCachedTenantBranding(): TenantBranding | null {
        try {
            const raw = localStorage.getItem(tenantCacheKey());
            if (raw) return JSON.parse(raw) as TenantBranding;
            // Evict the pre-scoping key so a stale cross-tenant branding
            // never resurfaces on hosts without server-injected branding.
            localStorage.removeItem(TENANT_CACHE_KEY);
            return null;
        } catch {
            return null;
        }
    }

    cacheTenantBranding(tenant: TenantBranding): void {
        localStorage.setItem(
            tenantCacheKey(),
            JSON.stringify({
                slug: tenant.slug,
                name: tenant.name,
                tagline: tenant.tagline,
                logo_url: tenant.logo_url,
            })
        );
    }

    clearTenantCache(): void {
        localStorage.removeItem(tenantCacheKey());
        localStorage.removeItem(TENANT_CACHE_KEY);
    }

    async getTenantSettings(): Promise<TenantSettingsWithOrigin> {
        return this.request<TenantSettingsWithOrigin>('/settings/tenant', { method: 'GET' });
    }

    async updateTenantSettings(
        data: Partial<TenantBranding> & { latitude?: number | null; longitude?: number | null },
    ): Promise<TenantSettingsWithOrigin> {
        const tenant = await this.request<TenantSettingsWithOrigin>('/settings/tenant', {
            method: 'PATCH',
            body: data,
        });
        this.cacheTenantBranding(tenant);
        return tenant;
    }

    async uploadTenantLogo(file: File): Promise<TenantBranding> {
        const formData = new FormData();
        formData.append('logo', file);
        const tenant = await this.upload<TenantBranding>('/settings/tenant/logo', formData);
        this.cacheTenantBranding(tenant);
        return tenant;
    }

    async deleteTenantLogo(): Promise<TenantBranding> {
        const tenant = await this.request<TenantBranding>('/settings/tenant/logo', { method: 'DELETE' });
        this.cacheTenantBranding(tenant);
        return tenant;
    }

    async updateTenantAppearance(data: {
        theme_mode: ThemeMode;
        theme_palette: PaletteColor;
    }): Promise<TenantBranding> {
        const tenant = await this.request<TenantBranding>('/settings/tenant/appearance', {
            method: 'PATCH',
            body: data,
        });
        this.cacheTenantBranding(tenant);
        return tenant;
    }

    async getAppBranding(): Promise<AppBranding> {
        return this.request<AppBranding>('/settings/app', { method: 'GET' });
    }

    async exportTenantData(): Promise<Record<string, unknown>> {
        return this.request<Record<string, unknown>>('/tenant/export', { method: 'GET' });
    }

    async getPaymentSettings(): Promise<PaymentSettings> {
        return this.request<PaymentSettings>('/settings/payment', { method: 'GET' });
    }

    async updatePaymentSettings(data: Partial<PaymentSettings> & { mayar_api_key?: string }): Promise<PaymentSettings> {
        return this.request<PaymentSettings>('/settings/payment', {
            method: 'PATCH',
            body: data,
        });
    }

    async uploadQris(file: File): Promise<PaymentSettings> {
        const formData = new FormData();
        formData.append('qris', file);
        return this.upload<PaymentSettings>('/settings/payment/qris', formData);
    }

    async deleteQris(): Promise<PaymentSettings> {
        return this.request<PaymentSettings>('/settings/payment/qris', { method: 'DELETE' });
    }

    async testMayar(): Promise<MayarTestResponse> {
        return this.request<MayarTestResponse>('/settings/mayar/test', { method: 'GET' });
    }

    async createMayarLink(orderId: number): Promise<MayarLinkResponse> {
        return this.request<MayarLinkResponse>(`/orders/${orderId}/mayar/link`, { method: 'POST' });
    }
}

export const api = new ApiClient();
export { ApiClient };
