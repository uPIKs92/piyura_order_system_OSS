import { useState } from 'react';
import { Tag, Trash2, X } from 'lucide-react';
import { IosSwipeRow } from '@/components/ios/IosSwipeRow';
import { QtyStepper } from '@/components/orders/QtyStepper';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatCurrency, haptic } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ProductUnit } from '@/lib/types';

interface CartLineProps {
    unit: ProductUnit;
    quantity: number;
    discountType: string;
    discountValue: number;
    onQuantityChange: (quantity: number) => void;
    onDiscountChange: (discountType: string, discountValue: number) => void;
    onRemove: () => void;
}

export function CartLine({
    unit,
    quantity,
    discountType,
    discountValue,
    onQuantityChange,
    onDiscountChange,
    onRemove,
}: CartLineProps) {
    const [showDiscount, setShowDiscount] = useState(discountValue > 0);

    const handleRemove = () => {
        haptic();
        onRemove();
    };

    const gross = unit.harga_jual * quantity;
    const discount =
        discountType === 'percent' ? (gross * discountValue) / 100 : discountValue;
    const subtotal = Math.max(0, gross - discount);
    const nama = unit.product?.nama ?? 'Produk';
    const hasDiscount = discountValue > 0;
    const lowStock = unit.stok <= quantity;

    return (
        <IosSwipeRow
            onSwipeLeft={handleRemove}
            className="rounded-xl border"
            leftActions={
                <button
                    type="button"
                    onClick={handleRemove}
                    className="action-delete px-4 text-xs font-medium"
                >
                    Hapus
                </button>
            }
        >
            <div className="flex flex-col gap-2 p-3.5">
                <div className="flex items-center gap-2">
                    <span className="flex min-w-0 flex-1 items-center gap-1.5">
                        <span className="truncate text-sm font-medium">{nama}</span>
                        {lowStock && (
                            <span
                                role="img"
                                aria-label={`Stok tinggal ${unit.stok}`}
                                className={cn(
                                    'size-2 shrink-0 rounded-full',
                                    unit.stok === 0 ? 'bg-destructive' : 'bg-warning',
                                )}
                            />
                        )}
                    </span>
                    <span className="shrink-0 text-xs text-muted-foreground">
                        {unit.satuan}
                    </span>
                    <button
                        type="button"
                        onClick={handleRemove}
                        aria-label={`Hapus ${nama}`}
                        className="-mr-1 shrink-0 rounded-md p-1 text-muted-foreground"
                    >
                        <Trash2 className="size-4" />
                    </button>
                </div>
                <p className="text-xs tabular-nums text-muted-foreground">
                    {formatCurrency(unit.harga_jual)} × {quantity}
                </p>
                <div className="flex items-center justify-between gap-2">
                    <QtyStepper value={quantity} onChange={onQuantityChange} />
                    <div className="flex items-center gap-2">
                        <span className="shrink-0 text-sm font-semibold">
                            {formatCurrency(subtotal)}
                        </span>
                        <button
                            type="button"
                            onClick={() => {
                                haptic();
                                setShowDiscount((v) => !v);
                            }}
                            aria-label="Toggle diskon"
                            aria-pressed={showDiscount}
                            className="relative shrink-0 rounded-md border bg-background p-1.5 text-muted-foreground"
                        >
                            <Tag className="size-4" />
                            {hasDiscount && !showDiscount && (
                                <span className="absolute -right-0.5 -top-0.5 size-2 rounded-full bg-warning ring-2 ring-background" />
                            )}
                        </button>
                    </div>
                </div>
                {showDiscount && (
                    <div className="rounded-lg border border-warning/30 bg-warning/10 p-2.5">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-warning">
                                Diskon
                            </span>
                            <button
                                type="button"
                                onClick={() => {
                                    haptic();
                                    setShowDiscount(false);
                                }}
                                aria-label="Tutup diskon"
                                className="-mr-1 rounded-md p-1 text-warning/60"
                            >
                                <X className="size-3.5" />
                            </button>
                        </div>
                        <div className="mt-2 flex items-center gap-2">
                            <Select
                                value={discountType || 'fixed'}
                                onValueChange={(value) => onDiscountChange(value, discountValue)}
                            >
                                <SelectTrigger size="sm" className="h-8 px-2">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="fixed">Rp</SelectItem>
                                        <SelectItem value="percent">%</SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <Input
                                type="number"
                                min={0}
                                placeholder="0"
                                value={discountValue || ''}
                                onChange={(e) =>
                                    onDiscountChange(discountType, Number(e.target.value))
                                }
                                className="h-8 flex-1 px-2"
                                aria-label="Nilai diskon"
                            />
                        </div>
                    </div>
                )}
            </div>
        </IosSwipeRow>
    );
}
