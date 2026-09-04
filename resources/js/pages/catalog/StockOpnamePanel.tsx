import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { ClipboardCheck, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldLabel } from '@/components/ui/field';
import { GroupedList, GroupedListDivider } from '@/components/ui/grouped-list';
import { IosListRow } from '@/components/ios/IosListRow';
import { ProductSearchSelect } from '@/components/ios/ProductSearchSelect';
import { useApi } from '@/lib/ApiProvider';
import { listAll } from '@/lib/listAll';
import { flattenProductUnits } from '@/lib/products';
import { haptic } from '@/lib/format';
import type { Product, ProductUnit, StockOpnameResponse, StockOpnameResult } from '@/lib/types';

interface StockOpnamePanelProps {
    onInventoryChange?: () => void;
}

type OpnameRow = {
    unit: ProductUnit;
    counted: string;
};

function DiffBadge({ diff }: { diff: number }) {
    if (diff === 0) return <Badge variant="secondary">Sesuai</Badge>;
    if (diff > 0) {
        return (
            <Badge className="bg-success/15 text-success">
                +{diff}
            </Badge>
        );
    }
    return <Badge variant="destructive">-{Math.abs(diff)}</Badge>;
}

export function StockOpnamePanel({ onInventoryChange }: StockOpnamePanelProps) {
    const { api } = useApi();
    const [products, setProducts] = useState<Product[]>([]);
    const [rows, setRows] = useState<OpnameRow[]>([]);
    const [draftUnitId, setDraftUnitId] = useState<number | string>('');
    const [notes, setNotes] = useState('');
    const [saving, setSaving] = useState(false);
    const [results, setResults] = useState<StockOpnameResult[]>([]);

    const loadProducts = useCallback(async () => {
        try {
            // Opname counts every unit; endpoint paginates, so walk bounded pages.
            setProducts(await listAll<Product>(api, 'products'));
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memuat produk');
        }
    }, [api]);

    useEffect(() => {
        loadProducts();
    }, [loadProducts]);

    const units = useMemo(() => flattenProductUnits(products), [products]);

    function addRow() {
        const unitId = Number(draftUnitId);
        if (!unitId) {
            toast.error('Pilih produk terlebih dahulu');
            return;
        }
        if (rows.some((row) => row.unit.id === unitId)) {
            toast.error('Produk sudah ada di daftar');
            return;
        }
        const unit = units.find((u) => u.id === unitId);
        if (!unit) return;
        haptic();
        setRows((current) => [...current, { unit, counted: '' }]);
        setDraftUnitId('');
    }

    function removeRow(unitId: number) {
        setRows((current) => current.filter((row) => row.unit.id !== unitId));
    }

    function setCounted(unitId: number, value: string) {
        const normalized = value.replace(/[^0-9]/g, '');
        setRows((current) =>
            current.map((row) =>
                row.unit.id === unitId ? { ...row, counted: normalized } : row,
            ),
        );
    }

    async function submit() {
        if (!rows.length) {
            toast.error('Pilih minimal satu produk');
            return;
        }
        if (rows.some((row) => row.counted === '')) {
            toast.error('Isi jumlah hitung fisik untuk semua produk');
            return;
        }
        if (saving) return;
        setSaving(true);
        try {
            const response = await api.request<StockOpnameResponse>('/inventory/opname', {
                method: 'POST',
                body: {
                    notes: notes.trim() || undefined,
                    items: rows.map((row) => ({
                        product_unit_id: row.unit.id,
                        counted_qty: Number(row.counted),
                    })),
                },
            });
            haptic();
            toast.success('Stok opname tersimpan');
            setResults(response.results);
            setRows([]);
            setDraftUnitId('');
            setNotes('');
            await loadProducts();
            onInventoryChange?.();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menyimpan opname');
        } finally {
            setSaving(false);
        }
    }

    return (
        <div className="flex flex-col gap-4">
            <Card size="sm" className="shadow-none">
                <CardHeader>
                    <CardTitle>Pilih produk</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-2">
                    <div className="flex flex-col gap-2 lg:flex-row lg:items-center">
                        <ProductSearchSelect
                            products={products}
                            value={draftUnitId}
                            onChange={setDraftUnitId}
                        />
                        <Button
                            type="button"
                            variant="outline"
                            className="lg:w-auto"
                            disabled={!Number(draftUnitId)}
                            onClick={addRow}
                        >
                            <Plus data-icon="inline-start" />
                            Tambah
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {rows.length > 0 && (
                <GroupedList>
                    {rows.map((row, index) => {
                        const counted = row.counted === '' ? null : Number(row.counted);
                        const diff = counted == null ? null : counted - Number(row.unit.stok);
                        return (
                            <div key={row.unit.id}>
                                {index > 0 && <GroupedListDivider />}
                                <div className="flex flex-col gap-2 px-5 py-3.5 sm:px-6">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="flex min-w-0 flex-col">
                                            <p className="truncate text-sm font-medium">
                                                {row.unit.product?.nama} ({row.unit.satuan})
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Sistem: {row.unit.stok}
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            size="icon-sm"
                                            variant="ghost"
                                            className="text-destructive"
                                            onClick={() => removeRow(row.unit.id)}
                                            aria-label="Hapus baris"
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Input
                                            type="number"
                                            inputMode="numeric"
                                            min={0}
                                            placeholder="Hitung fisik"
                                            className="w-32"
                                            value={row.counted}
                                            onChange={(e) => setCounted(row.unit.id, e.target.value)}
                                        />
                                        {diff != null && <DiffBadge diff={diff} />}
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </GroupedList>
            )}

            <Field>
                <FieldLabel>Catatan (opsional)</FieldLabel>
                <Textarea
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    rows={2}
                />
            </Field>

            <Button onClick={submit} disabled={saving || rows.length === 0}>
                <ClipboardCheck data-icon="inline-start" />
                {saving ? 'Menyimpan...' : 'Simpan Opname'}
            </Button>

            {results.length > 0 && (
                <Card size="sm" className="shadow-none">
                    <CardHeader>
                        <CardTitle>Hasil opname</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        <GroupedList>
                            {results.map((result, index) => (
                                <div key={result.product_unit_id}>
                                    {index > 0 && <GroupedListDivider />}
                                    <IosListRow
                                        title={`${result.product_name} (${result.satuan})`}
                                        subtitle={`Sistem ${result.system_qty} → Hitung ${result.counted_qty}`}
                                        trailing={<DiffBadge diff={result.diff} />}
                                    />
                                </div>
                            ))}
                        </GroupedList>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
