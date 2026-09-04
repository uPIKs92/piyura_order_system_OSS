import { forwardRef, useCallback, useEffect, useMemo, useState, useImperativeHandle, useRef } from 'react';
import { toast } from 'sonner';
import { KeyRound } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { GroupedList, GroupedListDivider } from '@/components/ui/grouped-list';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
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
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ResponsiveFormPanel } from '@/components/ResponsiveFormPanel';
import { IosListRow } from '@/components/ios/IosListRow';
import { IosSwipeRow } from '@/components/ios/IosSwipeRow';
import { useLongPress } from '@/hooks/use-long-press';
import { useApi } from '@/lib/ApiProvider';
import { haptic } from '@/lib/format';
import type { PaginatedResponse, User } from '@/lib/types';

const emptyForm = () => ({ name: '', email: '', password: '', role: 'staff', is_active: true });

export interface StaffPanelHandle {
    openCreate: () => void;
}

export const StaffPanel = forwardRef<StaffPanelHandle>(function StaffPanel(_props, ref) {
    const { api, user: currentUser } = useApi();
    const [users, setUsers] = useState<User[]>([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [editing, setEditing] = useState<User | null>(null);
    const [resetOpen, setResetOpen] = useState(false);
    const [resetTarget, setResetTarget] = useState<User | null>(null);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [form, setForm] = useState(emptyForm());

    const load = useCallback(async () => {
        try {
            const data = await api.list<PaginatedResponse<User>>('users', {
                page: String(page),
                per_page: '20',
            });
            setUsers(data.data);
            setLastPage(data.last_page);
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memuat');
        } finally {
            setLoading(false);
        }
    }, [api, page]);

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

    useImperativeHandle(ref, () => ({ openCreate }), []);

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
            if (page > 1 && users.length === 1) {
                setPage((p) => p - 1);
            } else {
                await load();
            }
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menghapus');
        }
    }

    return (
        <div className="flex flex-col gap-3">
            {loading ? (
                <div className="flex flex-col gap-3"><Skeleton className="h-16 w-full" /></div>
            ) : users.length === 0 ? (
                <Empty><EmptyTitle>Belum ada staff</EmptyTitle><EmptyDescription>Tambah staff pertama</EmptyDescription></Empty>
            ) : (
                <GroupedList>
                    {sortedUsers.map((u, index) => (
                        <div key={u.id}>
                            {index > 0 && <GroupedListDivider />}
                            <StaffRow
                                user={u}
                                isSelf={u.id === currentUser?.id}
                                onEdit={openEdit}
                                onReset={(target) => { setResetTarget(target); setResetOpen(true); }}
                                onDelete={(target) => { setDeleteId(target.id); setDeleteOpen(true); }}
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
                title={editing ? 'Ubah Staff' : 'Tambah Staff'}
                footer={
                    <div className="flex gap-2">
                        <Button variant="outline" className="flex-1" onClick={() => setDrawerOpen(false)}>Batal</Button>
                        <Button className="flex-1" onClick={save}>{editing ? 'Simpan' : 'Buat'}</Button>
                    </div>
                }
            >
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
            </ResponsiveFormPanel>

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
        </div>
    );
});

interface StaffRowProps {
    user: User;
    isSelf: boolean;
    onEdit: (user: User) => void;
    onReset: (user: User) => void;
    onDelete: (user: User) => void;
}

function StaffRow({ user, isSelf, onEdit, onReset, onDelete }: StaffRowProps) {
    const deletable = user.role !== 'owner';
    const longPressFired = useRef(false);

    const longPress = useLongPress(() => {
        if (!deletable) return;
        longPressFired.current = true;
        haptic();
        onDelete(user);
    });

    function handleClick() {
        if (longPressFired.current) {
            longPressFired.current = false;
            return;
        }
        haptic();
        onEdit(user);
    }

    if (isSelf) {
        return (
            <IosListRow
                title={user.name}
                subtitle={user.email}
                trailing={<Badge variant="secondary">Anda</Badge>}
            />
        );
    }

    return (
        <IosSwipeRow
            onSwipeLeft={() => onReset(user)}
            leftActions={
                <button
                    type="button"
                    onClick={() => onReset(user)}
                    className="action-edit flex h-full items-center gap-1.5 px-5 text-xs font-semibold"
                >
                    <KeyRound className="size-4 shrink-0" />
                    Reset Sandi
                </button>
            }
        >
            <IosListRow
                title={user.name}
                subtitle={user.email}
                onClick={handleClick}
                trailing={
                    <Badge variant={user.is_active ? 'default' : 'destructive'}>
                        {user.is_active ? 'Aktif' : 'Nonaktif'}
                    </Badge>
                }
                onContextMenu={
                    deletable
                        ? (e) => {
                              e.preventDefault();
                              haptic();
                              onDelete(user);
                          }
                        : undefined
                }
                {...longPress}
            />
        </IosSwipeRow>
    );
}
