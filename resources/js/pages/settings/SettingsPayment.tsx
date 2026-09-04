import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { ImagePlus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Input } from '@/components/ui/input';
import { CollapsibleCard } from '@/components/CollapsibleCard';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { useApi } from '@/lib/ApiProvider';
import { haptic } from '@/lib/format';
import type { PaymentSettings } from '@/lib/types';

export default function SettingsPayment() {
    const { api, user } = useApi();
    const isOwner = user?.role === 'owner';
    const fileRef = useRef<HTMLInputElement>(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [testing, setTesting] = useState(false);
    const [settings, setSettings] = useState<PaymentSettings | null>(null);
    const [bankName, setBankName] = useState('');
    const [bankAccountName, setBankAccountName] = useState('');
    const [bankAccountNumber, setBankAccountNumber] = useState('');
    const [mayarApiKey, setMayarApiKey] = useState('');

    const load = useCallback(async () => {
        try {
            const data = await api.getPaymentSettings();
            setSettings(data);
            setBankName(data.bank_name ?? '');
            setBankAccountName(data.bank_account_name ?? '');
            setBankAccountNumber(data.bank_account_number ?? '');
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memuat pengaturan pembayaran');
        } finally {
            setLoading(false);
        }
    }, [api]);

    useEffect(() => {
        load();
    }, [load]);


    async function saveBank() {
        if (!isOwner) return;
        setSaving(true);
        try {
            const data = await api.updatePaymentSettings({
                bank_name: bankName,
                bank_account_name: bankAccountName,
                bank_account_number: bankAccountNumber,
            });
            setSettings(data);
            haptic();
            toast.success('Detail bank disimpan');
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menyimpan');
        } finally {
            setSaving(false);
        }
    }

    async function saveMayarKey() {
        if (!isOwner) return;
        setSaving(true);
        try {
            const data = await api.updatePaymentSettings({ mayar_api_key: mayarApiKey });
            setSettings(data);
            setMayarApiKey('');
            haptic();
            toast.success('API key Mayar disimpan');
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menyimpan');
        } finally {
            setSaving(false);
        }
    }

    async function uploadQrisFile(file: File) {
        if (!isOwner) return;
        setUploading(true);
        try {
            const data = await api.uploadQris(file);
            setSettings(data);
            haptic();
            toast.success('Gambar QRIS diperbarui');
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal mengunggah QRIS');
        } finally {
            setUploading(false);
        }
    }

    async function removeQris() {
        if (!isOwner) return;
        try {
            const data = await api.deleteQris();
            setSettings(data);
            haptic();
            toast.success('Gambar QRIS dihapus');
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menghapus QRIS');
        }
    }

    async function testConnection() {
        setTesting(true);
        try {
            const result = await api.testMayar();
            haptic();
            if (result.connected) {
                toast.success(result.message);
            } else {
                toast.error(result.message);
            }
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menguji koneksi');
        } finally {
            setTesting(false);
        }
    }


    if (loading || !settings) {
        return <Skeleton className="h-96 w-full" />;
    }

    return (
        <div className="flex flex-col gap-4">
            <CollapsibleCard
                title="Detail Bank"
                description="Informasi rekening untuk pembayaran transfer manual."
                contentClassName="flex flex-col gap-3"
            >
                <FieldGroup>
                    <Field>
                        <FieldLabel>Nama bank</FieldLabel>
                        <Input value={bankName} onChange={(e) => setBankName(e.target.value)} placeholder="BCA / Mandiri / BRI" />
                    </Field>
                    <Field>
                        <FieldLabel>Atas nama</FieldLabel>
                        <Input value={bankAccountName} onChange={(e) => setBankAccountName(e.target.value)} placeholder="Nama pemilik rekening" />
                    </Field>
                    <Field>
                        <FieldLabel>Nomor rekening</FieldLabel>
                        <Input value={bankAccountNumber} onChange={(e) => setBankAccountNumber(e.target.value)} placeholder="1234567890" />
                    </Field>
                </FieldGroup>
                <Button size="sm" disabled={!isOwner || saving} onClick={saveBank}>
                    {saving ? <Spinner data-icon="inline-start" /> : null}
                    {saving ? 'Menyimpan...' : 'Simpan'}
                </Button>
            </CollapsibleCard>

            <CollapsibleCard
                title="QRIS Statis"
                description="Unggah gambar QRIS statis. Jika ada, ini dipakai (gratis) dan menonaktifkan Mayar dinamis."
                contentClassName="flex flex-col gap-3"
            >
                {settings.qris_image_url ? (
                    <div className="flex flex-col items-center gap-3">
                        <img src={settings.qris_image_url} alt="QRIS" className="h-48 w-48 rounded-xl object-contain border" />
                        <Button size="sm" variant="outline" disabled={!isOwner} onClick={removeQris}>
                            <Trash2 className="size-4" /> Hapus QRIS
                        </Button>
                    </div>
                ) : (
                    <div className="flex flex-col gap-3">
                        <input
                            ref={fileRef}
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            className="hidden"
                            onChange={(e) => {
                                const f = e.target.files?.[0];
                                if (f) uploadQrisFile(f);
                                e.target.value = '';
                            }}
                        />
                        <Button size="sm" variant="outline" disabled={!isOwner || uploading} onClick={() => fileRef.current?.click()}>
                            {uploading ? <Spinner data-icon="inline-start" /> : <ImagePlus className="size-4" />}
                            {uploading ? 'Mengunggah...' : 'Unggah gambar QRIS'}
                        </Button>
                    </div>
                )}
            </CollapsibleCard>

            <CollapsibleCard
                title="Mayar (QRIS Dinamis)"
                description={
                    <>
                        Aktif jika API key terisi <strong>dan</strong> tidak ada gambar QRIS statis. QR dibuat otomatis per pesanan.
                    </>
                }
                contentClassName="flex flex-col gap-3"
            >
                <FieldGroup>
                    <Field>
                        <FieldLabel>API Key Mayar</FieldLabel>
                        <Input
                            type="password"
                            value={mayarApiKey}
                            onChange={(e) => setMayarApiKey(e.target.value)}
                            placeholder={settings.mayar_enabled ? '•••••••• (terisi, biarkan kosong untuk tetap)' : 'Tempel API key dari dashboard Mayar'}
                        />
                    </Field>
                </FieldGroup>
                <div className="flex gap-2">
                    <Button size="sm" disabled={!isOwner || saving || !mayarApiKey} onClick={saveMayarKey}>
                        {saving ? <Spinner data-icon="inline-start" /> : null}
                        Simpan API Key
                    </Button>
                    <Button size="sm" variant="outline" disabled={!isOwner || testing || !settings.mayar_enabled} onClick={testConnection}>
                        {testing ? <Spinner data-icon="inline-start" /> : null}
                        {testing ? 'Menguji...' : 'Tes Koneksi'}
                    </Button>
                </div>
                <Separator />
                <p className="text-xs text-muted-foreground">
                    Status: {settings.mayar_enabled ? 'Aktif' : 'Nonaktif'}
                    {settings.qris_image_url && ' (QRIS statis dipakai)'}
                </p>
            </CollapsibleCard>
        </div>
    );
}
