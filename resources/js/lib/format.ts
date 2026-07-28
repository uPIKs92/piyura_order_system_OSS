export function formatCurrency(amount: number | string): string {
    return 'Rp ' + Number(amount).toLocaleString('id-ID');
}

export function todayISO(): string {
    return new Date().toISOString().slice(0, 10);
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
