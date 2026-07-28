import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Minus, Plus, ScanLine } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    Drawer,
    DrawerContent,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { ProductSearchSelect } from '@/components/ios/ProductSearchSelect';
import { useApi } from '@/lib/ApiProvider';
import { haptic } from '@/lib/format';
import type { Product, ProductUnit } from '@/lib/types';

export type RestockTarget = {
    productName: string;
    unit: ProductUnit;
};

interface RestockDrawerProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    target: RestockTarget | null;
    onSuccess?: () => void;
    showBarcode?: boolean;
    showProductSearch?: boolean;
    title?: string;
}

export function RestockDrawer({
    open,
    onOpenChange,
    target,
    onSuccess,
    showBarcode = true,
    showProductSearch = true,
    title = 'Restok cepat',
}: RestockDrawerProps) {
    const { api } = useApi();
    const [quantity, setQuantity] = useState(1);
    const [notes, setNotes] = useState('');
    const [barcode, setBarcode] = useState('');
    const [saving, setSaving] = useState(false);
    const [products, setProducts] = useState<Product[]>([]);
    const [searchUnitId, setSearchUnitId] = useState<number | string>('');
    const [lookupTarget, setLookupTarget] = useState<RestockTarget | null>(null);

    const active = lookupTarget ?? target;

    const loadProducts = useCallback(async () => {
        try {
            setProducts(await api.list<Product[]>('products'));
        } catch {
            // ignore — barcode path still works
        }
    }, [api]);

    useEffect(() => {
        if (open) {
            setQuantity(1);
            setNotes('');
            setBarcode('');
            setSearchUnitId('');
            setLookupTarget(null);
            if (!target && showProductSearch) {
                loadProducts();
            }
        }
    }, [open, target, showProductSearch, loadProducts]);

    useEffect(() => {
        if (!searchUnitId) return;
        for (const product of products) {
            const unit = product.units?.find((u) => u.id === Number(searchUnitId));
            if (unit) {
                setLookupTarget({ productName: product.nama, unit });
                break;
            }
        }
    }, [searchUnitId, products]);

    function bump(delta: number) {
        setQuantity((q) => Math.max(1, q + delta));
    }

    async function lookupBarcode() {
        const code = barcode.trim();
        if (!code) return;
        try {
            const product = await api.list<Product>('products/lookup', { barcode: code });
            const units = product.units ?? [];
            if (!units.length) {
                toast.error('Produk tanpa satuan');
                return;
            }
            const unit = units.find((u) => u.is_default) ?? units[0];
            setLookupTarget({ productName: product.nama, unit });
            setSearchUnitId(unit.id);
            haptic();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Barcode tidak ditemukan');
        }
    }

    async function submit() {
        if (!active) return;
        setSaving(true);
        try {
            await api.request(`/product-units/${active.unit.id}/restock`, {
                method: 'POST',
                body: JSON.stringify({ quantity, notes: notes.trim() || undefined }),
            });
            haptic();
            toast.success('Stok ditambahkan');
            onOpenChange(false);
            onSuccess?.();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal restok');
        } finally {
            setSaving(false);
        }
    }

    const presetQty = [1, 5, 10].includes(quantity) ? String(quantity) : '';

    return (
        <Drawer open={open} onOpenChange={onOpenChange} showSwipeHandle>
            <DrawerContent className="max-h-[90vh]">
                <DrawerHeader>
                    <DrawerTitle>{title}</DrawerTitle>
                </DrawerHeader>
                <div className="overflow-y-auto px-4 pb-4">
                    {!active && (showBarcode || showProductSearch) && (
                        <FieldGroup className="mb-4">
                            {showProductSearch && (
                                <Field>
                                    <FieldLabel>Cari produk</FieldLabel>
                                    <ProductSearchSelect
                                        products={products}
                                        value={searchUnitId}
                                        onChange={setSearchUnitId}
                                    />
                                </Field>
                            )}
                            {showBarcode && (
                                <Field>
                                    <FieldLabel>Scan barcode</FieldLabel>
                                    <div className="flex gap-2">
                                        <Input
                                            value={barcode}
                                            onChange={(e) => setBarcode(e.target.value)}
                                            placeholder="Masukkan barcode..."
                                            onKeyDown={(e) => e.key === 'Enter' && lookupBarcode()}
                                        />
                                        <Button type="button" variant="outline" size="icon" onClick={lookupBarcode}>
                                            <ScanLine />
                                        </Button>
                                    </div>
                                </Field>
                            )}
                        </FieldGroup>
                    )}

                    {active ? (
                        <FieldGroup>
                            <Card size="sm" className="shadow-none">
                                <CardContent className="text-sm">
                                    <p className="font-medium">{active.productName}</p>
                                    <p className="text-muted-foreground">
                                        {active.unit.satuan} • stok saat ini: {active.unit.stok}
                                    </p>
                                </CardContent>
                            </Card>

                            <Field>
                                <FieldLabel>Jumlah tambah</FieldLabel>
                                <div className="flex items-center gap-2">
                                    <Button type="button" variant="outline" size="icon" onClick={() => bump(-1)}>
                                        <Minus />
                                    </Button>
                                    <Input
                                        type="number"
                                        min={1}
                                        className="text-center"
                                        value={quantity}
                                        onChange={(e) => setQuantity(Math.max(1, Number(e.target.value) || 1))}
                                    />
                                    <Button type="button" variant="outline" size="icon" onClick={() => bump(1)}>
                                        <Plus />
                                    </Button>
                                </div>
                                <ToggleGroup
                                    type="single"
                                    value={presetQty}
                                    onValueChange={(value) => value && setQuantity(Number(value))}
                                    className="mt-2 justify-start"
                                >
                                    {[1, 5, 10].map((n) => (
                                        <ToggleGroupItem key={n} value={String(n)} size="sm">
                                            +{n}
                                        </ToggleGroupItem>
                                    ))}
                                </ToggleGroup>
                            </Field>

                            <Field>
                                <FieldLabel>Catatan (opsional)</FieldLabel>
                                <Textarea value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} />
                            </Field>
                        </FieldGroup>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Cari produk atau scan barcode untuk restok.
                        </p>
                    )}
                </div>
                <DrawerFooter>
                    <div className="flex gap-2">
                        <Button variant="outline" className="flex-1" onClick={() => onOpenChange(false)}>
                            Batal
                        </Button>
                        <Button className="flex-1" disabled={!active || saving} onClick={submit}>
                            {saving ? <Spinner data-icon="inline-start" /> : null}
                            Simpan
                        </Button>
                    </div>
                </DrawerFooter>
            </DrawerContent>
        </Drawer>
    );
}
