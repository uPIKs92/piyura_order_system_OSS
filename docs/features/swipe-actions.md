# Swipe-to-Action (Orders)

> Reference doc for the swipe gesture on the `/orders` list. Linked from story
> [08 — Mobile UI](08-mobile-ui.md) (8.3 Swipe actions) and the project
> [README](../../README.md).

Each order row in the list is swipeable. The available swipe actions are
**status-gated** (and, for Bayar, **payment-gated**) — a row in a terminal
state (`selesai` / `batal`) renders no actions at all, and so does a fully
paid `diproses` / `dikirim` row, so swiping those does nothing by design.
This is the single most common reason the gesture *appears* "not working".

## Direction → Action mapping

The row follows iOS trailing/leading-action semantics: the button is anchored on
the **opposite** edge from the drag direction.

| Gesture | Reveals | Button position | Fires |
|---------|---------|-----------------|-------|
| **Swipe left** (← drag) | 💳 **Bayar** (Pay) | right edge | `onPayment` |
| **Swipe right** (→ drag) | 🚫 **Batal** (Cancel) | left edge | `onCancel` |

Release **past the trigger distance** to commit the action; release short of it
and the row snaps back to neutral without firing anything.

## Status eligibility

Whether a swipe action exists depends on the order's status and, for Bayar,
its payment status.

| Status (label) | ← Bayar | → Batal | Notes |
|----------------|:-------:|:-------:|-------|
| `draft` (Draft) | ✗ | ✓ | Not yet payable — advance to `pending` first |
| `pending` (Menunggu) | ✓¹ | ✓ | The only status with **both** actions |
| `diproses` (Diproses) | ✓¹ | ✗ | Payable while unpaid — cannot be cancelled from list |
| `dikirim` (Dikirim) | ✓¹ | ✗ | Payable while unpaid — advance to `selesai` only |
| `selesai` (Selesai) | ✗ | ✗ | Terminal |
| `cancelled` (Batal) | ✗ | ✗ | Terminal |

¹ Bayar is hidden if `payment_status === 'paid'`, in every payable status
(`pending` / `diproses` / `dikirim`).

## Gating logic (source of truth)

Defined in [`resources/js/components/orders/OrderRow.tsx`](../../resources/js/components/orders/OrderRow.tsx)
and [`resources/js/lib/orderStatus.ts`](../../resources/js/lib/orderStatus.ts):

```ts
// Bayar (left-swipe action)
const canPay = ['pending', 'diproses', 'dikirim'].includes(order.status) && order.payment_status !== 'paid';

// Batal (right-swipe action)
const cancellable = canCancel(order.status);

// canCancel() mirrors the backend state machine:
//   true only when 'cancelled' is an allowed next status
//   → draft → ['pending', 'cancelled']   ✓
//   → pending → ['diproses', 'cancelled'] ✓
//   → diproses/dikirim/selesai/cancelled   ✗
```

The JS transition map is kept in sync with
`App\Enums\OrderStatus::allowedTransitions()` (PHP backend). Both must agree.

## Gesture thresholds

Tuned in [`IosSwipeRow.tsx`](../../resources/js/components/ios/IosSwipeRow.tsx):

| Constant | Value | Meaning |
|----------|------:|---------|
| `DIRECTION_THRESHOLD` | 8 px | Horizontal commit distance — below this the gesture stays native scroll |
| `LEFT_TRIGGER` | 80 px | Drag left ≥ this far → commit **Bayar** |
| `RIGHT_TRIGGER` | 60 px | Drag right ≥ this far → commit **Batal** |
| `LEFT_MAX` | 120 px | Rubber-band max travel to the left |
| `RIGHT_MAX` | 96 px | Rubber-band max travel to the right |

## Input pathways (cross-platform)

`IosSwipeRow` uses a **hybrid** input model — two pathways feeding the same
shared gesture logic:

1. **Touch Events** (non-passive `touchmove` via `addEventListener`) —
   iOS Safari + Android Chrome. `preventDefault()` is called **only after** a
   horizontal swipe is confirmed, reclaiming the gesture from native scroll.
   Vertical movement returns early, so native scrolling stays smooth. This was
   the fix for the original "swipe broken on iOS" bug (see
   [../archive/swipe-touch-events-fix.md](../archive/swipe-touch-events-fix.md)).
2. **Pointer Events** (React props, `pointerType !== 'touch'`) —
   desktop mouse/pen. The `pointerType === 'touch'` guard prevents
   double-processing on touch devices.

> **Desktop note:** with a mouse, a "swipe" is a **press-and-drag**
> (hold the left button down, then drag horizontally). Hovering does nothing.

## Coexistence with other touch systems

- **Pull-to-refresh** (`resources/js/hooks/use-pull-to-refresh.tsx`): checks for ~80 px
  vertical drag when scrolled to top. Swipe's 8 px direction lock fires first
  for horizontal gestures — no conflict.
- **Long-press → delete** (`resources/js/hooks/use-long-press.ts`, owner only): pointer
  events on the inner element; cancels on >10 px movement. Long-press and swipe
  coexist (long-press cancels on movement; swipe takes over for horizontal).

## Other row interactions

| Input | Action | Availability |
|-------|--------|--------------|
| **Tap** | Open order detail (edit) | all rows |
| **Long-press** | Delete prompt | owner only |

The tap and long-press are disambiguated via a `longPressFired` ref: after a
hold fires delete, the click that follows `pointerup` is suppressed so both
don't fire.

## Files

| File | Role |
|------|------|
| `resources/js/components/ios/IosSwipeRow.tsx` | Generic swipeable row + gesture engine |
| `resources/js/components/orders/OrderRow.tsx` | Wires status-gated Bayar/Batal actions into `IosSwipeRow` |
| `resources/js/lib/orderStatus.ts` | Status labels, transition map, `canCancel()` |
| `resources/js/pages/Orders.tsx` | `quickCancel()` / payment handlers + list `refetch()` |
