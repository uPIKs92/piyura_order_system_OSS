export function marketingHomeUrl(): string {
    return '/';
}

export function tenantLoginUrl(_slug: string): string {
    return '/login';
}

export function tenantGateUrl(): string {
    return '/login';
}

export function isArrivedFromGate(_searchParams: URLSearchParams): boolean {
    return false;
}

export function clearGateContext(): void {
    // no-op in single-tenant edition
}

export function hasResolvedLoginTenant(): boolean {
    return true;
}

export function needsTenantGate(): boolean {
    return false;
}
