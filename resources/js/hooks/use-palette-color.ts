import { useCallback, useEffect, useState } from 'react';
import {
    applyPalette,
    persistPalette,
    type PaletteColor,
    readStoredPalette,
} from '@/lib/theme';

export function usePaletteColor() {
    const [palette, setPaletteState] = useState<PaletteColor>('neutral');
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        const stored = readStoredPalette();
        setPaletteState(stored);
        applyPalette(stored);
        setMounted(true);
    }, []);

    const setPalette = useCallback((color: PaletteColor) => {
        setPaletteState(color);
        persistPalette(color);
    }, []);

    return { palette, setPalette, mounted };
}
