import { useCallback, useEffect, useState } from 'react';

/**
 * Minimal type for the BeforeInstallPromptEvent (not yet in lib.dom).
 * Chrome/Edge fire it when the installability criteria are met; we capture it
 * and call `.prompt()` later when the user clicks our install button.
 */
interface BeforeInstallPromptEvent extends Event {
    readonly platforms: string[];
    readonly userChoice: Promise<{
        outcome: 'accepted' | 'dismissed';
        platform: string;
    }>;
    prompt: () => Promise<void>;
}

const DISMISS_KEY = 'pwa-install-dismissed';

/** True when the app is already running in a standalone display mode. */
export function isStandalone(): boolean {
    if (typeof window === 'undefined') return false;
    return (
        window.matchMedia?.('(display-mode: standalone)').matches === true ||
        // iOS Safari exposes this on navigator instead.
        (window.navigator as Navigator & { standalone?: boolean }).standalone === true
    );
}

/** iOS Safari (no beforeinstallprompt) — needs manual "Add to Home Screen". */
export function isIosSafari(): boolean {
    if (typeof window === 'undefined') return false;
    const ua = window.navigator.userAgent || '';
    const isIOS = /iphone|ipad|ipod/i.test(ua);
    const isWebkit = /webkit/i.test(ua);
    const notChrome = !/crios|fxios/i.test(ua);
    return isIOS && isWebkit && notChrome;
}

/** Android browser (Chrome, Firefox, Samsung Internet, Opera, etc.). Excludes Windows desktop. */
export function isAndroidBrowser(): boolean {
    if (typeof window === 'undefined') return false;
    const ua = window.navigator.userAgent || '';
    return /android/i.test(ua) && !/windows/i.test(ua);
}

export type InstallPlatform = 'ios' | 'android' | 'desktop';

/** Detect current platform for install UX. */
export function detectPlatform(): InstallPlatform {
    if (isIosSafari()) return 'ios';
    if (isAndroidBrowser()) return 'android';
    return 'desktop';
}

/** Has the user previously dismissed the install prompt? */
export function isInstallDismissed(): boolean {
    try {
        return localStorage.getItem(DISMISS_KEY) === '1';
    } catch {
        return false;
    }
}

export function dismissInstall(): void {
    try {
        localStorage.setItem(DISMISS_KEY, '1');
    } catch {
        // ignore quota / privacy errors
    }
}

/**
 * Hook returning whether the app can be installed, plus a trigger.
 * On iOS we expose `canInstall: true` when running in Safari + not standalone
 * (the install is manual via Share → Add to Home Screen, surfaced as a sheet).
 */
export function useInstallPrompt() {
    const [deferred, setDeferred] = useState<BeforeInstallPromptEvent | null>(null);
    const [canInstall, setCanInstall] = useState(false);
    const [hasNativePrompt, setHasNativePrompt] = useState(false);
    const platform = detectPlatform();

    useEffect(() => {
        if (isStandalone()) return;

        const handler = (e: Event) => {
            e.preventDefault();
            setDeferred(e as BeforeInstallPromptEvent);
            setCanInstall(true);
            setHasNativePrompt(true);
        };
        window.addEventListener('beforeinstallprompt', handler);

        // iOS and Android-non-Chrome have no beforeinstallprompt — surface
        // the manual instructions sheet path instead.
        if (!isInstallDismissed() && (isIosSafari() || isAndroidBrowser())) {
            setCanInstall(true);
        }

        return () => window.removeEventListener('beforeinstallprompt', handler);
    }, []);

    const promptInstall = useCallback(async (): Promise<'accepted' | 'dismissed' | 'unsupported'> => {
        if (!deferred) return 'unsupported';
        await deferred.prompt();
        const choice = await deferred.userChoice;
        setDeferred(null);
        setCanInstall(false);
        return choice.outcome;
    }, [deferred]);

    return { canInstall, promptInstall, isIos: isIosSafari(), platform, hasNativePrompt };
}
