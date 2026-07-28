import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { ImagePlus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardRow, CardTitle } from '@/components/ui/card';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Skeleton } from '@/components/ui/skeleton';
import { useApi } from '@/lib/ApiProvider';
import { haptic } from '@/lib/format';
import type { AppBranding, TenantBranding } from '@/lib/types';

const emptyForm = (tenant?: TenantBranding | null) => ({
    name: tenant?.name ?? '',
    tagline: tenant?.tagline ?? '',
    address: tenant?.address ?? '',
    phone: tenant?.phone ?? '',
    email: tenant?.email ?? '',
    invoice_footer_text: tenant?.invoice_footer_text ?? '',
});

export default function SettingsProfile() {
    const { api, user, tenant, refreshTenant } = useApi();
    const isOwner = user?.role === 'owner';
    const fileRef = useRef<HTMLInputElement>(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [form, setForm] = useState(emptyForm());
    const [appBranding, setAppBranding] = useState<AppBranding | null>(null);

    const load = useCallback(async () => {
        try {
            const [settings, app] = await Promise.all([
                api.getTenantSettings(),
                api.getAppBranding(),
            ]);
            setForm(emptyForm(settings));
            setAppBranding(app);
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memuat pengaturan');
        } finally {
            setLoading(false);
        }
    }, [api]);

    useEffect(() => {
        load();
    }, [load]);

    async function save() {
        if (!isOwner) return;
        setSaving(true);
        try {
            await api.updateTenantSettings(form);
            await refreshTenant();
            haptic();
            toast.success('Profil bisnis disimpan');
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menyimpan');
        } finally {
            setSaving(false);
        }
    }

    async function handleLogoUpload(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file || !isOwner) return;
        setUploading(true);
        try {
            await api.uploadTenantLogo(file);
            await refreshTenant();
            await load();
            haptic();
            toast.success('Logo diperbarui');
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal mengunggah logo');
        } finally {
            setUploading(false);
            if (fileRef.current) fileRef.current.value = '';
        }
    }

    async function removeLogo() {
        if (!isOwner) return;
        setUploading(true);
        try {
            await api.deleteTenantLogo();
            await refreshTenant();
            await load();
            haptic();
            toast.success('Logo dihapus');
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menghapus logo');
        } finally {
            setUploading(false);
        }
    }

    const tenantInitial = (tenant?.name ?? form.name)?.charAt(0)?.toUpperCase() ?? '?';

    if (loading) {
        return (
            <div className="flex flex-col gap-4">
                <Skeleton className="h-48 w-full" />
                <Skeleton className="h-32 w-full" />
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-4">
            <Card>
                <CardHeader>
                    <CardTitle>Profil Bisnis</CardTitle>
                    <CardDescription>
                        {isOwner
                            ? 'Kelola identitas bisnis yang tampil di aplikasi dan invoice.'
                            : 'Informasi bisnis (hanya baca).'}
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    <div className="flex items-center gap-4">
                        <Avatar className="size-16 rounded-lg">
                            {tenant?.logo_url ? (
                                <AvatarImage src={tenant.logo_url} alt={form.name} />
                            ) : null}
                            <AvatarFallback className="rounded-lg text-xl">{tenantInitial}</AvatarFallback>
                        </Avatar>
                        {isOwner ? (
                            <div className="flex flex-col gap-2">
                                <input
                                    ref={fileRef}
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    className="hidden"
                                    onChange={handleLogoUpload}
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={uploading}
                                    onClick={() => fileRef.current?.click()}
                                >
                                    <ImagePlus data-icon="inline-start" />
                                    {uploading ? 'Mengunggah...' : 'Unggah Logo'}
                                </Button>
                                {tenant?.logo_url ? (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        disabled={uploading}
                                        onClick={removeLogo}
                                        className="text-destructive"
                                    >
                                        <Trash2 data-icon="inline-start" />
                                        Hapus Logo
                                    </Button>
                                ) : null}
                            </div>
                        ) : null}
                    </div>

                    <FieldGroup>
                        <Field>
                            <FieldLabel>Nama Bisnis</FieldLabel>
                            <Input
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                                disabled={!isOwner}
                                required
                            />
                        </Field>
                        <Field>
                            <FieldLabel>Tagline</FieldLabel>
                            <Input
                                value={form.tagline}
                                onChange={(e) => setForm({ ...form, tagline: e.target.value })}
                                disabled={!isOwner}
                                placeholder="Opsional"
                            />
                        </Field>
                        <Field>
                            <FieldLabel>Alamat</FieldLabel>
                            <Input
                                value={form.address}
                                onChange={(e) => setForm({ ...form, address: e.target.value })}
                                disabled={!isOwner}
                                placeholder="Opsional"
                            />
                        </Field>
                        <Field>
                            <FieldLabel>Telepon</FieldLabel>
                            <Input
                                value={form.phone}
                                onChange={(e) => setForm({ ...form, phone: e.target.value })}
                                disabled={!isOwner}
                                placeholder="Opsional"
                            />
                        </Field>
                        <Field>
                            <FieldLabel>Email</FieldLabel>
                            <Input
                                type="email"
                                value={form.email}
                                onChange={(e) => setForm({ ...form, email: e.target.value })}
                                disabled={!isOwner}
                                placeholder="Opsional"
                            />
                        </Field>
                        <Field>
                            <FieldLabel>Pesan Footer Invoice</FieldLabel>
                            <Input
                                value={form.invoice_footer_text}
                                onChange={(e) => setForm({ ...form, invoice_footer_text: e.target.value })}
                                disabled={!isOwner}
                                placeholder="Terima kasih telah berbelanja..."
                            />
                        </Field>
                    </FieldGroup>

                    {isOwner ? (
                        <Button onClick={save} disabled={saving || !form.name.trim()}>
                            {saving ? <Spinner data-icon="inline-start" /> : null}
                            {saving ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    ) : null}
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="border-b">
                    <CardTitle>Tentang Aplikasi</CardTitle>
                </CardHeader>
                <CardRow>
                    <span className="text-muted-foreground">Aplikasi</span>
                    <span className="font-medium tabular-nums">{appBranding?.app_name ?? 'Order Tracker'}</span>
                </CardRow>
                <CardRow>
                    <span className="text-muted-foreground">Platform</span>
                    <span className="font-medium tabular-nums">{appBranding?.platform_name ?? 'Piyuralabs'}</span>
                </CardRow>
            </Card>
        </div>
    );
}
