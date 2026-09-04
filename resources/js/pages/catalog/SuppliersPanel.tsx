import { forwardRef, useCallback, useEffect, useState, useImperativeHandle } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Empty, EmptyDescription, EmptyTitle } from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import { GroupedList, GroupedListDivider } from '@/components/ui/grouped-list';
import { RowActions } from '@/components/ui/row-actions';
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
import { ResponsiveFormPanel } from '@/components/ResponsiveFormPanel';
import { useApi } from '@/lib/ApiProvider';
import { haptic } from '@/lib/format';
import type { PaginatedResponse, Supplier } from '@/lib/types';

const emptyForm = () => ({ name: '', phone: '', address: '', notes: '' });

export interface SuppliersPanelHandle {
    openCreate: () => void;
}

export const SuppliersPanel = forwardRef<SuppliersPanelHandle>(function SuppliersPanel(_props, ref) {
    const { api } = useApi();
    const [suppliers, setSuppliers] = useState<Supplier[]>([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [editing, setEditing] = useState<Supplier | null>(null);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [form, setForm] = useState(emptyForm());
    const [saving, setSaving] = useState(false);

    const load = useCallback(async () => {
        try {
            const data = await api.list<PaginatedResponse<Supplier>>('suppliers', {
                page: String(page),
                per_page: '20',
            });
            setSuppliers(data.data);
            setLastPage(data.last_page);
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memuat pemasok');
        } finally {
            setLoading(false);
        }
    }, [api, page]);

    useEffect(() => {
        load();
    }, [load]);

    function openCreate() {
        haptic();
        setEditing(null);
        setForm(emptyForm());
        setDrawerOpen(true);
    }

    useImperativeHandle(ref, () => ({ openCreate }), []);

    function openEdit(supplier: Supplier) {
        setEditing(supplier);
        setForm({
            name: supplier.name,
            phone: supplier.phone || '',
            address: supplier.address || '',
            notes: supplier.notes || '',
        });
        setDrawerOpen(true);
    }

    function openDelete(supplier: Supplier) {
        setDeleteId(supplier.id);
        setDeleteOpen(true);
    }

    async function save() {
        const name = form.name.trim();
        if (!name) {
            toast.error('Nama pemasok wajib diisi');
            return;
        }
        if (saving) return;
        setSaving(true);
        const payload = {
            name,
            phone: form.phone.trim() || undefined,
            address: form.address.trim() || undefined,
            notes: form.notes.trim() || undefined,
        };
        try {
            if (editing) {
                await api.update<Supplier>('suppliers', editing.id, payload);
            } else {
                await api.create<Supplier>('suppliers', payload);
            }
            setDrawerOpen(false);
            haptic();
            toast.success('Pemasok disimpan');
            await load();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menyimpan pemasok');
        } finally {
            setSaving(false);
        }
    }

    async function handleDelete() {
        if (!deleteId) return;
        const id = deleteId;
        setDeleteOpen(false);
        haptic();
        try {
            await api.destroy('suppliers', id);
            toast.success('Pemasok dihapus');
            // Step back when the last row of the last page is removed.
            if (page > 1 && suppliers.length === 1) {
                setPage((p) => p - 1);
            } else {
                await load();
            }
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menghapus pemasok');
        }
    }

    return (
        <div className="flex flex-col gap-3">
            {loading ? (
                <div className="flex flex-col gap-2">
                    {Array.from({ length: 4 }).map((_, i) => (
                        <div key={i} className="rounded-lg border p-2.5">
                            <Skeleton className="h-6 w-1/2" />
                        </div>
                    ))}
                </div>
            ) : suppliers.length === 0 ? (
                <Empty>
                    <EmptyTitle>Belum ada pemasok</EmptyTitle>
                    <EmptyDescription>Tambah pemasok pertama</EmptyDescription>
                </Empty>
            ) : (
                <GroupedList>
                    {suppliers.map((supplier, index) => (
                        <div key={supplier.id}>
                            {index > 0 && <GroupedListDivider />}
                            <IosListRow
                                title={supplier.name}
                                subtitle={
                                    [supplier.phone, supplier.address]
                                        .filter(Boolean)
                                        .join(' · ') || undefined
                                }
                                trailing={
                                    <RowActions
                                        actions={[
                                            { label: 'Ubah', onClick: () => openEdit(supplier) },
                                            {
                                                label: 'Hapus',
                                                onClick: () => openDelete(supplier),
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

            {lastPage > 1 && (
                <div className="flex justify-center gap-2">
                    <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                        Sebelumnya
                    </Button>
                    <span className="flex items-center text-sm text-muted-foreground">
                        {page} / {lastPage}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={page >= lastPage}
                        onClick={() => setPage((p) => p + 1)}
                    >
                        Berikutnya
                    </Button>
                </div>
            )}

            <ResponsiveFormPanel
                open={drawerOpen}
                onOpenChange={setDrawerOpen}
                title={editing ? 'Ubah Pemasok' : 'Tambah Pemasok'}
                footer={
                    <div className="flex gap-2">
                        <Button variant="outline" className="flex-1" onClick={() => setDrawerOpen(false)}>
                            Batal
                        </Button>
                        <Button className="flex-1" disabled={saving} onClick={save}>
                            {saving ? 'Menyimpan...' : editing ? 'Simpan' : 'Buat'}
                        </Button>
                    </div>
                }
            >
                <FieldGroup>
                    <Field>
                        <FieldLabel>Nama</FieldLabel>
                        <Input
                            value={form.name}
                            onChange={(e) => setForm({ ...form, name: e.target.value })}
                            required
                        />
                    </Field>
                    <Field>
                        <FieldLabel>Telepon</FieldLabel>
                        <Input
                            type="tel"
                            inputMode="tel"
                            value={form.phone}
                            onChange={(e) => setForm({ ...form, phone: e.target.value })}
                        />
                    </Field>
                    <Field>
                        <FieldLabel>Alamat</FieldLabel>
                        <Textarea
                            value={form.address}
                            onChange={(e) => setForm({ ...form, address: e.target.value })}
                        />
                    </Field>
                    <Field>
                        <FieldLabel>Catatan</FieldLabel>
                        <Textarea
                            value={form.notes}
                            onChange={(e) => setForm({ ...form, notes: e.target.value })}
                        />
                    </Field>
                </FieldGroup>
            </ResponsiveFormPanel>

            <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus pemasok?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Pemasok tidak akan lagi muncul di penerimaan stok.
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
        </div>
    );
});
