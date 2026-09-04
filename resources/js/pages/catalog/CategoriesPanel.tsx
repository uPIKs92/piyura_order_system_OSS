import { forwardRef, useEffect, useMemo, useRef, useState, useImperativeHandle } from 'react';
import { toast } from 'sonner';
import { Check, Package, Plus, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Empty, EmptyDescription, EmptyTitle } from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
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
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { CategoryAccordion } from '@/components/catalog/CategoryAccordion';
import { ResponsiveFormPanel } from '@/components/ResponsiveFormPanel';
import { useApi } from '@/lib/ApiProvider';
import { useListAll } from '@/lib/listAll';
import { usePullToRefresh, PullToRefreshIndicator } from '@/hooks/use-pull-to-refresh';
import { haptic, formatCurrency } from '@/lib/format';
import { UNCATEGORIZED_LABEL } from '@/lib/products';
import type { Category, Product } from '@/lib/types';

const emptyForm = () => ({ nama: '', deskripsi: '', is_active: true });

function productMatchesSearch(product: Product, q: string): boolean {
    if (!q) return true;
    const haystack = [product.nama, product.sku, product.barcode]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();
    return haystack.includes(q);
}

function productPriceLabel(product: Product): string {
    const units = product.units ?? [];
    if (!units.length) return '-';
    const min = Math.min(...units.map((u) => Number(u.harga_jual)));
    return `dari ${formatCurrency(min)}`;
}

export interface CategoriesPanelHandle {
    openCreate: () => void;
}

