import presetFonts from '@/lib/theme-preset-fonts.json';

export const THEME_STORAGE_KEY = 'theme';
export const PALETTE_STORAGE_KEY = 'order-tracker-palette';
/** @deprecated migrated to PALETTE_STORAGE_KEY */
export const ACCENT_STORAGE_KEY = 'order-tracker-accent';

const PALETTE_FONT_LINK_ID = 'palette-google-fonts';

function buildGoogleFontsHref(queries: string[]): string | null {
    if (queries.length === 0) {
        return null;
    }

    const familyParams = queries.map((query) => `family=${query}`).join('&');

    return `https://fonts.googleapis.com/css2?${familyParams}&display=swap`;
}

function applyPaletteFonts(color: PaletteColor): void {
    const existing = document.getElementById(PALETTE_FONT_LINK_ID);
    existing?.remove();

    if (color === 'neutral') {
        return;
    }

    const families = presetFonts[color as keyof typeof presetFonts] ?? [];
    const href = buildGoogleFontsHref(families);
    if (!href) {
        return;
    }

    const link = document.createElement('link');
    link.id = PALETTE_FONT_LINK_ID;
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
}

export type BasicPaletteColor = 'neutral' | 'blue' | 'green' | 'orange' | 'violet';

export type PresetPaletteColor =
    | 'luma-lime'
    | 'sera-taupe'
    | 'lyra-zinc'
    | 'vega-emerald'
    | 'luma-teal'
    | 'luma-neutral'
    | 'nova-mauve'
    | 'sera-amber'
    | 'luma-yellow'
    | 'vega-sky'
    | 'nova-violet'
    | 'luma-red';

export type PaletteColor = BasicPaletteColor | PresetPaletteColor;

export type ThemeMode = 'light' | 'dark' | 'system';

export type PaletteGroup = 'basic' | 'preset';

export type PaletteOption = {
    value: PaletteColor;
    label: string;
    preview: string;
    surface: string;
    group: PaletteGroup;
};

export const basicPaletteOptions: PaletteOption[] = [
    {
        value: 'neutral',
        label: 'Netral',
        preview: 'oklch(0.45 0 0)',
        surface: 'oklch(0.94 0 0)',
        group: 'basic',
    },
    {
        value: 'blue',
        label: 'Biru',
        preview: 'oklch(0.546 0.245 262.881)',
        surface: 'oklch(0.96 0.02 264)',
        group: 'basic',
    },
    {
        value: 'green',
        label: 'Hijau',
        preview: 'oklch(0.527 0.154 150.069)',
        surface: 'oklch(0.96 0.02 155)',
        group: 'basic',
    },
    {
        value: 'orange',
        label: 'Oranye',
        preview: 'oklch(0.646 0.222 41.116)',
        surface: 'oklch(0.96 0.03 75)',
        group: 'basic',
    },
    {
        value: 'violet',
        label: 'Ungu',
        preview: 'oklch(0.541 0.281 293.009)',
        surface: 'oklch(0.96 0.02 300)',
        group: 'basic',
    },
];

