import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { GroupedList, GroupedListDivider } from '@/components/ui/grouped-list';
import { RowActions } from '@/components/ui/row-actions';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
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
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { IosPageHeader } from '@/components/ios/IosPageHeader';
import { Page } from '@/components/Page';
import { IosListRow } from '@/components/ios/IosListRow';
import { useApi } from '@/lib/ApiProvider';
import { haptic } from '@/lib/format';
import type { User } from '@/lib/types';

const emptyForm = () => ({ name: '', email: '', password: '', role: 'staff', is_active: true });

export default function Users() {
    const { api, user: currentUser } = useApi();
    const [users, setUsers] = useState<User[]>([]);
    const [loading, setLoading] = useState(true);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [editing, setEditing] = useState<User | null>(null);
    const [resetOpen, setResetOpen] = useState(false);
    const [resetTarget, setResetTarget] = useState<User | null>(null);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [form, setForm] = useState(emptyForm());

    const load = useCallback(async () => {
        try {
            setUsers(await api.list<User[]>('users'));
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memuat');
        } finally {
            setLoading(false);
        }
    }, [api]);

    useEffect(() => { load(); }, [load]);

    const sortedUsers = useMemo(() => {
        if (!currentUser) return users;
        const self = users.find((u) => u.id === currentUser.id);
        const others = users.filter((u) => u.id !== currentUser.id);
        return self ? [self, ...others] : users;
    }, [users, currentUser]);

    function openCreate() {
        setEditing(null);
        setForm(emptyForm());
        setDrawerOpen(true);
    }

    function openEdit(u: User) {
        setEditing(u);
        setForm({ name: u.name, email: u.email, password: '', role: u.role, is_active: u.is_active });
        setDrawerOpen(true);
    }

    async function save() {
        try {
            const payload = { ...form };
            if (editing && !payload.password) delete (payload as { password?: string }).password;
            if (editing) {
                await api.update('users', editing.id, payload);
            } else {
                await api.create('users', payload);
            }
            setDrawerOpen(false);
            haptic();
            toast.success('Disimpan');
            await load();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menyimpan');
        }
    }

    async function sendPasswordReset() {
        if (!resetTarget) return;
        try {
            await api.request('/password/email', { method: 'POST', body: { email: resetTarget.email } });
            setResetOpen(false);
            haptic();
            toast.success('Link reset password dikirim');
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal kirim reset');
        }
    }

    async function handleDelete() {
        if (!deleteId) return;
        try {
            await api.destroy('users', deleteId);
            setDeleteOpen(false);
            haptic();
            toast.success('User dihapus');
            await load();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menghapus');
        }
    }

    return (
        <Page>
            <IosPageHeader
                title="Pengguna"
                description="Kelola staff dan peran"
                action={<Button size="sm" onClick={openCreate}><Plus data-icon="inline-start" /> Tambah</Button>}
            />

            {loading ? (
                <div className="flex flex-col gap-3"><Skeleton className="h-16 w-full" /></div>
            ) : users.length === 0 ? (
                <Empty><EmptyTitle>Belum ada staff</EmptyTitle><EmptyDescription>Tambah staff pertama</EmptyDescription></Empty>
            ) : (
                <GroupedList>
                    {sortedUsers.map((u, index) => (
                        <div key={u.id}>
                            {index > 0 && <GroupedListDivider />}
                            <IosListRow
                            title={u.name}
                            subtitle={u.email}
                            trailing={
                                u.id !== currentUser?.id ? (
                                    <div className="flex items-center gap-2">
                                        <Badge variant={u.is_active ? 'default' : 'destructive'}>
                                            {u.is_active ? 'Aktif' : 'Nonaktif'}
                                        </Badge>
                                        <RowActions
                                            actions={[
                                                { label: 'Ubah', onClick: () => openEdit(u) },
                                                { label: 'Reset kata sandi', onClick: () => { setResetTarget(u); setResetOpen(true); } },
                                                { label: 'Hapus', onClick: () => { setDeleteId(u.id); setDeleteOpen(true); }, destructive: true },
                                            ]}
                                        />
                                    </div>
                                ) : (
                                    <Badge variant="secondary">Anda</Badge>
                                )
                            }
                        />
                        </div>
                    ))}
                </GroupedList>
            )}

            <Drawer open={drawerOpen} onOpenChange={setDrawerOpen} showSwipeHandle>
                <DrawerContent>
                    <DrawerHeader><DrawerTitle>{editing ? 'Ubah Staff' : 'Tambah Staff'}</DrawerTitle></DrawerHeader>
                    <div className="px-4 pb-4">
                        <FieldGroup>
                            <Field><FieldLabel>Nama</FieldLabel><Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required /></Field>
                            <Field><FieldLabel>Email</FieldLabel><Input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required /></Field>
                            <Field>
                                <FieldLabel>{editing ? 'Password (kosongkan jika tidak diubah)' : 'Password'}</FieldLabel>
                                <Input type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} />
                            </Field>
                            <Field>
                                <FieldLabel>Role</FieldLabel>
                                <Select
                                    value={form.role}
                                    onValueChange={(v) => setForm({ ...form, role: v })}
                                    items={[
                                        { value: 'staff', label: 'Staff' },
                                        { value: 'owner', label: 'Owner' },
                                    ]}
                                >
                                    <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="staff">Staff</SelectItem>
                                            <SelectItem value="owner">Owner</SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field orientation="horizontal">
                                <FieldLabel>Aktif</FieldLabel>
                                <Switch checked={form.is_active} onCheckedChange={(checked) => setForm({ ...form, is_active: checked })} />
                            </Field>
                        </FieldGroup>
                    </div>
                    <DrawerFooter>
                        <div className="flex gap-2">
                            <Button variant="outline" className="flex-1" onClick={() => setDrawerOpen(false)}>Batal</Button>
                            <Button className="flex-1" onClick={save}>{editing ? 'Simpan' : 'Buat'}</Button>
                        </div>
                    </DrawerFooter>
                </DrawerContent>
            </Drawer>

            <AlertDialog open={resetOpen} onOpenChange={setResetOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Reset kata sandi?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Kirim link reset ke {resetTarget?.email}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction onClick={sendPasswordReset}>Kirim</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus user?</AlertDialogTitle>
                        <AlertDialogDescription>User tidak akan bisa login lagi.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDelete} className="bg-destructive text-destructive-foreground">Hapus</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </Page>
    );
}