export const CategoriesPanel = forwardRef<CategoriesPanelHandle>(function CategoriesPanel(_props, ref) {
    const { api } = useApi();
    // Accordion view groups every product under its category, so both lists
    // need the full dataset — fetched via bounded page walks (see listAll).
    const {
        data: categories,
        loading,
        refreshing,
        refetch: refetchCategories,
        setData: setCategories,
    } = useListAll<Category>('categories', api);
    const {
        data: products,
        refetch: refetchProducts,
        setData: setProducts,
    } = useListAll<Product>('products', api);

    const [drawerOpen, setDrawerOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [editing, setEditing] = useState<Category | null>(null);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [form, setForm] = useState(emptyForm());

    // "Tambah Produk" picker: which category receives the staged products.
    const [pickerOpen, setPickerOpen] = useState(false);
    const [pickerCategory, setPickerCategory] = useState<Category | null>(null);
    const [pickerSearch, setPickerSearch] = useState('');
    // Queue: products selected for addition (applied on confirm).
    const [stagedAddIds, setStagedAddIds] = useState<number[]>([]);

    // Queue: products selected for removal from their category (applied on confirm).
    const [stagedRemovalIds, setStagedRemovalIds] = useState<number[]>([]);

    // "Tanpa kategori" row tap: which product gets a category, and the chosen one.
    const [assignTarget, setAssignTarget] = useState<Product | null>(null);
    const [assignChoiceId, setAssignChoiceId] = useState<number | null>(null);

    const [applying, setApplying] = useState(false);

    const { pulling, refreshing: pullRefreshing, bind } = usePullToRefresh(
        async () => {
            await Promise.all([refetchCategories(), refetchProducts()]);
        },
    );

    // Group products by category for the accordion sections.
    const productsByCategory = useMemo(() => {
        const map = new Map<number, Product[]>();
        for (const product of products) {
            if (product.category_id == null) continue;
            const list = map.get(product.category_id) ?? [];
            list.push(product);
            map.set(product.category_id, list);
        }
        return map;
    }, [products]);

    // Null relation covers both category_id = null and dangling ids of
    // soft-deleted categories.
    const uncategorizedProducts = useMemo(
        () => products.filter((product) => !product.category),
        [products],
    );

    // Only products without a category can be added here; products already
    // in a category (any category) are managed from their own section.
    const availableProducts = useMemo(() => {
        if (!pickerCategory) return [];
        const q = pickerSearch.trim().toLowerCase();
        return products
            .filter((product) => !product.category)
            .filter((product) => productMatchesSearch(product, q));
    }, [products, pickerCategory, pickerSearch]);

    function openCreate() {
        haptic();
        setEditing(null);
        setForm(emptyForm());
        setDrawerOpen(true);
    }

    useImperativeHandle(ref, () => ({ openCreate }), []);

    function openEdit(cat: Category) {
        setEditing(cat);
        setForm({ nama: cat.nama, deskripsi: cat.deskripsi || '', is_active: cat.is_active });
        setDrawerOpen(true);
    }

    function openDelete(cat: Category) {
        setDeleteId(cat.id);
        setDeleteOpen(true);
    }

    function openAddProduct(cat: Category) {
        setPickerCategory(cat);
        setPickerSearch('');
        setStagedAddIds([]);
        setPickerOpen(true);
    }

    function closePicker() {
        setPickerOpen(false);
        setStagedAddIds([]);
    }

    function openAssign(product: Product) {
        setAssignTarget(product);
        setAssignChoiceId(null);
    }

    function toggleStagedAdd(id: number) {
        haptic();
        setStagedAddIds((prev) =>
            prev.includes(id) ? prev.filter((pid) => pid !== id) : [...prev, id],
        );
    }

    function toggleStagedRemoval(id: number) {
        haptic();
        setStagedRemovalIds((prev) =>
            prev.includes(id) ? prev.filter((pid) => pid !== id) : [...prev, id],
        );
    }

    /** Patch a product's category in local state once the server confirms. */
    function patchProduct(productId: number, patch: Partial<Product>) {
        setProducts((prev) => prev.map((p) => (p.id === productId ? { ...p, ...patch } : p)));
    }

    /**
     * Coalesce post-mutation refetches: batch applies produce a single
     * trailing GET. Purely reconciliation — never on the interaction path.
     */
    const refetchTimer = useRef<number | null>(null);
    useEffect(() => {
        return () => {
            if (refetchTimer.current !== null) window.clearTimeout(refetchTimer.current);
        };
    }, []);
    function scheduleProductsRefetch(delayMs = 500) {
        if (refetchTimer.current !== null) window.clearTimeout(refetchTimer.current);
        refetchTimer.current = window.setTimeout(() => {
            refetchTimer.current = null;
            void refetchProducts();
        }, delayMs);
    }

    /** Apply the picker queue: assign all staged products to the category. */
    async function applyAdds() {
        if (!pickerCategory || stagedAddIds.length === 0 || applying) return;
        const category = pickerCategory;
        const ids = stagedAddIds;
        setApplying(true);
        const results = await Promise.allSettled(
            ids.map((id) => api.update('products', id, { category_id: category.id })),
        );
        const okIds = ids.filter((_, i) => results[i].status === 'fulfilled');
        const failed = ids.length - okIds.length;
        for (const id of okIds) {
            patchProduct(id, { category_id: category.id, category });
        }
        if (failed > 0) {
            toast.error(`${failed} produk gagal ditambahkan`);
        } else {
            toast.success(`${okIds.length} produk ditambahkan ke ${category.nama}`);
        }
        setStagedAddIds([]);
        setApplying(false);
        if (okIds.length === ids.length) {
            setPickerOpen(false);
        }
        scheduleProductsRefetch();
    }

    /** Apply the removal queue: move all staged products to Tanpa kategori. */
    async function applyRemovals() {
        if (stagedRemovalIds.length === 0 || applying) return;
        const ids = stagedRemovalIds;
        setApplying(true);
        const results = await Promise.allSettled(
            ids.map((id) => api.update('products', id, { category_id: null })),
        );
        const okIds = ids.filter((_, i) => results[i].status === 'fulfilled');
        const failed = ids.length - okIds.length;
        for (const id of okIds) {
            patchProduct(id, { category_id: null, category: undefined });
        }
        if (failed > 0) {
            toast.error(`${failed} produk gagal dikeluarkan`);
        } else {
            toast.success(`${okIds.length} produk dikeluarkan dari kategori`);
        }
        setStagedRemovalIds([]);
        setApplying(false);
        scheduleProductsRefetch();
    }

    /** Apply the assign choice: move the product into the chosen category. */
    async function applyAssign() {
        if (!assignTarget || assignChoiceId == null || applying) return;
        const target = assignTarget;
        const cat = categories.find((c) => c.id === assignChoiceId);
        if (!cat) return;
        setApplying(true);
        try {
            await api.update('products', target.id, { category_id: cat.id });
            patchProduct(target.id, { category_id: cat.id, category: cat });
            toast.success(`Produk ditambahkan ke ${cat.nama}`);
            setAssignTarget(null);
            setAssignChoiceId(null);
            scheduleProductsRefetch();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memilih kategori');
        } finally {
            setApplying(false);
        }
    }

    async function save() {
        try {
            if (editing) {
                await api.update('categories', editing.id, form);
                setCategories((prev) =>
                    prev.map((c) => (c.id === editing.id ? { ...c, ...form } : c)),
                );
            } else {
                await api.create('categories', form);
                await refetchCategories();
            }
            setDrawerOpen(false);
            haptic();
            toast.success('Disimpan');
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menyimpan');
            await refetchCategories();
        }
    }

    async function handleDelete() {
        if (!deleteId) return;
        const catSnapshot = categories;
        const prodSnapshot = products;
        setDeleteOpen(false);
        haptic();
        // Optimistic: category disappears, its products move to Tanpa kategori.
        setCategories((prev) => prev.filter((c) => c.id !== deleteId));
        setProducts((prev) =>
            prev.map((p) =>
                p.category_id === deleteId ? { ...p, category_id: null, category: undefined } : p,
            ),
        );
        try {
            await api.destroy('categories', deleteId);
            toast.success('Kategori dihapus');
            await Promise.all([refetchCategories(), refetchProducts()]);
        } catch (err) {
            setCategories(catSnapshot);
            setProducts(prodSnapshot);
            toast.error(err instanceof Error ? err.message : 'Gagal menghapus');
        }
    }


    return (
        <div className="flex flex-col gap-3" {...bind}>
            <PullToRefreshIndicator pulling={pulling} refreshing={refreshing || pullRefreshing} />

            {loading ? (
                <div className="flex flex-col gap-2">
                    {Array.from({ length: 4 }).map((_, i) => (
                        <div key={i} className="rounded-lg border p-2.5">
                            <Skeleton className="h-6 w-1/2" />
                        </div>
                    ))}
                </div>
            ) : categories.length === 0 && uncategorizedProducts.length === 0 ? (
                <Empty>
                    <EmptyTitle>Belum ada kategori</EmptyTitle>
                    <EmptyDescription>Tambah kategori pertama</EmptyDescription>
                </Empty>
            ) : (
                <div className="flex flex-col gap-2">
                    {categories.map((cat) => (
                        <CategoryAccordion
                            key={cat.id}
                            title={cat.nama}
                            products={productsByCategory.get(cat.id) ?? []}
                            onEditCategory={() => openEdit(cat)}
                            onDeleteCategory={() => openDelete(cat)}
                            onAddProduct={() => openAddProduct(cat)}
                            onStageRemove={(product) => toggleStagedRemoval(product.id)}
                            stagedProductIds={stagedRemovalIds}
                        />
                    ))}
                    {uncategorizedProducts.length > 0 && (
                        <CategoryAccordion
                            title={UNCATEGORIZED_LABEL}
                            products={uncategorizedProducts}
                            onProductPress={openAssign}
                        />
                    )}
                </div>
            )}

            {/* Staged-removal confirm bar */}
            {stagedRemovalIds.length > 0 && (
                <div className="sticky bottom-0 z-20 flex items-center gap-2 rounded-lg border bg-card p-2 shadow-lg">
                    <p className="min-w-0 flex-1 truncate text-xs text-muted-foreground">
                        {stagedRemovalIds.length} produk akan dipindahkan ke {UNCATEGORIZED_LABEL}
                    </p>
                    <Button
                        size="sm"
                        variant="outline"
                        disabled={applying}
                        onClick={() => setStagedRemovalIds([])}
                    >
                        Batal
                    </Button>
                    <Button
                        size="sm"
                        variant="destructive"
                        disabled={applying}
                        onClick={applyRemovals}
                    >
                        {applying ? 'Memproses...' : `Keluarkan (${stagedRemovalIds.length})`}
                    </Button>
                </div>
            )}

            <ResponsiveFormPanel
                open={drawerOpen}
                onOpenChange={setDrawerOpen}
                title={editing ? 'Ubah Kategori' : 'Tambah Kategori'}
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
                        <FieldLabel>Deskripsi</FieldLabel>
                        <Textarea
                            value={form.deskripsi}
                            onChange={(e) => setForm({ ...form, deskripsi: e.target.value })}
                        />
                    </Field>
                    <Field orientation="horizontal">
                        <FieldLabel>Aktif</FieldLabel>
                        <Switch
                            checked={form.is_active}
                            onCheckedChange={(checked) => setForm({ ...form, is_active: checked })}
                        />
                    </Field>
                </FieldGroup>
            </ResponsiveFormPanel>

            <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus kategori?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Produk dalam kategori ini akan dipindahkan ke Tanpa kategori.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDelete} className="bg-destructive text-destructive-foreground">
                            Hapus
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Stage products into a category, apply on confirm */}
            <ResponsiveFormPanel
                open={pickerOpen}
                onOpenChange={(open) => (open ? setPickerOpen(true) : closePicker())}
                title={`Tambah Produk ${pickerCategory ? `— ${pickerCategory.nama}` : ''}`}
                footer={
                    <div className="flex gap-2">
                        <Button variant="outline" className="flex-1" disabled={applying} onClick={closePicker}>
                            Batal
                        </Button>
                        <Button
                            className="flex-1"
                            disabled={stagedAddIds.length === 0 || applying}
                            onClick={applyAdds}
                        >
                            {applying
                                ? 'Menambahkan...'
                                : stagedAddIds.length > 0
                                  ? `Tambah (${stagedAddIds.length})`
                                  : 'Tambah'}
                        </Button>
                    </div>
                }
            >
                <div className="pb-2">
                    <div className="relative">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            className="pl-9"
                            placeholder="Cari nama, SKU, barcode..."
                            value={pickerSearch}
                            onChange={(e) => setPickerSearch(e.target.value)}
                        />
                    </div>
                    <p className="mt-1.5 px-1 text-[11px] text-muted-foreground">
                        Hanya produk Tanpa kategori yang ditampilkan
                    </p>
                </div>
                {availableProducts.length === 0 ? (
                    <p className="py-8 text-center text-sm text-muted-foreground">
                        {pickerSearch.trim()
                            ? 'Tidak ada produk yang cocok'
                            : 'Tidak ada produk di Tanpa kategori'}
                    </p>
                ) : (
                    <div className="flex flex-col gap-1">
                        {availableProducts.map((product) => {
                            const staged = stagedAddIds.includes(product.id);
                            return (
                                <div
                                    key={product.id}
                                    className={
                                        'flex items-center gap-2 rounded-md p-1.5 active:bg-muted/60'
                                        + (staged ? ' bg-muted' : '')
                                    }
                                    role="button"
                                    tabIndex={0}
                                    aria-selected={staged}
                                    aria-label={staged
                                        ? `Batalkan ${product.nama}`
                                        : `Pilih ${product.nama}`}
                                    onClick={() => toggleStagedAdd(product.id)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter' || e.key === ' ') {
                                            e.preventDefault();
                                            toggleStagedAdd(product.id);
                                        }
                                    }}
                                >
                                    {product.photo_url ? (
                                        <img
                                            src={product.photo_url}
                                            alt={product.nama}
                                            loading="lazy"
                                            className="size-9 shrink-0 rounded-md object-cover"
                                        />
                                    ) : (
                                        <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-muted">
                                            <Package className="size-4 text-muted-foreground/40" />
                                        </div>
                                    )}
                                    <div className="flex min-w-0 flex-1 flex-col">
                                        <span className="truncate text-xs font-medium">{product.nama}</span>
                                        <span className="truncate text-[11px] text-muted-foreground">
                                            {productPriceLabel(product)}
                                        </span>
                                    </div>
                                    <span
                                        className={
                                            'flex size-6 shrink-0 items-center justify-center rounded-full'
                                            + (staged
                                                ? ' bg-primary text-primary-foreground'
                                                : ' border text-muted-foreground')
                                        }
                                        aria-hidden="true"
                                    >
                                        {staged ? <Check className="size-3.5" /> : <Plus className="size-3.5" />}
                                    </span>
                                </div>
                            );
                        })}
                    </div>
                )}
            </ResponsiveFormPanel>

            {/* Choose a category for an uncategorized product, apply on confirm */}
            <ResponsiveFormPanel
                open={assignTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setAssignTarget(null);
                        setAssignChoiceId(null);
                    }
                }}
                title={`Pilih Kategori ${assignTarget ? `— ${assignTarget.nama}` : ''}`}
                footer={
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            className="flex-1"
                            disabled={applying}
                            onClick={() => {
                                setAssignTarget(null);
                                setAssignChoiceId(null);
                            }}
                        >
                            Batal
                        </Button>
                        <Button
                            className="flex-1"
                            disabled={assignChoiceId == null || applying}
                            onClick={applyAssign}
                        >
                            {applying ? 'Memproses...' : 'Pindahkan'}
                        </Button>
                    </div>
                }
            >
                {categories.length === 0 ? (
                    <p className="py-8 text-center text-sm text-muted-foreground">
                        Belum ada kategori
                    </p>
                ) : (
                    <div className="flex flex-col gap-1">
                        {categories.map((cat) => {
                            const chosen = assignChoiceId === cat.id;
                            return (
                                <button
                                    key={cat.id}
                                    type="button"
                                    className={
                                        'flex items-center justify-between gap-2 rounded-md p-2.5 text-left text-sm active:bg-muted/60'
                                        + (chosen ? ' bg-muted' : '')
                                    }
                                    aria-selected={chosen}
                                    onClick={() => {
                                        haptic();
                                        setAssignChoiceId(cat.id);
                                    }}
                                >
                                    <span className="truncate font-medium">{cat.nama}</span>
                                    {chosen ? (
                                        <Check className="size-4 shrink-0 text-primary" />
                                    ) : (
                                        <span className="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-muted px-1.5 text-[11px] font-medium text-muted-foreground tabular-nums">
                                            {productsByCategory.get(cat.id)?.length ?? 0}
                                        </span>
                                    )}
                                </button>
                            );
                        })}
                    </div>
                )}
            </ResponsiveFormPanel>
        </div>
    );
});