/** Popular all-time community themes from shadcnpreset.com/community */
export const presetPaletteOptions: PaletteOption[] = [
    {
        value: 'luma-lime',
        label: 'Luma Lime',
        preview: 'oklch(0.841 0.238 128.85)',
        surface: 'oklch(1 0 0)',
        group: 'preset',
    },
    {
        value: 'sera-taupe',
        label: 'Sera Taupe',
        preview: 'oklch(0.214 0.009 43.1)',
        surface: 'oklch(1 0 0)',
        group: 'preset',
    },
    {
        value: 'lyra-zinc',
        label: 'Lyra Zinc',
        preview: 'oklch(0.21 0.006 285.885)',
        surface: 'oklch(1 0 0)',
        group: 'preset',
    },
    {
        value: 'vega-emerald',
        label: 'Vega Emerald',
        preview: 'oklch(0.508 0.118 165.612)',
        surface: 'oklch(1 0 0)',
        group: 'preset',
    },
    {
        value: 'luma-teal',
        label: 'Luma Teal',
        preview: 'oklch(0.511 0.096 186.391)',
        surface: 'oklch(1 0 0)',
        group: 'preset',
    },
    {
        value: 'luma-neutral',
        label: 'Luma Neutral',
        preview: 'oklch(0.205 0 0)',
        surface: 'oklch(1 0 0)',
        group: 'preset',
    },
    {
        value: 'nova-mauve',
        label: 'Nova Mauve',
        preview: 'oklch(0.212 0.019 322.12)',
        surface: 'oklch(1 0 0)',
        group: 'preset',
    },
    {
        value: 'sera-amber',
        label: 'Sera Amber',
        preview: 'oklch(0.555 0.163 48.998)',
        surface: 'oklch(1 0 0)',
        group: 'preset',
    },
    {
        value: 'luma-yellow',
        label: 'Luma Yellow',
        preview: 'oklch(0.852 0.199 91.936)',
        surface: 'oklch(1 0 0)',
        group: 'preset',
    },
    {
        value: 'vega-sky',
        label: 'Vega Sky',
        preview: 'oklch(0.5 0.134 242.749)',
        surface: 'oklch(1 0 0)',
        group: 'preset',
    },
    {
        value: 'nova-violet',
        label: 'Nova Violet',
        preview: 'oklch(0.491 0.27 292.581)',
        surface: 'oklch(1 0 0)',
        group: 'preset',
    },
    {
        value: 'luma-red',
        label: 'Luma Red',
        preview: 'oklch(0.505 0.213 27.518)',
        surface: 'oklch(1 0 0)',
        group: 'preset',
    },
];

export const paletteOptions: PaletteOption[] = [...basicPaletteOptions, ...presetPaletteOptions];

const paletteValues = new Set<string>(paletteOptions.map((option) => option.value));

export function isPaletteColor(value: string | null | undefined): value is PaletteColor {
    return value !== null && value !== undefined && paletteValues.has(value);
}

export function readStoredPalette(): PaletteColor {
    try {
        const stored =
            localStorage.getItem(PALETTE_STORAGE_KEY) ?? localStorage.getItem(ACCENT_STORAGE_KEY);
        return isPaletteColor(stored) ? stored : 'neutral';
    } catch {
        return 'neutral';
    }
}

export function applyPalette(color: PaletteColor): void {
    if (color === 'neutral') {
        document.documentElement.removeAttribute('data-palette');
        document.documentElement.removeAttribute('data-accent');
        applyPaletteFonts(color);
        return;
    }

    document.documentElement.setAttribute('data-palette', color);
    document.documentElement.removeAttribute('data-accent');
    applyPaletteFonts(color);
}

export function isThemeMode(value: string | null | undefined): value is ThemeMode {
    return value === 'light' || value === 'dark' || value === 'system';
}

export function applyTenantAppearance(
    tenant: { theme_mode?: string | null; theme_palette?: string | null },
    setTheme?: (theme: string) => void,
): void {
    const mode = isThemeMode(tenant.theme_mode) ? tenant.theme_mode : 'system';
    const palette = isPaletteColor(tenant.theme_palette) ? tenant.theme_palette : 'neutral';

    if (setTheme) {
        setTheme(mode);
    }

    try {
        localStorage.setItem(THEME_STORAGE_KEY, mode);
    } catch {
        // Ignore storage failures in private browsing.
    }

    persistPalette(palette);
}

export function persistPalette(color: PaletteColor): void {
    try {
        if (color === 'neutral') {
            localStorage.removeItem(PALETTE_STORAGE_KEY);
            localStorage.removeItem(ACCENT_STORAGE_KEY);
        } else {
            localStorage.setItem(PALETTE_STORAGE_KEY, color);
            localStorage.removeItem(ACCENT_STORAGE_KEY);
        }
    } catch {
        // Ignore storage failures in private browsing.
    }

    applyPalette(color);
}

/** @deprecated use PaletteColor */
export type AccentColor = PaletteColor;

/** @deprecated use paletteOptions */
export const accentOptions = paletteOptions;

/** @deprecated use readStoredPalette */
export const readStoredAccent = readStoredPalette;

/** @deprecated use applyPalette */
export const applyAccent = applyPalette;

/** @deprecated use persistPalette */
export const persistAccent = persistPalette;
