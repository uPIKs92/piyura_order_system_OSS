export function formatCurrency(amount: number | string): string {
    return 'Rp ' + Number(amount).toLocaleString('id-ID');
}

// Local (browser timezone) Y-m-d — NOT toISOString(), which is UTC and
// yields yesterday's date for WIB users between 00:00 and 06:59.
export function todayISO(): string {
    return daysAgoISO(0);
}

export function daysAgoISO(days: number): string {
    const d = new Date();
    d.setDate(d.getDate() - days);
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${mm}-${dd}`;
}

export function haptic(pattern: number | number[] = 50): void {
    if (navigator.vibrate) {
        navigator.vibrate(pattern);
    }
}

export function formatActivityDiff(properties?: Record<string, unknown>): string | null {
    if (!properties) return null;

    const old = properties.old as Record<string, unknown> | undefined;
    const attrs = properties.attributes as Record<string, unknown> | undefined;

    if (!old && !attrs) {
        if (properties.from && properties.to) {
            return `${properties.from} → ${properties.to}`;
        }
        return null;
    }

    const keys = new Set([...Object.keys(old ?? {}), ...Object.keys(attrs ?? {})]);
    const parts: string[] = [];

    keys.forEach((key) => {
        const from = old?.[key];
        const to = attrs?.[key];
        if (from !== undefined && to !== undefined && from !== to) {
            parts.push(`${key}: ${String(from)} → ${String(to)}`);
        }
    });

    return parts.length ? parts.join(', ') : null;
}
