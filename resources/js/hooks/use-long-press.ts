import { useCallback, useRef } from 'react';

interface LongPressHandlers {
    onPointerDown: (e: React.PointerEvent) => void;
    onPointerUp: (e: React.PointerEvent) => void;
    onPointerLeave: (e: React.PointerEvent) => void;
    onPointerCancel: (e: React.PointerEvent) => void;
    onPointerMove: (e: React.PointerEvent) => void;
    onTouchStart: (e: React.TouchEvent) => void;
    onTouchMove: (e: React.TouchEvent) => void;
    onTouchEnd: (e: React.TouchEvent) => void;
}

const MOVE_THRESHOLD = 10; // px — cancel if finger drifts past this (scroll intent)

/**
 * Long-press detector with BOTH pointer and touch handlers.
 *
 * Why touch handlers are needed alongside pointer handlers:
 * - IosSwipeRow uses a non-passive `touchmove` with `preventDefault()` to take
 *   control of horizontal swipes. On iOS Safari this suppresses the subsequent
 *   `pointermove` events this hook relies on to cancel its timer.
 * - Without touch-based cancellation, a slow horizontal swipe reaches the 500ms
 *   delay and fires the long-press (e.g. delete dialog) mid-swipe — blocking the
 *   swipe gesture the user intended.
 * - Touch events bubble through React's root delegation independently of the
 *   pointer event stream, so `onTouchMove` fires reliably even after
 *   `preventDefault()` and cancels the timer.
 * - Pointer handlers are kept for desktop (mouse) support.
 */
export function useLongPress(onLongPress: () => void, delay = 500): LongPressHandlers {
    const timer = useRef<number | null>(null);
    const origin = useRef<{ x: number; y: number } | null>(null);

    const clear = useCallback(() => {
        if (timer.current !== null) {
            window.clearTimeout(timer.current);
            timer.current = null;
        }
        origin.current = null;
    }, []);

    const start = useCallback(
        (x: number, y: number) => {
            origin.current = { x, y };
            clear();
            timer.current = window.setTimeout(() => {
                onLongPress();
                timer.current = null;
            }, delay);
        },
        [onLongPress, delay, clear],
    );

    const move = useCallback(
        (x: number, y: number) => {
            if (!origin.current || timer.current === null) return;
            const dx = x - origin.current.x;
            const dy = y - origin.current.y;
            if (Math.hypot(dx, dy) > MOVE_THRESHOLD) clear();
        },
        [clear],
    );

    const onPointerDown = useCallback(
        (e: React.PointerEvent) => start(e.clientX, e.clientY),
        [start],
    );

    const onPointerMove = useCallback(
        (e: React.PointerEvent) => move(e.clientX, e.clientY),
        [move],
    );

    const onTouchStart = useCallback(
        (e: React.TouchEvent) => {
            const t = e.touches[0];
            if (t) start(t.clientX, t.clientY);
        },
        [start],
    );

    const onTouchMove = useCallback(
        (e: React.TouchEvent) => {
            const t = e.touches[0];
            if (t) move(t.clientX, t.clientY);
        },
        [move],
    );

    return {
        onPointerDown,
        onPointerMove,
        onPointerUp: clear,
        onPointerLeave: clear,
        onPointerCancel: clear,
        onTouchStart,
        onTouchMove,
        onTouchEnd: clear,
    };
}
