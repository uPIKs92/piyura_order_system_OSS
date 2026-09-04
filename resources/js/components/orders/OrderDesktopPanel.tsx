import type { ReactNode } from 'react';
import { Sheet, SheetContent, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { CartReviewContent } from '@/components/orders/CartReviewSheet';
import { PosProductGrid } from '@/components/orders/PosProductGrid';
import type { OrderFormData, Product, ProductUnit } from '@/lib/types';

interface OrderDesktopPanelProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'form' | 'detail';
    title: ReactNode;
    /** Detail mode: scrollable body content (rendered in overflow-y-auto). */
    detailBody?: ReactNode;
    /** Detail mode: pinned footer actions (OrderStatusActions + Ubah/Bayar). */
    detailFooter?: ReactNode;
    /** Form mode: banner/status-stepper block rendered above the product grid. */
    formAside?: ReactNode;
    // Form-mode props (same shape as CartReviewContent's):
    products: Product[];
    cartQuantities: Record<number, number>;
    onAdd: (productUnitId: number) => void;
    cartItems: { item: OrderFormData['items'][number]; idx: number }[];
    unitMap: Map<number, ProductUnit>;
    total: number;
    form: OrderFormData;
    onFormChange: (patch: Partial<OrderFormData>) => void;
    onUpdateItem: (idx: number, patch: Partial<OrderFormData['items'][number]>) => void;
    onRemoveItem: (idx: number) => void;
    onSave: () => void;
    onSubmit: () => void;
    canSubmit: boolean;
    storedDeliveryFee?: number | string;
    storedDeliveryKm?: number | string | null;
}

/**
 * Wide right-side Sheet for the Orders page on desktop (>= 1024px), replacing
 * the mobile bottom drawer. Supports two modes: `form` (POS product grid left
 * + cart review right) and `detail` (scrollable body + pinned footer actions).
 */
export function OrderDesktopPanel({
    open,
    onOpenChange,
    mode,
    title,
    detailBody,
    detailFooter,
    formAside,
    products,
    cartQuantities,
    onAdd,
    cartItems,
    unitMap,
    total,
    form,
    onFormChange,
    onUpdateItem,
    onRemoveItem,
    onSave,
    onSubmit,
    canSubmit,
    storedDeliveryFee,
    storedDeliveryKm,
}: OrderDesktopPanelProps) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="data-[side=right]:sm:max-w-5xl">
                <SheetHeader className="px-4 pt-6 pb-4">
                    <SheetTitle>{title}</SheetTitle>
                </SheetHeader>
                {mode === 'detail' ? (
                    <>
                        <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pb-6">
                            {detailBody}
                        </div>
                        {detailFooter != null && (
                            <SheetFooter className="flex-col gap-3 border-t">{detailFooter}</SheetFooter>
                        )}
                    </>
                ) : (
                    <div className="grid min-h-0 flex-1 lg:grid-cols-[1fr_360px]">
                        <div className="flex min-h-0 min-w-0 flex-col border-r">
                            {formAside}
                            <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                                <PosProductGrid products={products} cartQuantities={cartQuantities} onAdd={onAdd} />
                            </div>
                        </div>
                        <div className="flex min-h-0 min-w-0 flex-col">
                            <CartReviewContent
                                cartItems={cartItems}
                                unitMap={unitMap}
                                total={total}
                                form={form}
                                onFormChange={onFormChange}
                                onUpdateItem={onUpdateItem}
                                onRemoveItem={onRemoveItem}
                                onSave={onSave}
                                onSubmit={onSubmit}
                                canSubmit={canSubmit}
                                storedDeliveryFee={storedDeliveryFee}
                                storedDeliveryKm={storedDeliveryKm}
                            />
                        </div>
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
