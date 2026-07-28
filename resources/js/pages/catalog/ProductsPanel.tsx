import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Plus, Search, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { GroupedList, GroupedListDivider } from '@/components/ui/grouped-list';
import { RowActions } from '@/components/ui/row-actions';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Empty, EmptyDescription, EmptyTitle } from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Drawer,
    DrawerContent,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { IosListRow } from '@/components/ios/IosListRow';
import { RestockDrawer, type RestockTarget } from '@/components/inventory/RestockDrawer';
import { useApi } from '@/lib/ApiProvider';
import { formatCurrency, haptic } from '@/lib/format';
import { isLowStock, stockChipClass } from '@/lib/inventory';
import type { Category, Product, ProductUnit } from '@/lib/types';

type UnitForm = {
    id?: number;
    satuan: string;
    harga_jual: number;
    harga_beli: number;
    stok: number;
    min_stok: number;
    is_default: boolean;
};

type StockFilter = 'all' | 'low' | 'out';

const emptyUnit = (): UnitForm => ({
    satuan: 'pcs',
    harga_jual: 0,
    harga_beli: 0,
    stok: 0,
    min_stok: 5,
    is_default: false,
});

const emptyForm = () => ({
    category_id: '',
    nama: '',
    sku: '',
    barcode: '',
    deskripsi: '',
    is_active: true,
    units: [{ ...emptyUnit(), is_default: true }],
});

function unitSummary(product: Product): string {
    const units = product.units ?? [];
    if (!units.length) return '-';
    const minPrice = Math.min(...units.map((unit) => Number(unit.harga_jual)));
    return `dari ${formatCurrency(minPrice)}`;
}

function productMatchesFilter(product: Product, filter: StockFilter): boolean {
    const units = product.units ?? [];
    if (filter === 'all') return true;
    if (filter === 'out') return units.some((u) => u.stok === 0);
    return units.some((u) => isLowStock(u.stok, u.min_stok ?? 5));
}

