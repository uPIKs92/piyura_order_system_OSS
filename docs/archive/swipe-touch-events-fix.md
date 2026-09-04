# Task: Fix swipe-to-action on iOS Safari

## Problem

Swipe-to-action on the Orders list works in Chromium (Playwright passes) but
**does not work on real iOS Safari**. It worked before the refactor from
`react-swipeable` to native pointer events.

## Root cause

The refactor moved from **Touch Events** (`react-swipeable` with
`preventScrollOnSwipe: true`) to **Pointer Events** with `touch-action: pan-y`.

On iOS Safari, `touch-action: pan-y` causes the browser to claim any gesture
with even slight vertical drift for native scrolling. It fires `pointercancel`
**before** our 8px direction-lock threshold fires — typically within 3-5px.
At that point `swiping.current` is still `false`, so the cancel handler
early-returns and the gesture is silently abandoned.

Chromium is more lenient (~10px before committing), so the Playwright test
passes. This is a fundamental WebKit vs Chromium divergence in pointer-event
delivery.

## Why the old code worked

`react-swipeable` used **Touch Events** with `preventScrollOnSwipe: true`:
- `touchstart` / `touchmove` / `touchend` listeners
- Called `e.preventDefault()` on `touchmove` to block native scrolling during a
  confirmed horizontal swipe
- Touch events are **always delivered** (unlike pointer events which get
  cancelled). `preventDefault()` on a non-passive `touchmove` listener is the
  definitive way to take control of a gesture.

## Solution: Touch Events with non-passive touchmove

Switch `IosSwipeRow` back to Touch Events. The performance concern that
motivated the refactor (20 rows × non-passive listeners = scroll lag) is
addressed by:
- Calling `preventDefault()` **only** when a horizontal swipe is confirmed
- Returning early from `touchmove` for vertical movement (zero cost)

### Files to change

1. **`IosSwipeRow.tsx`** — Replace pointer event handlers with touch event
   listeners attached via `addEventListener` (non-passive `touchmove`).
   Keep: direction-lock logic, ref-based transforms, click suppression,
   `overflow: clip`.

2. **`IosListRow.tsx`** — Remove vestigial pointer event passthrough props
   (no longer needed; swipe is on parent, long-press has its own handlers).

3. **`swipe.spec.js`** — Dispatch `TouchEvent` via `page.evaluate` instead of
   `page.mouse` (mouse events don't trigger touch listeners).

### Coexistence with other touch systems

- **Pull-to-refresh** (`use-pull-to-refresh.tsx`): Uses `onTouchStart/Move/End`
  on a wrapper div. Checks for 80px vertical drag when scrolled to top. Swipe's
  8px direction lock fires first for horizontal gestures — no conflict.
- **Long-press** (`use-long-press.ts``): Uses pointer events on the inner
  button. Cancels on >10px movement. Swipe's touch events fire alongside; both
  coexist (long-press cancels on movement, swipe takes over for horizontal).
