import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { ImagePlus, MapPin, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Input } from '@/components/ui/input';
import { CardRow } from '@/components/ui/card';
import { CollapsibleCard } from '@/components/CollapsibleCard';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Skeleton } from '@/components/ui/skeleton';
import { InstallAppCard } from '@/components/InstallAppCard';
import { LocationPickerPanel } from '@/components/LocationPickerPanel';
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
    const [originPin, setOriginPin] = useState<{ lat: number; lon: number } | null>(null);
    const [mapOpen, setMapOpen] = useState(false);
    const [savingPin, setSavingPin] = useState(false);

    const load = useCallback(async () => {
        try {
            const [settings, app] = await Promise.all([
                api.getTenantSettings(),
                api.getAppBranding(),
            ]);
            setForm(emptyForm(settings));
            setOriginPin(
                settings.latitude != null && settings.longitude != null
                    ? { lat: settings.latitude, lon: settings.longitude }
                    : null,
            );
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

    async function persistPin(latitude: number | null, longitude: number | null, address?: string) {
        if (!isOwner) return;
        if (!form.name.trim()) {
            toast.error('Isi Nama Bisnis terlebih dahulu');
            return;
        }
        setSavingPin(true);
        try {
            const settings = await api.updateTenantSettings({
                name: tenant?.name ?? form.name,
                latitude,
                longitude,
                ...(address != null ? { address } : {}),
            });
            setOriginPin(
                settings.latitude != null && settings.longitude != null
                    ? { lat: settings.latitude, lon: settings.longitude }
                    : null,
            );
            haptic();
            toast.success(latitude == null ? 'Pin lokasi toko dihapus' : 'Lokasi toko disimpan');
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menyimpan lokasi');
        } finally {
            setSavingPin(false);
        }
    }

    async function handlePinConfirm(lat: number, lon: number) {
        // Same semantics as the order form: reverse-geocode the pin and fill
        // the Alamat field with the result; the address is persisted together
        // with the pin so there is no half-saved state.
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
            setForm((f) => ({ ...f, address }));
        } else {
            toast.info('Pin tersimpan; teks alamat tidak berubah — bisa diedit manual.');
        }
        await persistPin(lat, lon, address ?? undefined);
        if (address != null) {
            toast.success('Alamat toko terisi dari peta');
        }
    }

    function removePin() {
        void persistPin(null, null);
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
            <CollapsibleCard
                title="Profil Bisnis"
                description={
                    isOwner
                        ? 'Kelola identitas bisnis yang tampil di aplikasi dan invoice.'
                        : 'Informasi bisnis (hanya baca).'
                }
                contentClassName="flex flex-col gap-4"
            >
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
                    {isOwner ? (
                        <Field>
                            <FieldLabel>Lokasi Toko di Peta</FieldLabel>
                            <p className="text-sm text-muted-foreground">
                                {originPin
                                    ? `${originPin.lat.toFixed(5)}, ${originPin.lon.toFixed(5)}`
                                    : 'Belum diatur — jarak memakai alamat teks'}
                            </p>
                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={savingPin}
                                    onClick={() => setMapOpen(true)}
                                >
                                    <MapPin data-icon="inline-start" />
                                    Atur di Peta
                                </Button>
                                {originPin ? (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="text-destructive"
                                        disabled={savingPin}
                                        onClick={removePin}
                                    >
                                        <Trash2 data-icon="inline-start" />
                                        Hapus Pin
                                    </Button>
                                ) : null}
                            </div>
                        </Field>
                    ) : null}
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
            </CollapsibleCard>

            <CollapsibleCard title="Tentang Aplikasi" contentClassName="px-0 pb-0">
                <CardRow>
                    <span className="text-muted-foreground">Aplikasi</span>
                    <span className="font-medium tabular-nums">{appBranding?.app_name ?? 'Simple Order Systems'}</span>
                </CardRow>
                <CardRow>
                    <span className="text-muted-foreground">Platform</span>
                    <span className="font-medium tabular-nums">{appBranding?.platform_name ?? 'Piyuralabs'}</span>
                </CardRow>
            </CollapsibleCard>

            <InstallAppCard />

            <LocationPickerPanel
                open={mapOpen}
                onOpenChange={setMapOpen}
                title="Lokasi Toko di Peta"
                initialCoords={originPin}
                onConfirm={handlePinConfirm}
            />
        </div>
    );
}
