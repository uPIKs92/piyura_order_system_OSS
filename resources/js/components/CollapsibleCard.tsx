import { useState } from 'react';
import { ChevronDown } from 'lucide-react';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { haptic } from '@/lib/format';
import { cn } from '@/lib/utils';

interface CollapsibleCardProps {
    title: React.ReactNode;
    description?: React.ReactNode;
    defaultOpen?: boolean;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    children: React.ReactNode;
    className?: string;
    contentClassName?: string;
}

export function CollapsibleCard({
    title,
    description,
    defaultOpen = false,
    open: openProp,
    onOpenChange,
    children,
    className,
    contentClassName,
}: CollapsibleCardProps) {
    const [internalOpen, setInternalOpen] = useState(defaultOpen);
    const open = openProp ?? internalOpen;

    function handleOpenChange(next: boolean) {
        if (openProp === undefined) setInternalOpen(next);
        onOpenChange?.(next);
        haptic();
    }

    return (
        <Collapsible open={open} onOpenChange={handleOpenChange}>
            <Card className={className}>
                <CollapsibleTrigger
                    render={
                        <CardHeader className="cursor-pointer select-none rounded-t-4xl active:bg-muted/60" />
                    }
                >
                    <CardTitle>{title}</CardTitle>
                    {description ? <CardDescription>{description}</CardDescription> : null}
                    <CardAction>
                        <ChevronDown
                            className={cn(
                                'size-4 shrink-0 text-muted-foreground transition-transform',
                                open ? '' : '-rotate-90',
                            )}
                        />
                    </CardAction>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <CardContent className={contentClassName}>{children}</CardContent>
                </CollapsibleContent>
            </Card>
        </Collapsible>
    );
}
