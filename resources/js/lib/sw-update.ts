import { toast } from 'sonner';

/**
 * Service-worker update flow.
 *
 * Listens for a newly-installed waiting SW. When one is detected we surface a
 * sonner toast with a "Reload" action that tells the waiting SW to skipWaiting;
 * once it becomes the active controller the page reloads automatically so the
 * user picks up the new precached shell + assets.
 *
 * Must run after the SW has been registered (Blade registers /sw.js on load).
 */
export function registerServiceWorkerUpdater(): void {
    if (typeof window === 'undefined') return;
    if (!('serviceWorker' in navigator)) return;

    let reloadShown = false;

    const promptReload = (waitingReg?: ServiceWorker) => {
        if (reloadShown) return;
        reloadShown = true;
        toast.info('Pembaruan tersedia', {
            description: 'Versi baru aplikasi siap dipasang.',
            duration: 12000,
            action: {
                label: 'Muat ulang',
                onClick: () => {
                    if (waitingReg) {
                        waitingReg.postMessage({ type: 'SKIP_WAITING' });
                    } else {
                        window.location.reload();
                    }
                },
            },
        });
    };

    window.addEventListener('load', () => {
        navigator.serviceWorker.getRegistration().then((reg) => {
            if (!reg) return;

            // New SW waiting to activate.
            if (reg.waiting) {
                promptReload(reg.waiting);
            }

            reg.addEventListener('updatefound', () => {
                const installing = reg.installing;
                if (!installing) return;
                installing.addEventListener('statechange', () => {
                    if (installing.state === 'installed' && navigator.serviceWorker.controller) {
                        promptReload(installing);
                    }
                });
            });
        });

        // When the new SW takes over, reload exactly once.
        let refreshed = false;
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (refreshed) return;
            refreshed = true;
            window.location.reload();
        });
    });
}
