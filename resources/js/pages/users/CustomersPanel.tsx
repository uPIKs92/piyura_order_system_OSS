import { forwardRef, useCallback, useEffect, useState, useImperativeHandle, useRef } from 'react';
import { toast } from 'sonner';
import { MapPin, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Empty, EmptyDescription, EmptyTitle } from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import { GroupedList, GroupedListDivider } from '@/components/ui/grouped-list';
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
import { LocationPickerPanel } from '@/components/LocationPickerPanel';
import { useLongPress } from '@/hooks/use-long-press';
import { useApi } from '@/lib/ApiProvider';
import { haptic } from '@/lib/format';
import type { Customer, PaginatedResponse } from '@/lib/types';

const emptyForm = () => ({
    name: '',
    phone: '',
    address: '',
    latitude: null as number | null,
    longitude: null as number | null,
    notes: '',
});

function formatLastOrdered(value: string): string {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

export interface CustomersPanelHandle {
    openCreate: () => void;
}

export const CustomersPanel = forwardRef<CustomersPanelHandle>(function CustomersPanel(_props, ref) {
    const { api } = useApi();
    const [customers, setCustomers] = useState<Customer[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [editing, setEditing] = useState<Customer | null>(null);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [form, setForm] = useState(emptyForm());
    const [saving, setSaving] = useState(false);
    const [pickerOpen, setPickerOpen] = useState(false);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setDebouncedSearch(search.trim());
            setPage(1);
        }, 300);
        return () => window.clearTimeout(timer);
    }, [search]);

    const load = useCallback(async () => {
        try {
            const data = await api.list<PaginatedResponse<Customer>>('customers', {
                page: String(page),
                per_page: '20',
                search: debouncedSearch || undefined,
            });
            setCustomers(data.data);
            setLastPage(data.last_page);
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memuat pelanggan');
        } finally {
            setLoading(false);
        }
    }, [api, page, debouncedSearch]);

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

    function openEdit(customer: Customer) {
        setEditing(customer);
        setForm({
            name: customer.name,
            phone: customer.phone || '',
            address: customer.address || '',
            latitude: customer.latitude ?? null,
            longitude: customer.longitude ?? null,
            notes: customer.notes || '',
        });
        setDrawerOpen(true);
    }

    async function handlePinConfirm(lat: number, lon: number) {
        setPickerOpen(false);
        let address: string | null = null;
        try {
            const result = await api.request<{ address: string }>('/delivery/reverse', {
                method: 'POST',
                body: { lat, lon },
            });
            if (result.address) address = result.address;
        } catch {
            /* handled below */
        }
        if (address != null) {
            const filled = address;
            setForm((f) => ({ ...f, address: filled, latitude: lat, longitude: lon }));
            toast.success('Alamat pelanggan terisi dari peta');
        } else {
            setForm((f) => ({ ...f, latitude: lat, longitude: lon }));
            toast.info('Pin tersimpan; teks alamat tidak berubah — bisa diedit manual.');
        }
    }

    function removePin() {
        setForm((f) => ({ ...f, latitude: null, longitude: null }));
    }

    function openDelete(customer: Customer) {
        setDeleteId(customer.id);
        setDeleteOpen(true);
    }

    async function save() {
        const name = form.name.trim();
        if (!name) {
            toast.error('Nama pelanggan wajib diisi');
            return;
        }
        if (saving) return;
        setSaving(true);
        const payload = {
            name,
            phone: form.phone.trim() || undefined,
            address: form.address.trim() || undefined,
            latitude: form.latitude,
            longitude: form.longitude,
            notes: form.notes.trim() || undefined,
        };
        try {
            if (editing) {
                await api.update<Customer>('customers', editing.id, payload);
            } else {
                await api.create<Customer>('customers', payload);
            }
            setDrawerOpen(false);
            haptic();
            toast.success('Pelanggan disimpan');
            await load();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menyimpan pelanggan');
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
            await api.destroy('customers', id);
            toast.success('Pelanggan dihapus');
            if (page > 1 && customers.length === 1) {
                setPage((p) => p - 1);
            } else {
                await load();
            }
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menghapus pelanggan');
        }
    }

    return (
        <div className="flex flex-col gap-3">
            <Input
                placeholder="Cari nama atau telepon..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
            />

            {loading ? (
                <div className="flex flex-col gap-2">
                    {Array.from({ length: 4 }).map((_, i) => (
                        <div key={i} className="rounded-lg border p-2.5">
                            <Skeleton className="h-6 w-1/2" />
                        </div>
                    ))}
                </div>
            ) : customers.length === 0 ? (
                <Empty>
                    <EmptyTitle>Belum ada pelanggan</EmptyTitle>
                    <EmptyDescription>Tambah pelanggan pertama</EmptyDescription>
                </Empty>
            ) : (
                <GroupedList>
                    {customers.map((customer, index) => (
                        <div key={customer.id}>
                            {index > 0 && <GroupedListDivider />}
                            <CustomerRow customer={customer} onEdit={openEdit} onDelete={openDelete} />
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
                title={editing ? 'Ubah Pelanggan' : 'Tambah Pelanggan'}
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
                        <FieldLabel>Lokasi Pelanggan di Peta</FieldLabel>
                        <p className="text-sm text-muted-foreground">
                            {form.latitude != null && form.longitude != null
                                ? `${form.latitude.toFixed(5)}, ${form.longitude.toFixed(5)}`
                                : 'Belum diatur'}
                        </p>
                        <div className="flex flex-wrap items-center gap-2">
                            <Button type="button" variant="outline" size="sm" onClick={() => setPickerOpen(true)}>
                                <MapPin data-icon="inline-start" />
                                Pilih di Peta
                            </Button>
                            {form.latitude != null && form.longitude != null ? (
                                <Button type="button" variant="ghost" size="sm" className="text-destructive" onClick={removePin}>
                                    <Trash2 data-icon="inline-start" />
                                    Hapus Pin
                                </Button>
                            ) : null}
                        </div>
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

            <LocationPickerPanel
                open={pickerOpen}
                onOpenChange={setPickerOpen}
                title="Lokasi Pelanggan di Peta"
                initialCoords={form.latitude != null && form.longitude != null ? { lat: form.latitude, lon: form.longitude } : null}
                onConfirm={handlePinConfirm}
            />

            <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus pelanggan?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Pelanggan tidak akan lagi muncul di saran pesanan.
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

interface CustomerRowProps {
    customer: Customer;
    onEdit: (customer: Customer) => void;
    onDelete: (customer: Customer) => void;
}

function CustomerRow({ customer, onEdit, onDelete }: CustomerRowProps) {
    const longPressFired = useRef(false);

    const longPress = useLongPress(() => {
        longPressFired.current = true;
        haptic();
        onDelete(customer);
    });

    function handleClick() {
        if (longPressFired.current) {
            longPressFired.current = false;
            return;
        }
        haptic();
        onEdit(customer);
    }

    return (
        <IosListRow
            title={customer.name}
            subtitle={
                [customer.phone, customer.address]
                    .filter(Boolean)
                    .join(' · ') || undefined
            }
            onClick={handleClick}
            onContextMenu={(e) => {
                e.preventDefault();
                haptic();
                onDelete(customer);
            }}
            {...longPress}
        >
            {customer.last_ordered_at && (
                <span className="text-xs text-muted-foreground/80">
                    Terakhir pesan {formatLastOrdered(customer.last_ordered_at)}
                </span>
            )}
        </IosListRow>
    );
}
