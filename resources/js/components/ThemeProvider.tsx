import { useEffect } from 'react';
import { ThemeProvider as NextThemesProvider } from 'next-themes';
import { applyPalette, readStoredPalette, THEME_STORAGE_KEY } from '@/lib/theme';

export function ThemeProvider({ children }: { children: React.ReactNode }) {
    useEffect(() => {
        applyPalette(readStoredPalette());
    }, []);

    return (
        <NextThemesProvider
            attribute="class"
            defaultTheme="system"
            enableSystem
            disableTransitionOnChange
            storageKey={THEME_STORAGE_KEY}
        >
            {children}
        </NextThemesProvider>
    );
}
