import { useState } from 'react';
import { useSwipeable } from 'react-swipeable';
import { cn } from '@/lib/utils';

interface IosSwipeRowProps {
    children: React.ReactNode;
    onSwipeLeft?: () => void;
    onSwipeRight?: () => void;
    leftActions?: React.ReactNode;
    rightActions?: React.ReactNode;
    className?: string;
}

export function IosSwipeRow({
    children,
    onSwipeLeft,
    onSwipeRight,
    leftActions,
    rightActions,
    className,
}: IosSwipeRowProps) {
    const [offset, setOffset] = useState(0);

    const handlers = useSwipeable({
        onSwiping: (event) => {
            if (event.dir === 'Left' && leftActions) {
                setOffset(Math.max(-120, event.deltaX));
            }
            if (event.dir === 'Right' && rightActions) {
                setOffset(Math.min(96, event.deltaX));
            }
        },
        onSwipedLeft: () => {
            setOffset(0);
            onSwipeLeft?.();
        },
        onSwipedRight: () => {
            setOffset(0);
            onSwipeRight?.();
        },
        onTouchEndOrOnMouseUp: () => setOffset(0),
        trackMouse: true,
        preventScrollOnSwipe: true,
    });

    return (
        <div className={cn('relative overflow-hidden', className)} {...handlers}>
            {leftActions && (
                <div className="absolute inset-y-0 left-0 flex items-stretch">{leftActions}</div>
            )}
            {rightActions && (
                <div className="absolute inset-y-0 right-0 flex items-stretch">{rightActions}</div>
            )}
            <div
                className="relative bg-card transition-transform duration-150"
                style={{ transform: `translateX(${offset}px)` }}
            >
                {children}
            </div>
        </div>
    );
}
