import { useEffect, useState } from 'react';

/**
 * Tracks whether a CSS media query currently matches.
 *
 * SSR-safe: returns false and registers no listener when `window`
 * or `window.matchMedia` is unavailable.
 */
export function useMediaQuery(query: string): boolean {
    const [matches, setMatches] = useState<boolean>(() => {
        if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
            return false;
        }
        return window.matchMedia(query).matches;
    });

    useEffect(() => {
        if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
            return;
        }
        const mql = window.matchMedia(query);
        // Re-read on mount to correct any initial-state race.
        setMatches(mql.matches);
        const handler = (event: MediaQueryListEvent) => {
            setMatches(event.matches);
        };
        mql.addEventListener('change', handler);
        return () => {
            mql.removeEventListener('change', handler);
        };
    }, [query]);

    return matches;
}
