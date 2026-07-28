import type {
    AppBranding,
    AuthResponse,
    PaletteColor,
    TenantBranding,
    ThemeMode,
    User,
} from '@/lib/types';

const TENANT_CACHE_KEY = 'tenant_branding';

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

    async request<T = unknown>(endpoint: string, options: RequestInit = {}): Promise<T> {
        const url = '/api' + endpoint;
        const headers = await this.buildHeaders((options.headers as Record<string, string>) ?? {});
        const config: RequestInit = {
            credentials: 'include',
            headers,
            ...options,
        };

        if (config.body && typeof config.body === 'object' && !(config.body instanceof FormData)) {
            config.body = JSON.stringify(config.body);
        }

        const response = await fetch(url, config);

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

        this.sessionKnown = true;
        return data as T;
    }

    clearSession(): void {
        this.sessionKnown = false;
        this.clearTenantCache();
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

    async create<T = unknown>(resource: string, data: unknown): Promise<T> {
        return this.request<T>('/' + resource, { method: 'POST', body: data as BodyInit });
    }

    async update<T = unknown>(resource: string, id: number | string, data: unknown): Promise<T> {
        return this.request<T>('/' + resource + '/' + id, { method: 'PUT', body: data as BodyInit });
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
            const raw = localStorage.getItem(TENANT_CACHE_KEY);
            return raw ? (JSON.parse(raw) as TenantBranding) : null;
        } catch {
            return null;
        }
    }

    cacheTenantBranding(tenant: TenantBranding): void {
        localStorage.setItem(
            TENANT_CACHE_KEY,
            JSON.stringify({
                slug: tenant.slug,
                name: tenant.name,
                tagline: tenant.tagline,
                logo_url: tenant.logo_url,
            })
        );
    }

    clearTenantCache(): void {
        localStorage.removeItem(TENANT_CACHE_KEY);
    }

    async getTenantSettings(): Promise<TenantBranding> {
        return this.request<TenantBranding>('/settings/tenant', { method: 'GET' });
    }

    async updateTenantSettings(data: Partial<TenantBranding>): Promise<TenantBranding> {
        const tenant = await this.request<TenantBranding>('/settings/tenant', {
            method: 'PATCH',
            body: data as BodyInit,
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
            body: data as BodyInit,
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
}

export const api = new ApiClient();
export { ApiClient };
