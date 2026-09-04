import { useState } from 'react';
import { Download, Share, PlusSquare, MoreVertical } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { CollapsibleCard } from '@/components/CollapsibleCard';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useInstallPrompt, dismissInstall } from '@/lib/useInstallPrompt';

/**
 * Install card shown in Settings → Profile.
 *
 * - Chrome/Edge (hasNativePrompt): button triggers beforeinstallprompt.
 * - iOS Safari: opens a sheet explaining Share → Add to Home Screen.
 * - Android non-Chrome (Firefox/Samsung/Opera): opens a sheet explaining
 *   browser menu (⋮) → Add to Home screen.
 * - Hidden when running standalone (already installed) or after user dismiss.
 */
export function InstallAppCard() {
    const { canInstall, promptInstall, platform, hasNativePrompt } = useInstallPrompt();
    const [sheetOpen, setSheetOpen] = useState(false);
    const [busy, setBusy] = useState(false);

    if (!canInstall) return null;

    async function handleInstall() {
        // Chrome/Edge: use the captured beforeinstallprompt event.
        if (hasNativePrompt) {
            setBusy(true);
            try {
                const outcome = await promptInstall();
                if (outcome === 'dismissed') {
                    dismissInstall();
                }
            } finally {
                setBusy(false);
            }
            return;
        }
        // iOS or Android non-Chrome: show manual instructions sheet.
        setSheetOpen(true);
    }

    const buttonLabel = busy
        ? 'Memproses...'
        : hasNativePrompt
            ? 'Pasang ke layar utama'
            : platform === 'ios'
                ? 'Cara pasang di iPhone/iPad'
                : platform === 'android'
                    ? 'Cara pasang di Android'
                    : 'Pasang ke layar utama';

    const isIosSheet = platform === 'ios';

    return (
        <>
            <CollapsibleCard
                title={
                    <span className="flex items-center gap-2">
                        <Download className="size-4" />
                        Pasang Aplikasi
                    </span>
                }
                description="Simpan ke layar utama agar terbuka penuh tanpa address bar."
            >
                <Button onClick={handleInstall} disabled={busy} className="w-full sm:w-auto">
                    {buttonLabel}
                </Button>
            </CollapsibleCard>

            <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
                <SheetContent side="bottom" className="sm:max-w-md">
                    <SheetHeader>
                        <SheetTitle>Pasang ke Layar Utama</SheetTitle>
                        <SheetDescription>
                            {isIosSheet
                                ? 'Tiga langkah singkat untuk memasang aplikasi di iPhone atau iPad.'
                                : 'Tiga langkah singkat untuk memasang aplikasi di perangkat Android Anda.'}
                        </SheetDescription>
                    </SheetHeader>

                    {isIosSheet ? (
                        <ol className="mt-4 space-y-4 px-1 text-sm">
                            <li className="flex gap-3">
                                <Share className="mt-0.5 size-5 shrink-0 text-primary" />
                                <span>
                                    Ketuk tombol <strong>Share</strong> di bilah bawah Safari.
                                </span>
                            </li>
                            <li className="flex gap-3">
                                <PlusSquare className="mt-0.5 size-5 shrink-0 text-primary" />
                                <span>
                                    Pilih <strong>Add to Home Screen</strong> dari daftar.
                                </span>
                            </li>
                            <li className="flex gap-3">
                                <Download className="mt-0.5 size-5 shrink-0 text-primary" />
                                <span>
                                    Ketuk <strong>Add</strong>. Ikon akan muncul di layar utama.
                                </span>
                            </li>
                        </ol>
                    ) : (
                        <ol className="mt-4 space-y-4 px-1 text-sm">
                            <li className="flex gap-3">
                                <MoreVertical className="mt-0.5 size-5 shrink-0 text-primary" />
                                <span>
                                    Ketuk ikon <strong>menu</strong> (⋮) di pojok kanan atas browser.
                                </span>
                            </li>
                            <li className="flex gap-3">
                                <PlusSquare className="mt-0.5 size-5 shrink-0 text-primary" />
                                <span>
                                    Pilih <strong>Add to Home screen</strong>{' '}
                                    (atau <strong>Install app</strong> di beberapa browser).
                                </span>
                            </li>
                            <li className="flex gap-3">
                                <Download className="mt-0.5 size-5 shrink-0 text-primary" />
                                <span>
                                    Konfirmasi dengan mengetuk <strong>Add</strong> / <strong>OK</strong>.
                                </span>
                            </li>
                        </ol>
                    )}

                    <div className="mt-6 flex justify-end gap-2">
                        <Button
                            variant="ghost"
                            onClick={() => {
                                dismissInstall();
                                setSheetOpen(false);
                            }}
                        >
                            Jangan tampilkan lagi
                        </Button>
                        <Button onClick={() => setSheetOpen(false)}>Mengerti</Button>
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}
