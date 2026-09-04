import { ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

interface IosListRowProps {
    title: string;
    subtitle?: React.ReactNode;
    prefix?: React.ReactNode;
    trailing?: React.ReactNode;
    onClick?: () => void;
    showChevron?: boolean;
    className?: string;
    children?: React.ReactNode;
    onPointerDown?: React.PointerEventHandler<HTMLElement>;
    onPointerMove?: React.PointerEventHandler<HTMLElement>;
    onPointerUp?: React.PointerEventHandler<HTMLElement>;
    onPointerLeave?: React.PointerEventHandler<HTMLElement>;
    onPointerCancel?: React.PointerEventHandler<HTMLElement>;
    onContextMenu?: React.MouseEventHandler<HTMLElement>;
    onTouchStart?: React.TouchEventHandler<HTMLElement>;
    onTouchMove?: React.TouchEventHandler<HTMLElement>;
    onTouchEnd?: React.TouchEventHandler<HTMLElement>;
}

export function IosListRow({
    title,
    subtitle,
    prefix,
    trailing,
    onClick,
    showChevron = false,
    className,
    children,
    onPointerDown,
    onPointerMove,
    onPointerUp,
    onPointerLeave,
    onPointerCancel,
    onContextMenu,
    onTouchStart,
    onTouchMove,
    onTouchEnd,
}: IosListRowProps) {
    const Comp = onClick ? 'button' : 'div';

    return (
        <Comp
            type={onClick ? 'button' : undefined}
            onClick={onClick}
            className={cn(
                'flex w-full min-h-12 items-center gap-3 px-5 py-3.5 text-left transition-colors sm:px-6',
                onClick && 'active:bg-muted/60',
                className,
            )}
            style={{ touchAction: 'pan-y' }}
            onPointerDown={onPointerDown}
            onPointerMove={onPointerMove}
            onPointerUp={onPointerUp}
            onPointerLeave={onPointerLeave}
            onPointerCancel={onPointerCancel}
            onContextMenu={onContextMenu}
            onTouchStart={onTouchStart}
            onTouchMove={onTouchMove}
            onTouchEnd={onTouchEnd}
        >
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                {prefix ? <div className="flex items-center gap-1">{prefix}</div> : null}
                <p className="truncate font-medium text-foreground">{title}</p>
                {subtitle && (
                    typeof subtitle === 'string' ? (
                        <p className="truncate text-xs text-muted-foreground">{subtitle}</p>
                    ) : (
                        <div className="text-xs text-muted-foreground">{subtitle}</div>
                    )
                )}
                {children}
            </div>
            {trailing && <div className="shrink-0 text-right">{trailing}</div>}
            {showChevron && <ChevronRight className="shrink-0 text-muted-foreground" />}
        </Comp>
    );
}
