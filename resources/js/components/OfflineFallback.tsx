import { useState } from 'react';
import { useApi } from '@/lib/ApiProvider';
import { Button } from '@/components/ui/button';

/**
 * Retryable full-page state for when the session probe fails because the
 * network is unreachable and no user was ever loaded — keeps offline users
 * on-page instead of bouncing them to /login.
 */
export function OfflineFallback() {
    const { refreshUser } = useApi();
    const [retrying, setRetrying] = useState(false);

    const retry = async () => {
        setRetrying(true);
        try {
            await refreshUser();
        } finally {
            setRetrying(false);
        }
    };

    return (
        <div className="flex min-h-dvh flex-col items-center justify-center gap-2 bg-background p-6 text-center">
            <h1 className="text-lg font-semibold">Anda sedang offline</h1>
            <p className="max-w-sm text-sm text-muted-foreground">
                Tidak dapat terhubung ke server. Periksa koneksi internet Anda.
            </p>
            <Button className="mt-2" onClick={() => void retry()} disabled={retrying}>
                {retrying ? 'Menyambungkan ulang…' : 'Coba lagi'}
            </Button>
        </div>
    );
}
