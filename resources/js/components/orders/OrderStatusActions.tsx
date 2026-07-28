import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
    canCancel,
    getAdvanceActionLabel,
    getAdvanceStatuses,
    isTerminal,
} from '@/lib/orderStatus';

interface OrderStatusActionsProps {
    status: string;
    onAdvance: (nextStatus: string) => void | Promise<void>;
    loading?: boolean;
    disabled?: boolean;
    layout?: 'stack' | 'inline';
}

export function OrderStatusActions({
    status,
    onAdvance,
    loading = false,
    disabled = false,
    layout = 'stack',
}: OrderStatusActionsProps) {
    const [cancelOpen, setCancelOpen] = useState(false);
    const advanceStatuses = getAdvanceStatuses(status);
    const showCancel = canCancel(status);

    if (isTerminal(status)) {
        return null;
    }

    return (
        <>
            <div className={layout === 'inline' ? 'flex flex-wrap gap-2' : 'flex flex-col gap-2'}>
                {advanceStatuses.map((nextStatus) => (
                    <Button
                        key={nextStatus}
                        className={layout === 'inline' ? 'flex-1' : 'w-full'}
                        disabled={disabled || loading}
                        onClick={() => onAdvance(nextStatus)}
                    >
                        {getAdvanceActionLabel(status, nextStatus)}
                    </Button>
                ))}
                {showCancel && (
                    <Button
                        variant="outline"
                        className={cnDestructive(layout)}
                        disabled={disabled || loading}
                        onClick={() => setCancelOpen(true)}
                    >
                        Batalkan Pesanan
                    </Button>
                )}
            </div>

            <AlertDialog open={cancelOpen} onOpenChange={setCancelOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Batalkan pesanan?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Status pesanan akan diubah menjadi Batal. Tindakan ini tidak bisa dibatalkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Kembali</AlertDialogCancel>
                        <AlertDialogAction
                            className="bg-destructive text-destructive-foreground"
                            onClick={() => {
                                setCancelOpen(false);
                                void onAdvance('cancelled');
                            }}
                        >
                            Batalkan Pesanan
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

function cnDestructive(layout: 'stack' | 'inline'): string {
    return layout === 'inline'
        ? 'flex-1 border-destructive/30 text-destructive hover:bg-destructive/10'
        : 'w-full border-destructive/30 text-destructive hover:bg-destructive/10';
}
