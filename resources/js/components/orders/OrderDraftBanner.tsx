import { FileText } from 'lucide-react';

import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';

interface OrderDraftBannerProps {
    onRestore: () => void;
    onDiscard: () => void;
    className?: string;
}

export function OrderDraftBanner({
    onRestore,
    onDiscard,
    className,
}: OrderDraftBannerProps) {
    return (
        <div
            role="status"
            className={cn(
                'flex items-center gap-2 rounded-lg bg-muted/60 px-3 py-2 text-sm',
                className,
            )}
        >
            <FileText className="size-4 shrink-0 text-muted-foreground" />
            <span className="min-w-0 flex-1 truncate text-foreground">
                Draf tersimpan ditemukan
            </span>
            <Button type="button" variant="default" size="sm" onClick={onRestore}>
                Pulihkan
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={onDiscard}
                className="text-destructive hover:text-destructive"
            >
                Buang
            </Button>
        </div>
    );
}
