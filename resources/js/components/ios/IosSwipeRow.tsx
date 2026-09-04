import { useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';

interface IosSwipeRowProps {
    children: React.ReactNode;
    onSwipeLeft?: () => void;
    onSwipeRight?: () => void;
    leftActions?: React.ReactNode;
    rightActions?: React.ReactNode;
    className?: string;
    contentClassName?: string;
}

const DIRECTION_THRESHOLD = 8;
const LEFT_TRIGGER = 80;
const RIGHT_TRIGGER = 60;
const LEFT_MAX = 120;
const RIGHT_MAX = 96;

/**
 * Swipeable row — HYBRID input handling for cross-platform support.
 *
 * Two input pathways feed the same shared gesture logic:
 *
 * 1. Touch Events (`addEventListener`, NON-PASSIVE `touchmove`):
 *    iOS Safari + Android Chrome. `touch-action: pan-y` only HINTS to the
 *    browser that vertical panning is native. But iOS's gesture router commits
 *    any gesture with vertical drift to native scroll before our 8px
 *    direction-lock fires — stealing the gesture from JS. Pure passive + pan-y
 *    therefore CANNOT reclaim horizontal/diagonal swipes on iOS. The only thing
 *    that forces JS ownership is `preventDefault()` on a NON-PASSIVE `touchmove`,
 *    called once a horizontal swipe is confirmed. Vertical movement returns
 *    early and never calls preventDefault, so native scrolling stays smooth.
 *    (Touch events always deliver coordinates; we read dx and transform via ref.)
 *
 * 2. Pointer Events (React props, `pointerType !== 'touch'`):
 *    Desktop mouse/pen. Touch devices also fire pointer events, but the
 *    `pointerType === 'touch'` guard prevents double-processing — only the
 *    touch pathway runs for touch input. This is why desktop drag works.
 *
 * Performance:
 * - `touchmove` is NON-PASSIVE so we can `preventDefault` to claim horizontal
 *   gestures. Touch events dispatch only to the touched element (the touchstart
 *   target), so the cost is one cheap handler on one row — NOT 20 rows.
 * - `preventDefault` runs ONLY when `swiping.current` is true (horizontal
 *   confirmed); vertical movement returns early, so native scroll proceeds.
 * - Transforms are applied directly via ref, no React re-renders during drag.
 *
 * Action anchoring: `leftActions` = actions revealed by a left-swipe (content
 * slides left, exposes them on trailing/right edge). `rightActions` mirror on
 * the leading/left edge. Matches iOS trailing/leading-action semantics.
 */
export function IosSwipeRow({
    children,
    onSwipeLeft,
    onSwipeRight,
    leftActions,
    rightActions,
    className,
    contentClassName,
}: IosSwipeRowProps) {
    const rootRef = useRef<HTMLDivElement>(null);
    const contentRef = useRef<HTMLDivElement>(null);
    const startX = useRef(0);
    const startY = useRef(0);
    const offset = useRef(0);
    const swiping = useRef(false);
    const directionLocked = useRef(false);
    const suppressClick = useRef(false);
    const pointerActive = useRef(false);

    // Store latest config in a ref so listeners bind once (empty deps).
    const configRef = useRef({
        hasLeft: !!leftActions,
        hasRight: !!rightActions,
        onLeft: onSwipeLeft,
        onRight: onSwipeRight,
    });
    configRef.current = {
        hasLeft: !!leftActions,
        hasRight: !!rightActions,
        onLeft: onSwipeLeft,
        onRight: onSwipeRight,
    };

    function setTransform(px: number, animated: boolean) {
        const el = contentRef.current;
        if (!el) return;
        el.style.transition = animated ? 'transform 150ms ease-out' : '';
        el.style.transform = `translateX(${px}px)`;
        offset.current = px;
    }

    // ─── Shared gesture logic (used by both touch + pointer pathways) ──────

    function startGesture(x: number, y: number) {
        startX.current = x;
        startY.current = y;
        offset.current = 0;
        swiping.current = false;
        directionLocked.current = false;
    }

    function updateGesture(x: number, y: number) {
        const dx = x - startX.current;
        const dy = y - startY.current;
        const { hasLeft, hasRight } = configRef.current;

        if (!directionLocked.current) {
            if (Math.abs(dx) < DIRECTION_THRESHOLD && Math.abs(dy) < DIRECTION_THRESHOLD) return;
            directionLocked.current = true;
            if (Math.abs(dy) >= Math.abs(dx)) return; // vertical — native scroll
            if ((dx < 0 && !hasLeft) || (dx > 0 && !hasRight)) return; // no action that way
            swiping.current = true;
        }

        if (!swiping.current) return;

        let clamped = 0;
        if (dx < 0 && hasLeft) {
            clamped = Math.max(-LEFT_MAX, dx);
        } else if (dx > 0 && hasRight) {
            clamped = Math.min(RIGHT_MAX, dx);
        }
        setTransform(clamped, false);
    }

    function endGesture() {
        if (!swiping.current) return;

        const finalOffset = offset.current;
        setTransform(0, true);

        const { onLeft, onRight } = configRef.current;
        if (finalOffset <= -LEFT_TRIGGER && onLeft) {
            suppressClick.current = true;
            onLeft();
        } else if (finalOffset >= RIGHT_TRIGGER && onRight) {
            suppressClick.current = true;
            onRight();
        }

        swiping.current = false;
    }

    function resetGesture() {
        if (offset.current !== 0) setTransform(0, true);
        swiping.current = false;
        directionLocked.current = false;
    }

    // ─── Touch Events: iOS Safari + Android Chrome ────────────────────────

    useEffect(() => {
        const el = rootRef.current;
        if (!el) return;

        function onTouchStart(e: TouchEvent) {
            if (e.touches.length !== 1) return;
            const t = e.touches[0];
            startGesture(t.clientX, t.clientY);
        }

        function onTouchMove(e: TouchEvent) {
            if (e.touches.length !== 1) return;
            const t = e.touches[0];
            updateGesture(t.clientX, t.clientY);
            // Once a horizontal swipe is confirmed, CLAIM the gesture so iOS
            // Safari cannot steal it back for native vertical scroll. pan-y only
            // hints the browser; preventDefault on a non-passive listener is
            // what actually forces JS ownership for diagonal/ambiguous drags.
            // Vertical movement never reaches here (updateGesture returns early
            // with swiping.current === false), so native scrolling is untouched.
            if (swiping.current) {
                e.preventDefault();
            }
        }

        function onTouchEnd() {
            endGesture();
        }

        // touchmove is NON-PASSIVE so preventDefault can claim horizontal
        // gestures on iOS. touchstart/end stay passive (no preventDefault needed).
        el.addEventListener('touchstart', onTouchStart, { passive: true });
        el.addEventListener('touchmove', onTouchMove, { passive: false });
        el.addEventListener('touchend', onTouchEnd, { passive: true });
        el.addEventListener('touchcancel', onTouchEnd, { passive: true });

        return () => {
            el.removeEventListener('touchstart', onTouchStart);
            el.removeEventListener('touchmove', onTouchMove);
            el.removeEventListener('touchend', onTouchEnd);
            el.removeEventListener('touchcancel', onTouchEnd);
        };
    }, []);

    // ─── Pointer Events: desktop mouse/pen only ───────────────────────────
    // On touch devices, pointerdown/move/up also fire — the pointerType guard
    // prevents double-processing (touch pathway handles those).

    function onPointerDown(e: React.PointerEvent) {
        if (e.pointerType === 'touch') return; // touch pathway handles it
        pointerActive.current = true;
        startGesture(e.clientX, e.clientY);
    }

    function onPointerMove(e: React.PointerEvent) {
        if (e.pointerType === 'touch' || !pointerActive.current) return;
        const wasSwiping = swiping.current;
        updateGesture(e.clientX, e.clientY);
        // Capture pointer once swipe confirms so we track outside element bounds.
        if (!wasSwiping && swiping.current) {
            (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
        }
    }

    function onPointerUp(e: React.PointerEvent) {
        if (e.pointerType === 'touch') return;
        pointerActive.current = false;
        endGesture();
    }

    function onPointerCancel(e: React.PointerEvent) {
        if (e.pointerType === 'touch') return;
        pointerActive.current = false;
        resetGesture();
    }

    function onClickCapture(e: React.MouseEvent) {
        if (suppressClick.current) {
            suppressClick.current = false;
            e.preventDefault();
            e.stopPropagation();
        }
    }

    return (
        <div
            ref={rootRef}
            className={cn('relative', className)}
            style={{ touchAction: 'pan-y', overflow: 'clip' }}
            onPointerDown={onPointerDown}
            onPointerMove={onPointerMove}
            onPointerUp={onPointerUp}
            onPointerCancel={onPointerCancel}
            onClickCapture={onClickCapture}
        >
            {leftActions && (
                <div className="absolute inset-y-0 right-0 flex items-stretch">{leftActions}</div>
            )}
            {rightActions && (
                <div className="absolute inset-y-0 left-0 flex items-stretch">{rightActions}</div>
            )}
            <div
                ref={contentRef}
                className={cn('relative bg-card', contentClassName)}
            >
                {children}
            </div>
        </div>
    );
}
