import presetFonts from '@/lib/theme-preset-fonts.json';

export const THEME_STORAGE_KEY = 'theme';
export const PALETTE_STORAGE_KEY = 'order-tracker-palette';
/** @deprecated migrated to PALETTE_STORAGE_KEY */
export const ACCENT_STORAGE_KEY = 'order-tracker-accent';

const PALETTE_FONT_LINK_ID = 'palette-google-fonts';

const NON_GOOGLE_FONTS = new Set([
    'SFMono-Regular',
    'Segoe UI',
    'Apple Color Emoji',
    'Cambria',
    'Times New Roman',
    'Georgia',
]);

function buildGoogleFontsHref(families: string[]): string | null {
    const googleFamilies = families.filter((family) => !NON_GOOGLE_FONTS.has(family));
    if (googleFamilies.length === 0) {
        return null;
    }

    const familyParams = googleFamilies
        .map((family) => `family=${encodeURIComponent(family)}:wght@300;400;500;600;700`)
        .join('&');

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
    | 'claude-plus'
    | 'light-green'
    | 'zen-inspired-theme'
    | 'astrovista'
    | 'tiesen'
    | 'designbyte'
    | 'qrafthive'
    | 'mx-brutalist'
    | 'sage-green'
    | 'apple-liquid-glass';

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

/** Popular all-time community themes from tweakcn.com/community */
export const presetPaletteOptions: PaletteOption[] = [
    {
        value: 'claude-plus',
        label: 'Claude +',
        preview: 'oklch(0.6171 0.1375 39.0427)',
        surface: 'oklch(0.9818 0.0054 95.0986)',
        group: 'preset',
    },
    {
        value: 'light-green',
        label: 'Light Green',
        preview: 'oklch(0.8871 0.2122 128.5041)',
        surface: 'oklch(0.9892 0.0054 117.9205)',
        group: 'preset',
    },
    {
        value: 'zen-inspired-theme',
        label: 'Zen Inspired',
        preview: 'oklch(0.3012 0 0)',
        surface: 'oklch(0.9195 0.0169 88.0030)',
        group: 'preset',
    },
    {
        value: 'astrovista',
        label: 'AstroVista',
        preview: 'oklch(0.6420 0.1691 38.5815)',
        surface: 'oklch(0.9383 0.0042 236.4993)',
        group: 'preset',
    },
    {
        value: 'tiesen',
        label: 'Tiesen',
        preview: 'oklch(0.5144 0.1605 267.4400)',
        surface: 'oklch(0.9851 0 0)',
        group: 'preset',
    },
    {
        value: 'designbyte',
        label: 'Designbyte',
        preview: 'oklch(0.8545 0.1675 159.6564)',
        surface: 'oklch(0.9940 0 0)',
        group: 'preset',
    },
    {
        value: 'qrafthive',
        label: 'Qrafthive',
        preview: 'oklch(0.6716 0.1368 48.5130)',
        surface: 'oklch(1.0000 0 0)',
        group: 'preset',
    },
    {
        value: 'mx-brutalist',
        label: 'MX-Brutalist',
        preview: 'oklch(0.5687 0.1498 151.9380)',
        surface: 'oklch(0.9923 0.0104 91.4994)',
        group: 'preset',
    },
    {
        value: 'sage-green',
        label: 'Sage Green',
        preview: 'oklch(0.7830 0.0384 132.7370)',
        surface: 'oklch(0.9940 0 0)',
        group: 'preset',
    },
    {
        value: 'apple-liquid-glass',
        label: 'Apple Liquid Glass',
        preview: 'oklch(0.6007 0.1903 257.9419)',
        surface: 'oklch(0.9700 0.0029 264.5420)',
        group: 'preset',
    },
];

export const paletteOptions: PaletteOption[] = [...basicPaletteOptions, ...presetPaletteOptions];

const paletteValues = new Set<string>(paletteOptions.map((option) => option.value));

export function isPaletteColor(value: string | null): value is PaletteColor {
    return value !== null && paletteValues.has(value);
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
    const mode = isThemeMode(tenant.theme_mode ?? null) ? tenant.theme_mode! : 'system';
    const palette = isPaletteColor(tenant.theme_palette ?? null) ? tenant.theme_palette! : 'neutral';

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
