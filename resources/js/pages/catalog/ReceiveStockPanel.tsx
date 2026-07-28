import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { toast } from 'sonner';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { GroupedList, GroupedListDivider } from '@/components/ui/grouped-list';
import { IosListRow } from '@/components/ios/IosListRow';
import { ProductSearchSelect } from '@/components/ios/ProductSearchSelect';
import { useApi } from '@/lib/ApiProvider';
import { haptic } from '@/lib/format';
import type { Product, StockReceipt } from '@/lib/types';

type ReceiveLine = {
    product_unit_id: number;
    quantity: number;
    unit_cost?: number;
};

interface ReceiveStockPanelProps {
    onSuccess?: () => void;
    formId?: string;
    hideSubmit?: boolean;
    onSavingChange?: (saving: boolean) => void;
    onLinesChange?: (count: number) => void;
}

export function ReceiveStockPanel({
    onSuccess,
    formId = 'receive-stock-form',
    hideSubmit = false,
    onSavingChange,
    onLinesChange,
}: ReceiveStockPanelProps) {
    const { api } = useApi();
    const [products, setProducts] = useState<Product[]>([]);
    const [supplierName, setSupplierName] = useState('');
    const [notes, setNotes] = useState('');
    const [lines, setLines] = useState<ReceiveLine[]>([]);
    const [draftUnitId, setDraftUnitId] = useState<number | string>('');
    const [draftQty, setDraftQty] = useState(1);
    const [saving, setSaving] = useState(false);

    const loadProducts = useCallback(async () => {
        try {
            const data = await api.list<Product[]>('products');
            setProducts(data);
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memuat produk');
        }
    }, [api]);

    useEffect(() => {
        loadProducts();
    }, [loadProducts]);

    useEffect(() => {
        onSavingChange?.(saving);
    }, [onSavingChange, saving]);

    useEffect(() => {
        onLinesChange?.(lines.length);
    }, [lines.length, onLinesChange]);

    function addLine() {
        const unitId = Number(draftUnitId);
        if (!unitId || draftQty < 1) {
            toast.error('Pilih produk dan jumlah');
            return;
        }
        setLines((current) => {
            const existing = current.find((line) => line.product_unit_id === unitId);
            if (existing) {
                return current.map((line) =>
                    line.product_unit_id === unitId
                        ? { ...line, quantity: line.quantity + draftQty }
                        : line,
                );
            }
            return [...current, { product_unit_id: unitId, quantity: draftQty }];
        });
        setDraftUnitId('');
        setDraftQty(1);
    }

    function removeLine(unitId: number) {
        setLines((current) => current.filter((line) => line.product_unit_id !== unitId));
    }

    function lineLabel(unitId: number): string {
        for (const product of products) {
            const unit = product.units?.find((u) => u.id === unitId);
            if (unit) return `${product.nama} (${unit.satuan})`;
        }
        return `Unit #${unitId}`;
    }

    async function submit(event?: FormEvent) {
        event?.preventDefault();
        if (!lines.length) {
            toast.error('Tambahkan minimal satu baris');
            return;
        }
        setSaving(true);
        try {
            const receipt = await api.request<StockReceipt>('/inventory/receipts', {
                method: 'POST',
                body: JSON.stringify({
                    supplier_name: supplierName.trim() || undefined,
                    notes: notes.trim() || undefined,
                    items: lines,
                }),
            });
            haptic();
            toast.success(`Penerimaan ${receipt.receipt_no} tersimpan`);
            setLines([]);
            setSupplierName('');
            setNotes('');
            await loadProducts();
            onSuccess?.();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menyimpan penerimaan');
        } finally {
            setSaving(false);
        }
    }

    return (
        <form id={formId} className="flex flex-col gap-4" onSubmit={submit}>
            <FieldGroup>
                <Field>
                    <FieldLabel>Supplier (opsional)</FieldLabel>
                    <Input value={supplierName} onChange={(e) => setSupplierName(e.target.value)} />
                </Field>
                <Field>
                    <FieldLabel>Catatan</FieldLabel>
                    <Textarea value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} />
                </Field>
            </FieldGroup>

            <Card size="sm" className="shadow-none">
                <CardHeader>
                    <CardTitle>Tambah baris</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-3">
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <ProductSearchSelect
                            products={products}
                            value={draftUnitId}
                            onChange={setDraftUnitId}
                        />
                        <Input
                            type="number"
                            min={1}
                            className="w-full sm:w-24"
                            value={draftQty}
                            onChange={(e) => setDraftQty(Math.max(1, Number(e.target.value) || 1))}
                        />
                        <Button type="button" onClick={addLine}>
                            <Plus data-icon="inline-start" />
                            Tambah
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {lines.length > 0 && (
                <GroupedList>
                    {lines.map((line, index) => (
                        <div key={line.product_unit_id}>
                            {index > 0 && <GroupedListDivider />}
                            <IosListRow
                                title={lineLabel(line.product_unit_id)}
                                trailing={
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm font-medium">×{line.quantity}</span>
                                        <Button
                                            type="button"
                                            size="icon-sm"
                                            variant="ghost"
                                            className="text-destructive"
                                            onClick={() => removeLine(line.product_unit_id)}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                }
                            />
                        </div>
                    ))}
                </GroupedList>
            )}

            {!hideSubmit && (
                <Button type="submit" disabled={saving || lines.length === 0}>
                    Simpan Penerimaan
                </Button>
            )}
        </form>
    );
}
