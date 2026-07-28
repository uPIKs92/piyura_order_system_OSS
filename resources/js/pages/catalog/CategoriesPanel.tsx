import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { IosListRow } from '@/components/ios/IosListRow';
import { useApi } from '@/lib/ApiProvider';
import { haptic } from '@/lib/format';
import type { Category } from '@/lib/types';

const emptyForm = () => ({ nama: '', deskripsi: '', is_active: true });

export function CategoriesPanel() {
    const { api } = useApi();
    const [categories, setCategories] = useState<Category[]>([]);
    const [loading, setLoading] = useState(true);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [editing, setEditing] = useState<Category | null>(null);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [form, setForm] = useState(emptyForm());

    const load = useCallback(async () => {
        try {
            setCategories(await api.list<Category[]>('categories'));
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memuat');
        } finally {
            setLoading(false);
        }
    }, [api]);

    useEffect(() => {
        load();
    }, [load]);

    function openCreate() {
        setEditing(null);
        setForm(emptyForm());
        setDrawerOpen(true);
    }

    function openEdit(cat: Category) {
        setEditing(cat);
        setForm({ nama: cat.nama, deskripsi: cat.deskripsi || '', is_active: cat.is_active });
        setDrawerOpen(true);
    }

    async function save() {
        try {
            if (editing) {
                await api.update('categories', editing.id, form);
            } else {
                await api.create('categories', form);
            }
            setDrawerOpen(false);
            haptic();
            toast.success('Disimpan');
            await load();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menyimpan');
        }
    }

    async function handleDelete() {
        if (!deleteId) return;
        try {
            await api.destroy('categories', deleteId);
            setDeleteOpen(false);
            haptic();
            toast.success('Kategori dihapus');
            await load();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menghapus');
        }
    }

    return (
        <div className="flex flex-col gap-3">
            <div className="flex justify-end">
                <Button size="sm" onClick={openCreate}>
                    <Plus data-icon="inline-start" />
                    Tambah Kategori
                </Button>
            </div>

            {loading ? (
                <div className="flex flex-col gap-3">
                    <Skeleton className="h-16 w-full" />
                </div>
            ) : categories.length === 0 ? (
                <Empty>
                    <EmptyTitle>Belum ada kategori</EmptyTitle>
                    <EmptyDescription>Tambah kategori pertama</EmptyDescription>
                </Empty>
            ) : (
                <GroupedList>
                    {categories.map((cat, index) => (
                        <div key={cat.id}>
                            {index > 0 && <GroupedListDivider />}
                            <IosListRow
                                title={cat.nama}
                                subtitle={`${cat.products_count ?? 0} produk`}
                                trailing={
                                    <RowActions
                                        actions={[
                                            { label: 'Ubah', onClick: () => openEdit(cat) },
                                            {
                                                label: 'Hapus',
                                                onClick: () => {
                                                    setDeleteId(cat.id);
                                                    setDeleteOpen(true);
                                                },
                                                destructive: true,
                                            },
                                        ]}
                                    />
                                }
                            />
                        </div>
                    ))}
                </GroupedList>
            )}

            <Drawer open={drawerOpen} onOpenChange={setDrawerOpen} showSwipeHandle>
                <DrawerContent>
                    <DrawerHeader>
                        <DrawerTitle>{editing ? 'Ubah Kategori' : 'Tambah Kategori'}</DrawerTitle>
                    </DrawerHeader>
                    <div className="px-4 pb-4">
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
                        <AlertDialogTitle>Hapus kategori?</AlertDialogTitle>
                        <AlertDialogDescription>Produk dalam kategori ini mungkin terpengaruh.</AlertDialogDescription>
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
