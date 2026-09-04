import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyTitle } from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import { GroupedList, GroupedListDivider } from '@/components/ui/grouped-list';
import { RowActions } from '@/components/ui/row-actions';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import { formatCurrency, haptic, todayISO } from '@/lib/format';
import type { Expense, ExpenseCategory, PaginatedResponse } from '@/lib/types';

export const EXPENSE_CATEGORY_LABELS: Record<ExpenseCategory, string> = {
    bahan_baku: 'Bahan Baku',
    kemasan: 'Kemasan',
    transport: 'Transport-Bensin',
    operasional: 'Listrik-Air-Pulsa',
    gaji: 'Gaji',
    lain_lain: 'Lain-lain',
};

const EXPENSE_CATEGORIES = Object.keys(EXPENSE_CATEGORY_LABELS) as ExpenseCategory[];

interface ExpensesPanelProps {
    date: string;
    onSaved?: () => void;
}

export function ExpensesPanel({ date, onSaved }: ExpensesPanelProps) {
    const { api } = useApi();
    const [expenses, setExpenses] = useState<Expense[]>([]);
    const [loading, setLoading] = useState(true);
    const [amount, setAmount] = useState('');
    const [category, setCategory] = useState<ExpenseCategory>('bahan_baku');
    const [note, setNote] = useState('');
    const [saving, setSaving] = useState(false);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [editing, setEditing] = useState<Expense | null>(null);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [form, setForm] = useState<{ expense_date: string; amount: string; category: ExpenseCategory; note: string }>({
        expense_date: date,
        amount: '',
        category: 'bahan_baku',
        note: '',
    });

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const data = await api.list<PaginatedResponse<Expense>>('expenses', {
                from: date,
                to: date,
                per_page: '100',
            });
            setExpenses(data.data);
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memuat pengeluaran');
        } finally {
            setLoading(false);
        }
    }, [api, date]);

    useEffect(() => {
        void load();
    }, [load]);

    const dailyTotal = expenses.reduce((acc, expense) => acc + Number(expense.amount), 0);

    async function quickAdd() {
        const value = Number(amount);
        if (!amount || Number.isNaN(value) || value <= 0) {
            toast.error('Nominal harus lebih dari 0');
            return;
        }
        if (saving) return;
        setSaving(true);
        try {
            await api.create<Expense>('expenses', {
                expense_date: date,
                amount: value,
                category,
                note: note.trim() || undefined,
            });
            haptic();
            toast.success('Pengeluaran dicatat');
            setAmount('');
            setNote('');
            await load();
            onSaved?.();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menyimpan pengeluaran');
        } finally {
            setSaving(false);
        }
    }

    function openEdit(expense: Expense) {
        setEditing(expense);
        setForm({
            expense_date: expense.expense_date,
            amount: String(Number(expense.amount)),
            category: expense.category,
            note: expense.note || '',
        });
        setDrawerOpen(true);
    }

    function openDelete(expense: Expense) {
        setDeleteId(expense.id);
        setDeleteOpen(true);
    }

    async function saveEdit() {
        if (!editing) return;
        const value = Number(form.amount);
        if (!form.amount || Number.isNaN(value) || value <= 0) {
            toast.error('Nominal harus lebih dari 0');
            return;
        }
        if (saving) return;
        setSaving(true);
        try {
            await api.update<Expense>('expenses', editing.id, {
                expense_date: form.expense_date,
                amount: value,
                category: form.category,
                note: form.note.trim() || undefined,
            });
            haptic();
            toast.success('Pengeluaran disimpan');
            setDrawerOpen(false);
            await load();
            onSaved?.();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menyimpan pengeluaran');
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
            await api.destroy('expenses', id);
            toast.success('Pengeluaran dihapus');
            await load();
            onSaved?.();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menghapus pengeluaran');
        }
    }

    return (
        <div className="flex flex-col gap-4">
            <Card>
                <CardContent className="flex flex-col gap-1">
                    <p className="text-xs text-muted-foreground">Total Pengeluaran</p>
                    <p className="text-xl font-bold tabular-nums">{formatCurrency(dailyTotal)}</p>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="flex flex-col gap-3">
                    <FieldGroup>
                        <div className="grid grid-cols-2 gap-2">
                            <Field>
                                <FieldLabel>Nominal (Rp)</FieldLabel>
                                <Input
                                    type="number"
                                    inputMode="numeric"
                                    min={0}
                                    step={500}
                                    value={amount}
                                    onChange={(e) => setAmount(e.target.value)}
                                    placeholder="15000"
                                />
                            </Field>
                            <Field>
                                <FieldLabel>Catatan (opsional)</FieldLabel>
                                <Input
                                    value={note}
                                    onChange={(e) => setNote(e.target.value)}
                                    placeholder="Belanja telur"
                                />
                            </Field>
                        </div>
                        <Field>
                            <FieldLabel>Kategori</FieldLabel>
                            <div className="flex flex-wrap gap-1.5">
                                {EXPENSE_CATEGORIES.map((value) => (
                                    <Button
                                        key={value}
                                        type="button"
                                        size="sm"
                                        variant={category === value ? 'default' : 'outline'}
                                        className="h-8 rounded-full px-3"
                                        onClick={() => setCategory(value)}
                                    >
                                        {EXPENSE_CATEGORY_LABELS[value]}
                                    </Button>
                                ))}
                            </div>
                        </Field>
                        <Button onClick={quickAdd} disabled={saving}>
                            Simpan
                        </Button>
                    </FieldGroup>
                </CardContent>
            </Card>

            {loading ? (
                <div className="flex flex-col gap-2">
                    {Array.from({ length: 3 }).map((_, i) => (
                        <div key={i} className="rounded-lg border p-2.5">
                            <Skeleton className="h-6 w-1/2" />
                        </div>
                    ))}
                </div>
            ) : expenses.length === 0 ? (
                <Empty>
                    <EmptyTitle>Belum ada pengeluaran</EmptyTitle>
                    <EmptyDescription>Catat pengeluaran pertama untuk tanggal ini</EmptyDescription>
                </Empty>
            ) : (
                <GroupedList>
                    {expenses.map((expense, index) => (
                        <div key={expense.id}>
                            {index > 0 && <GroupedListDivider />}
                            <IosListRow
                                title={formatCurrency(expense.amount)}
                                subtitle={
                                    [EXPENSE_CATEGORY_LABELS[expense.category], expense.note]
                                        .filter(Boolean)
                                        .join(' · ')
                                }
                                trailing={
                                    <RowActions
                                        actions={[
                                            { label: 'Ubah', onClick: () => openEdit(expense) },
                                            {
                                                label: 'Hapus',
                                                onClick: () => openDelete(expense),
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

            <ResponsiveFormPanel
                open={drawerOpen}
                onOpenChange={setDrawerOpen}
                title="Ubah Pengeluaran"
                footer={
                    <div className="flex gap-2">
                        <Button variant="outline" className="flex-1" onClick={() => setDrawerOpen(false)}>
                            Batal
                        </Button>
                        <Button className="flex-1" disabled={saving} onClick={saveEdit}>
                            {saving ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </div>
                }
            >
                <FieldGroup>
                    <Field>
                        <FieldLabel>Tanggal</FieldLabel>
                        <Input
                            type="date"
                            value={form.expense_date}
                            max={todayISO()}
                            onChange={(e) => setForm({ ...form, expense_date: e.target.value })}
                        />
                    </Field>
                    <Field>
                        <FieldLabel>Nominal (Rp)</FieldLabel>
                        <Input
                            type="number"
                            inputMode="numeric"
                            min={0}
                            value={form.amount}
                            onChange={(e) => setForm({ ...form, amount: e.target.value })}
                        />
                    </Field>
                    <Field>
                        <FieldLabel>Kategori</FieldLabel>
                        <Select
                            value={form.category}
                            onValueChange={(value) => setForm({ ...form, category: value as ExpenseCategory })}
                            items={EXPENSE_CATEGORIES.map((value) => ({ value, label: EXPENSE_CATEGORY_LABELS[value] }))}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Pilih kategori" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {EXPENSE_CATEGORIES.map((value) => (
                                        <SelectItem key={value} value={value}>
                                            {EXPENSE_CATEGORY_LABELS[value]}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field>
                        <FieldLabel>Catatan</FieldLabel>
                        <Textarea
                            value={form.note}
                            onChange={(e) => setForm({ ...form, note: e.target.value })}
                        />
                    </Field>
                </FieldGroup>
            </ResponsiveFormPanel>

            <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus pengeluaran?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Catatan pengeluaran akan dihapus permanen dan laporan harian ikut berubah.
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
}
