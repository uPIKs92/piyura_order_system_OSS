import { forwardRef, useCallback, useEffect, useMemo, useRef, useState, useImperativeHandle } from 'react';
import { toast } from 'sonner';
import { ImagePlus, LayoutGrid, List, Plus, Search, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Empty, EmptyDescription, EmptyTitle } from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import { ResponsiveFormPanel } from '@/components/ResponsiveFormPanel';
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
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { CategoryFilterButton } from '@/components/catalog/CategoryFilterButton';
import { ProductTileGrid } from '@/components/catalog/ProductTileGrid';
import { ProductListRow } from '@/components/catalog/ProductListRow';
import { RestockDrawer, type RestockTarget } from '@/components/inventory/RestockDrawer';
import { useApi } from '@/lib/ApiProvider';
import { listAll, useListAll } from '@/lib/listAll';
import { usePullToRefresh, PullToRefreshIndicator } from '@/hooks/use-pull-to-refresh';
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

export interface ProductsPanelHandle {
    openCreate: () => void;
}

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

interface ProductsPanelProps {
    onInventoryChange?: () => void;
}

export const ProductsPanel = forwardRef<ProductsPanelHandle, ProductsPanelProps>(
    function ProductsPanel({ onInventoryChange }, ref) {
    const { api, user } = useApi();
    const isOwner = user?.role === 'owner';
    // Full catalog via bounded page walks (resources now paginate; see listAll).
    const {
        data: products,
        loading,
        refreshing,
        refetch,
    } = useListAll<Product>('products', api);
    const [categories, setCategories] = useState<Category[]>([]);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [editing, setEditing] = useState<Product | null>(null);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [form, setForm] = useState(emptyForm());
    const [search, setSearch] = useState('');
    const [stockFilter, setStockFilter] = useState<StockFilter>('all');
    const [categoryFilter, setCategoryFilter] = useState('');
    const [restockOpen, setRestockOpen] = useState(false);
    const [restockTarget, setRestockTarget] = useState<RestockTarget | null>(null);
    const [view, setView] = useState<'grid' | 'list'>(() => {
        const saved = localStorage.getItem('catalog:view');
        if (saved === 'grid' || saved === 'list') return saved;
        return typeof window !== 'undefined' && window.matchMedia('(max-width: 767px)').matches
            ? 'list'
            : 'grid';
    });
    const photoInputRef = useRef<HTMLInputElement>(null);
    const [photoUploading, setPhotoUploading] = useState(false);
    const [pendingPhoto, setPendingPhoto] = useState<File | null>(null);
    const pendingPhotoUrl = useMemo(
        () => (pendingPhoto ? URL.createObjectURL(pendingPhoto) : null),
        [pendingPhoto],
    );
    useEffect(() => {
        return () => {
            if (pendingPhotoUrl) URL.revokeObjectURL(pendingPhotoUrl);
        };
    }, [pendingPhotoUrl]);

    const { pulling, refreshing: pullRefreshing, bind } = usePullToRefresh(
        async () => {
            await Promise.all([refetch(), loadCategories()]);
        },
    );

    const loadCategories = useCallback(async () => {
        try {
            setCategories(await listAll<Category>(api, 'categories'));
        } catch {
            // products panel still usable without category chips
        }
    }, [api]);

    useEffect(() => {
        void loadCategories();
    }, [loadCategories]);

    useEffect(() => {
        localStorage.setItem('catalog:view', view);
    }, [view]);


    const filteredProducts = useMemo(() => {
        const q = search.trim().toLowerCase();
        const catId = categoryFilter ? Number(categoryFilter) : null;
        return products.filter((product) => {
            if (!productMatchesFilter(product, stockFilter)) return false;
            if (catId !== null && product.category_id !== catId) return false;
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
    }, [products, search, stockFilter, categoryFilter]);

    function openCreate() {
        haptic();
        setEditing(null);
        setPendingPhoto(null);
        setForm({
            ...emptyForm(),
            category_id: categoryFilter || '',
        });
        setDrawerOpen(true);
    }

    useImperativeHandle(ref, () => ({ openCreate }), []);

    function openEdit(product: Product) {
        setEditing(product);
        setPendingPhoto(null);
        setForm({
            category_id: product.category_id == null ? '' : String(product.category_id),
            nama: product.nama,
            sku: product.sku || '',
            barcode: product.barcode || '',
            deskripsi: product.deskripsi || '',
            is_active: product.is_active,
            units: (product.units ?? []).map((unit: ProductUnit) => ({
                id: unit.id,
                satuan: unit.satuan,
                harga_jual: Number(unit.harga_jual),
                harga_beli: Number(unit.harga_beli ?? 0),
                stok: unit.stok,
                min_stok: unit.min_stok ?? 5,
                is_default: unit.is_default,
            })),
        });
        setDrawerOpen(true);
    }

    function openDelete(product: Product) {
        setDeleteId(product.id);
        setDrawerOpen(false);
        setDeleteOpen(true);
    }

    function openRestock(product: Product, unit: ProductUnit) {
        setRestockTarget({ productName: product.nama, unit });
        setRestockOpen(true);
    }

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
                category_id: form.category_id ? Number(form.category_id) : null,
                units: form.units.map((unit) => ({
                    ...unit,
                    satuan: unit.satuan.trim().toLowerCase(),
                })),
            };
            if (editing) {
                await api.update('products', editing.id, payload);
            } else {
                const created = await api.create<Product>('products', payload);
                if (pendingPhoto && created?.id) {
                    setPhotoUploading(true);
                    const formData = new FormData();
                    formData.append('photo', pendingPhoto);
                    await api.upload<Product>(`/products/${created.id}/photo`, formData);
                }
            }
            setPendingPhoto(null);
            setDrawerOpen(false);
            haptic();
            toast.success('Disimpan');
            await Promise.all([refetch(), loadCategories()]);
            onInventoryChange?.();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menyimpan');
        } finally {
            setPhotoUploading(false);
        }
    }

    async function handleDelete() {
        if (!deleteId) return;
        try {
            await api.destroy('products', deleteId);
            setDeleteOpen(false);
            haptic();
            toast.success('Produk dihapus');
            await refetch();
            onInventoryChange?.();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menghapus');
        }
    }

    async function handlePhotoUpload(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;

        if (!editing) {
            setPendingPhoto(file);
            if (photoInputRef.current) photoInputRef.current.value = '';
            return;
        }

        const formData = new FormData();
        formData.append('photo', file);

        try {
            setPhotoUploading(true);
            const updated = await api.upload<Product>(`/products/${editing.id}/photo`, formData);
            setEditing(updated);
            haptic();
            toast.success('Foto diperbarui');
            await refetch();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal mengunggah foto');
        } finally {
            setPhotoUploading(false);
            if (photoInputRef.current) photoInputRef.current.value = '';
        }
    }

    async function handlePhotoDelete() {
        if (!editing) {
            setPendingPhoto(null);
            return;
        }
        try {
            const updated = await api.request<Product>(`/products/${editing.id}/photo`, {
                method: 'DELETE',
            });
            setEditing(updated);
            haptic();
            toast.success('Foto dihapus');
            await refetch();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menghapus foto');
        }
    }

    return (
        <div className="flex flex-col gap-3">
            <div className="flex items-center gap-2">
                <div className="relative flex-1">
                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        className="pl-9"
                        placeholder="Cari nama, SKU, barcode..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>
                <CategoryFilterButton
                    categories={categories}
                    value={categoryFilter}
                    onChange={setCategoryFilter}
                />
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label={view === 'grid' ? 'Tampilan daftar' : 'Tampilan kotak'}
                    onClick={() => {
                        haptic();
                        setView(view === 'grid' ? 'list' : 'grid');
                    }}
                >
                    {view === 'grid' ? <LayoutGrid /> : <List />}
                </Button>
            </div>

            <Tabs value={stockFilter} onValueChange={(v) => setStockFilter(v as StockFilter)}>
                <TabsList variant="segment">
                    <TabsTrigger value="all">Semua</TabsTrigger>
                    <TabsTrigger value="low">Stok rendah</TabsTrigger>
                    <TabsTrigger value="out">Habis</TabsTrigger>
                </TabsList>
            </Tabs>

            <div {...bind}>
                <PullToRefreshIndicator pulling={pulling} refreshing={refreshing || pullRefreshing} />

                {view === 'grid' ? (
                    <ProductTileGrid
                        products={filteredProducts}
                        loading={loading}
                        onEdit={openEdit}
                        onRestock={openRestock}
                    />
                ) : loading ? (
                    <div className="flex flex-col gap-2">
                        <Skeleton className="h-[76px] w-full rounded-lg" />
                        <Skeleton className="h-[76px] w-full rounded-lg" />
                        <Skeleton className="h-[76px] w-full rounded-lg" />
                    </div>
                ) : filteredProducts.length === 0 ? (
                    <Empty>
                        <EmptyTitle>Belum ada produk</EmptyTitle>
                        <EmptyDescription>
                            {products.length ? 'Tidak ada produk yang cocok dengan filter.' : 'Tambah produk pertama'}
                        </EmptyDescription>
                    </Empty>
                ) : (
                    <div className="flex flex-col gap-2">
                        {filteredProducts.map((product) => (
                            <ProductListRow
                                key={product.id}
                                product={product}
                                onEdit={openEdit}
                                onRestock={openRestock}
                            />
                        ))}
                    </div>
                )}
            </div>

            <RestockDrawer
                open={restockOpen}
                onOpenChange={setRestockOpen}
                target={restockTarget}
                showBarcode={!restockTarget}
                showProductSearch={!restockTarget}
                onSuccess={() => {
                    void refetch();
                    onInventoryChange?.();
                }}
            />

            <ResponsiveFormPanel
                open={drawerOpen}
                onOpenChange={setDrawerOpen}
                title={editing ? 'Ubah Produk' : 'Tambah Produk'}
                desktopWidthClass="data-[side=right]:sm:max-w-lg"
                footer={
                    <div className="flex gap-2">
                        <Button variant="outline" className="flex-1" onClick={() => setDrawerOpen(false)}>
                            Batal
                        </Button>
                        <Button className="flex-1" onClick={save}>
                            {editing ? 'Simpan' : 'Buat'}
                        </Button>
                    </div>
                }
            >
                <FieldGroup>
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
                        <FieldLabel>Kategori</FieldLabel>
                        <Select
                            value={form.category_id}
                            onValueChange={(value) => setForm({ ...form, category_id: value })}
                            items={[
                                { value: '', label: 'Tanpa kategori' },
                                ...categories.map((cat) => ({ value: String(cat.id), label: cat.nama })),
                            ]}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Tanpa kategori" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="">Tanpa kategori</SelectItem>
                                    {categories.map((cat) => (
                                        <SelectItem key={cat.id} value={String(cat.id)}>
                                            {cat.nama}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field>
                        <FieldLabel>Deskripsi</FieldLabel>
                        <Textarea
                            value={form.deskripsi}
                            onChange={(e) => setForm({ ...form, deskripsi: e.target.value })}
                        />
                    </Field>

                    <Field>
                        <FieldLabel>Foto Produk</FieldLabel>
                        <input
                            ref={photoInputRef}
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            className="hidden"
                            onChange={handlePhotoUpload}
                        />
                        <div className="flex items-center gap-3">
                            {editing?.photo_url ?? pendingPhotoUrl ? (
                                <img
                                    src={editing?.photo_url ?? pendingPhotoUrl ?? ''}
                                    alt={editing?.nama ?? form.nama}
                                    className="size-16 rounded-lg border object-cover"
                                />
                            ) : (
                                <div className="flex size-16 items-center justify-center rounded-lg border bg-muted text-muted-foreground">
                                    <ImagePlus className="size-5" />
                                </div>
                            )}
                            <div className="flex flex-col gap-1.5">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={photoUploading}
                                    onClick={() => photoInputRef.current?.click()}
                                >
                                    <ImagePlus data-icon="inline-start" />
                                    {photoUploading
                                        ? 'Mengunggah...'
                                        : editing?.photo_url ?? pendingPhotoUrl
                                          ? 'Ganti Foto'
                                          : 'Unggah Foto'}
                                </Button>
                                {editing?.photo_url ?? pendingPhotoUrl ? (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        className="text-destructive"
                                        onClick={handlePhotoDelete}
                                    >
                                        <Trash2 data-icon="inline-start" />
                                        Hapus Foto
                                    </Button>
                                ) : null}
                            </div>
                        </div>
                    </Field>

                    <div className="flex flex-col gap-3">
                        <div className="flex flex-col gap-1">
                            <div className="flex items-center justify-between gap-2">
                                <p className="text-sm font-medium">Satuan & Harga</p>
                                <Button type="button" size="sm" onClick={addUnit}>
                                    <Plus data-icon="inline-start" />
                                    Tambah Satuan
                                </Button>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Satu produk bisa punya beberapa satuan, mis. pcs dan lusinan.
                            </p>
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
                                        {isOwner ? (
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
                                        ) : null}
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

                    {editing && isOwner ? (
                        <Button
                            type="button"
                            variant="ghost"
                            className="w-full text-destructive"
                            onClick={() => openDelete(editing)}
                        >
                            <Trash2 data-icon="inline-start" />
                            Hapus Produk
                        </Button>
                    ) : null}
                </FieldGroup>
            </ResponsiveFormPanel>

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
    },
);