export function ProductsPanel({ onInventoryChange }: { onInventoryChange?: () => void }) {
    const { api } = useApi();
    const [products, setProducts] = useState<Product[]>([]);
    const [categories, setCategories] = useState<Category[]>([]);
    const [loading, setLoading] = useState(true);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [editing, setEditing] = useState<Product | null>(null);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [form, setForm] = useState(emptyForm());
    const [search, setSearch] = useState('');
    const [stockFilter, setStockFilter] = useState<StockFilter>('all');
    const [restockOpen, setRestockOpen] = useState(false);
    const [restockTarget, setRestockTarget] = useState<RestockTarget | null>(null);

    const load = useCallback(async () => {
        try {
            const [p, c] = await Promise.all([
                api.list<Product[]>('products'),
                api.list<Category[]>('categories'),
            ]);
            setProducts(p);
            setCategories(c);
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memuat');
        } finally {
            setLoading(false);
        }
    }, [api]);

    useEffect(() => {
        load();
    }, [load]);

    const filteredProducts = useMemo(() => {
        const q = search.trim().toLowerCase();
        return products.filter((product) => {
            if (!productMatchesFilter(product, stockFilter)) return false;
            if (!q) return true;
            const haystack = [
                product.nama,
                product.sku,
                product.barcode,
                ...(product.units ?? []).map((u) => u.satuan),
            ]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();
            return haystack.includes(q);
        });
    }, [products, search, stockFilter]);

    function openCreate() {
        setEditing(null);
        setForm(emptyForm());
        setDrawerOpen(true);
    }

    function openEdit(product: Product) {
        setEditing(product);
        setForm({
            category_id: String(product.category_id),
            nama: product.nama,
            sku: product.sku || '',
            barcode: product.barcode || '',
            deskripsi: product.deskripsi || '',
            is_active: product.is_active,
            units: (product.units ?? []).map((unit: ProductUnit) => ({
                id: unit.id,
                satuan: unit.satuan,
                harga_jual: Number(unit.harga_jual),
                harga_beli: Number(unit.harga_beli),
                stok: unit.stok,
                min_stok: unit.min_stok ?? 5,
                is_default: unit.is_default,
            })),
        });
        setDrawerOpen(true);
    }

    function openRestock(product: Product, unit: ProductUnit) {
        setRestockTarget({ productName: product.nama, unit });
        setRestockOpen(true);
    }

    const categorySelectItems = useMemo(
        () => categories.map((c) => ({ value: String(c.id), label: c.nama })),
        [categories],
    );

    function updateUnit(index: number, patch: Partial<UnitForm>) {
        setForm((current) => {
            const units = current.units.map((unit, idx) => (idx === index ? { ...unit, ...patch } : unit));
            if (patch.is_default) {
                return {
                    ...current,
                    units: units.map((unit, idx) => ({ ...unit, is_default: idx === index })),
                };
            }

            return { ...current, units };
        });
    }

    function addUnit() {
        setForm((current) => ({
            ...current,
            units: [...current.units, emptyUnit()],
        }));
    }

    function removeUnit(index: number) {
        setForm((current) => {
            if (current.units.length <= 1) return current;
            const units = current.units.filter((_, idx) => idx !== index);
            if (!units.some((unit) => unit.is_default)) {
                units[0] = { ...units[0], is_default: true };
            }
            return { ...current, units };
        });
    }

    async function save() {
        try {
            const payload = {
                ...form,
                category_id: Number(form.category_id),
                units: form.units.map((unit) => ({
                    ...unit,
                    satuan: unit.satuan.trim().toLowerCase(),
                })),
            };
            if (editing) {
                await api.update('products', editing.id, payload);
            } else {
                await api.create('products', payload);
            }
            setDrawerOpen(false);
            haptic();
            toast.success('Disimpan');
            await load();
            onInventoryChange?.();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menyimpan');
        }
    }

    async function handleDelete() {
        if (!deleteId) return;
        try {
            await api.destroy('products', deleteId);
            setDeleteOpen(false);
            haptic();
            toast.success('Produk dihapus');
            await load();
            onInventoryChange?.();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menghapus');
        }
    }

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="relative flex-1">
                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        className="pl-9"
                        placeholder="Cari nama, SKU, barcode..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>
                <Button size="sm" onClick={openCreate}>
                    <Plus data-icon="inline-start" />
                    Tambah Produk
                </Button>
            </div>

            <Tabs value={stockFilter} onValueChange={(v) => setStockFilter(v as StockFilter)}>
                <TabsList variant="segment">
                    <TabsTrigger value="all">Semua</TabsTrigger>
                    <TabsTrigger value="low">Stok rendah</TabsTrigger>
                    <TabsTrigger value="out">Habis</TabsTrigger>
                </TabsList>
            </Tabs>

            {loading ? (
                <div className="flex flex-col gap-3">
                    <Skeleton className="h-16 w-full" />
                    <Skeleton className="h-16 w-full" />
                </div>
            ) : filteredProducts.length === 0 ? (
                <Empty>
                    <EmptyTitle>Belum ada produk</EmptyTitle>
                    <EmptyDescription>
                        {products.length ? 'Tidak ada produk yang cocok dengan filter.' : 'Tambah produk pertama'}
                    </EmptyDescription>
                </Empty>
            ) : (
                <GroupedList>
                    {filteredProducts.map((product, index) => (
                        <div key={product.id}>
                            {index > 0 && <GroupedListDivider />}
                            <IosListRow
                                title={product.nama}
                                subtitle={`${product.category?.nama || ''} • ${unitSummary(product)}`}
                                trailing={
                                    <div className="flex flex-col items-end gap-2">
                                        <div className="flex flex-wrap justify-end gap-1">
                                            {(product.units ?? []).map((unit) => (
                                                <Badge
                                                    key={unit.id}
                                                    className={stockChipClass(unit.stok, unit.min_stok ?? 5)}
                                                >
                                                    {unit.satuan}: {unit.stok}
                                                </Badge>
                                            ))}
                                        </div>
                                        <RowActions
                                            actions={[
                                                {
                                                    label: 'Restok',
                                                    onClick: () => {
                                                        const unit =
                                                            product.units?.find((u) => u.is_default) ??
                                                            product.units?.[0];
                                                        if (unit) openRestock(product, unit);
                                                    },
                                                },
                                                { label: 'Ubah', onClick: () => openEdit(product) },
                                                {
                                                    label: 'Hapus',
                                                    onClick: () => {
                                                        setDeleteId(product.id);
                                                        setDeleteOpen(true);
                                                    },
                                                    destructive: true,
                                                },
                                            ]}
                                        />
                                    </div>
                                }
                            />
                        </div>
                    ))}
                </GroupedList>
            )}

            <RestockDrawer
                open={restockOpen}
                onOpenChange={setRestockOpen}
                target={restockTarget}
                showBarcode={!restockTarget}
                showProductSearch={!restockTarget}
                onSuccess={() => {
                    load();
                    onInventoryChange?.();
                }}
            />

            <Drawer open={drawerOpen} onOpenChange={setDrawerOpen} showSwipeHandle>
                <DrawerContent className="max-h-[90vh]">
                    <DrawerHeader>
                        <DrawerTitle>{editing ? 'Ubah Produk' : 'Tambah Produk'}</DrawerTitle>
                    </DrawerHeader>
                    <div className="overflow-y-auto px-4 pb-4">
                        <FieldGroup>
                            <Field>
                                <FieldLabel>Kategori</FieldLabel>
                                <Select
                                    value={form.category_id}
                                    onValueChange={(v) => setForm({ ...form, category_id: v })}
                                    items={categorySelectItems}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Pilih kategori" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {categories.map((c) => (
                                                <SelectItem key={c.id} value={String(c.id)}>
                                                    {c.nama}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field>
                                <FieldLabel>Nama</FieldLabel>
                                <Input
                                    value={form.nama}
                                    onChange={(e) => setForm({ ...form, nama: e.target.value })}
                                    required
                                />
                            </Field>
                            <Field>
                                <FieldLabel>SKU</FieldLabel>
                                <Input value={form.sku} onChange={(e) => setForm({ ...form, sku: e.target.value })} />
                            </Field>
                            <Field>
                                <FieldLabel>Barcode</FieldLabel>
                                <Input
                                    value={form.barcode}
                                    onChange={(e) => setForm({ ...form, barcode: e.target.value })}
                                />
                            </Field>
                            <Field>
                                <FieldLabel>Deskripsi</FieldLabel>
                                <Textarea
                                    value={form.deskripsi}
                                    onChange={(e) => setForm({ ...form, deskripsi: e.target.value })}
                                />
                            </Field>

                            <div className="flex flex-col gap-3">
                                <div className="flex items-center justify-between">
                                    <p className="text-sm font-medium">Satuan & Harga</p>
                                    <Button type="button" size="sm" variant="outline" onClick={addUnit}>
                                        <Plus data-icon="inline-start" />
                                        Satuan
                                    </Button>
                                </div>
                                <RadioGroup
                                    value={String(Math.max(0, form.units.findIndex((unit) => unit.is_default)))}
                                    onValueChange={(value) => {
                                        const index = Number(value);
                                        setForm({
                                            ...form,
                                            units: form.units.map((unit, unitIndex) => ({
                                                ...unit,
                                                is_default: unitIndex === index,
                                            })),
                                        });
                                    }}
                                    className="flex flex-col gap-3"
                                >
                                    {form.units.map((unit, index) => (
                                        <Card key={index} size="sm" className="shadow-none">
                                            <CardHeader>
                                                <Field orientation="horizontal" className="col-start-1 row-start-1 w-auto">
                                                    <RadioGroupItem value={String(index)} id={`unit-default-${index}`} />
                                                    <FieldLabel htmlFor={`unit-default-${index}`}>Default</FieldLabel>
                                                </Field>
                                                {form.units.length > 1 ? (
                                                    <CardAction>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            className="text-destructive"
                                                            onClick={() => removeUnit(index)}
                                                        >
                                                            <Trash2 data-icon="inline-start" />
                                                            Hapus
                                                        </Button>
                                                    </CardAction>
                                                ) : null}
                                            </CardHeader>
                                            <CardContent className="flex flex-col gap-2">
                                                <div className="grid grid-cols-2 gap-2">
                                                    <Field>
                                                        <FieldLabel>Satuan</FieldLabel>
                                                        <Input
                                                            value={unit.satuan}
                                                            onChange={(e) => updateUnit(index, { satuan: e.target.value })}
                                                        />
                                                    </Field>
                                                    {editing && unit.id ? (
                                                        <Field>
                                                            <FieldLabel>Stok</FieldLabel>
                                                            <p className="flex h-9 items-center rounded-md border bg-muted/50 px-3 text-sm">
                                                                {unit.stok}
                                                            </p>
                                                        </Field>
                                                    ) : (
                                                        <Field>
                                                            <FieldLabel>Stok awal</FieldLabel>
                                                            <Input
                                                                type="number"
                                                                min={0}
                                                                value={unit.stok}
                                                                onChange={(e) =>
                                                                    updateUnit(index, { stok: Number(e.target.value) })
                                                                }
                                                            />
                                                        </Field>
                                                    )}
                                                </div>
                                                <div className="grid grid-cols-2 gap-2">
                                                    <Field>
                                                        <FieldLabel>Notifikasi Min. Stok</FieldLabel>
                                                        <Input
                                                            type="number"
                                                            min={0}
                                                            value={unit.min_stok}
                                                            onChange={(e) =>
                                                                updateUnit(index, { min_stok: Number(e.target.value) })
                                                            }
                                                        />
                                                    </Field>
                                                    <Field>
                                                        <FieldLabel>Harga Jual</FieldLabel>
                                                        <Input
                                                            type="number"
                                                            value={unit.harga_jual}
                                                            onChange={(e) =>
                                                                updateUnit(index, { harga_jual: Number(e.target.value) })
                                                            }
                                                        />
                                                    </Field>
                                                </div>
                                                <Field>
                                                    <FieldLabel>Harga Beli</FieldLabel>
                                                    <Input
                                                        type="number"
                                                        value={unit.harga_beli}
                                                        onChange={(e) =>
                                                            updateUnit(index, { harga_beli: Number(e.target.value) })
                                                        }
                                                    />
                                                </Field>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </RadioGroup>
                            </div>

                            <Field orientation="horizontal">
                                <FieldLabel>Aktif</FieldLabel>
                                <Switch
                                    checked={form.is_active}
                                    onCheckedChange={(checked) => setForm({ ...form, is_active: checked })}
                                />
                            </Field>
                        </FieldGroup>
                    </div>
                    <DrawerFooter>
                        <div className="flex gap-2">
                            <Button variant="outline" className="flex-1" onClick={() => setDrawerOpen(false)}>
                                Batal
                            </Button>
                            <Button className="flex-1" onClick={save}>
                                {editing ? 'Simpan' : 'Buat'}
                            </Button>
                        </div>
                    </DrawerFooter>
                </DrawerContent>
            </Drawer>

            <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus produk?</AlertDialogTitle>
                        <AlertDialogDescription>Tindakan ini tidak dapat dibatalkan.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDelete} className="bg-destructive text-destructive-foreground">
                            Hapus
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
