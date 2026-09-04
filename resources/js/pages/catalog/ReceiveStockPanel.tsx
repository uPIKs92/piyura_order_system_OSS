import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react';
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
import { SupplierSelect } from '@/components/inventory/SupplierSelect';
import { useApi } from '@/lib/ApiProvider';
import { listAll } from '@/lib/listAll';
import { haptic } from '@/lib/format';
import type { Product, StockReceipt } from '@/lib/types';

type ReceiveLine = {
    key: number;
    product_unit_id: number;
    quantity: number;
    unit_cost?: number;
    expired_at?: string;
    batch_no?: string;
};

function formatExpiry(value: string): string {
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

function lineSubtitle(line: ReceiveLine): string | undefined {
    const parts: string[] = [];
    if (line.batch_no) parts.push(`Batch ${line.batch_no}`);
    if (line.expired_at) parts.push(`Edp. ${formatExpiry(line.expired_at)}`);
    return parts.length ? parts.join(' • ') : undefined;
}

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
    const [supplierId, setSupplierId] = useState<number | null>(null);
    const [supplierName, setSupplierName] = useState('');
    const [notes, setNotes] = useState('');
    const [lines, setLines] = useState<ReceiveLine[]>([]);
    const [draftUnitId, setDraftUnitId] = useState<number | string>('');
    const [draftQty, setDraftQty] = useState(1);
    const [draftExpiredAt, setDraftExpiredAt] = useState('');
    const [draftBatchNo, setDraftBatchNo] = useState('');
    const [saving, setSaving] = useState(false);
    const lineKeyRef = useRef(0);

    const loadProducts = useCallback(async () => {
        try {
            // Line picker needs every product unit; endpoint paginates, so walk bounded pages.
            const data = await listAll<Product>(api, 'products');
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
        const expiredAt = draftExpiredAt || undefined;
        const batchNo = draftBatchNo.trim() || undefined;
        const newKey = ++lineKeyRef.current;
        setLines((current) => {
            // Gabung hanya bila satuan + kedaluwarsa + batch sama;
            // selisih expiry/batch menjadi baris terpisah.
            const existing = current.find(
                (line) =>
                    line.product_unit_id === unitId &&
                    (line.expired_at ?? '') === (expiredAt ?? '') &&
                    (line.batch_no ?? '') === (batchNo ?? ''),
            );
            if (existing) {
                return current.map((line) =>
                    line.key === existing.key
                        ? { ...line, quantity: line.quantity + draftQty }
                        : line,
                );
            }
            return [
                ...current,
                {
                    key: newKey,
                    product_unit_id: unitId,
                    quantity: draftQty,
                    expired_at: expiredAt,
                    batch_no: batchNo,
                },
            ];
        });
        setDraftUnitId('');
        setDraftQty(1);
        setDraftExpiredAt('');
        setDraftBatchNo('');
    }

    function removeLine(lineKey: number) {
        setLines((current) => current.filter((line) => line.key !== lineKey));
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
                    supplier_id: supplierId ?? undefined,
                    supplier_name: supplierId === null ? supplierName.trim() || undefined : undefined,
                    notes: notes.trim() || undefined,
                    items: lines.map((line) => ({
                        product_unit_id: line.product_unit_id,
                        quantity: line.quantity,
                        unit_cost: line.unit_cost,
                        expired_at: line.expired_at || undefined,
                        batch_no: line.batch_no?.trim() || undefined,
                    })),
                }),
            });
            haptic();
            toast.success(`Penerimaan ${receipt.receipt_no} tersimpan`);
            setLines([]);
            setSupplierId(null);
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
                    <SupplierSelect
                        supplierId={supplierId}
                        onSupplierIdChange={setSupplierId}
                        manualName={supplierName}
                        onManualNameChange={setSupplierName}
                    />
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
                    <div className="flex flex-col gap-2">
                        <ProductSearchSelect
                            products={products}
                            value={draftUnitId}
                            onChange={setDraftUnitId}
                        />
                        <div className="flex flex-col gap-2 lg:flex-row lg:items-center">
                            <Input
                                type="number"
                                min={1}
                                className="w-full lg:w-24"
                                value={draftQty}
                                onChange={(e) => setDraftQty(Math.max(1, Number(e.target.value) || 1))}
                            />
                            <Button type="button" className="lg:flex-1" onClick={addLine}>
                                <Plus data-icon="inline-start" />
                                Tambah
                            </Button>
                        </div>
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <Field>
                                <FieldLabel>Kedaluwarsa (ops.)</FieldLabel>
                                <Input
                                    type="date"
                                    value={draftExpiredAt}
                                    onChange={(e) => setDraftExpiredAt(e.target.value)}
                                />
                            </Field>
                            <Field>
                                <FieldLabel>No. batch (ops.)</FieldLabel>
                                <Input
                                    value={draftBatchNo}
                                    onChange={(e) => setDraftBatchNo(e.target.value)}
                                    placeholder="cth. B-2401"
                                />
                            </Field>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {lines.length > 0 && (
                <GroupedList>
                    {lines.map((line, index) => (
                        <div key={line.key}>
                            {index > 0 && <GroupedListDivider />}
                            <IosListRow
                                title={lineLabel(line.product_unit_id)}
                                subtitle={lineSubtitle(line)}
                                trailing={
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm font-medium">×{line.quantity}</span>
                                        <Button
                                            type="button"
                                            size="icon-sm"
                                            variant="ghost"
                                            className="text-destructive"
                                            onClick={() => removeLine(line.key)}
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
