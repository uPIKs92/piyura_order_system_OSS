import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardRow, CardTitle } from '@/components/ui/card';
import { CollapsibleCard } from '@/components/CollapsibleCard';
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useApi } from '@/lib/ApiProvider';
import { haptic, todayISO } from '@/lib/format';
import type { DeliverySettingsResponse, DeliveryTestResponse, ExpirySettings, ImportResult, IntegrationSettings, SheetsSyncEntry, PpnSettings, PajakSettings, PphMode } from '@/lib/types';

const emptyIntegrations: IntegrationSettings = {
    sheets_sync_enabled: false,
    sheets_spreadsheet_id: null,
    sheets_products_tab: 'Products',
    sheets_orders_tab: 'Orders',
    sheets_reporting_tab: 'Reporting',
    google_connected: false,
    google_connected_email: null,
};

const pphModeItems: { value: PphMode; label: string }[] = [
    { value: 'umkm_non_pkp', label: 'UMKM Non-PKP (0,5% / 12%)' },
    { value: 'umkm_pkp_22', label: 'UMKM PKP (PPh 22 2,5%)' },
];

const deliveryModeItems: { value: DeliverySettingsResponse['fee_mode']; label: string }[] = [
    { value: 'per_km', label: 'Per Kilometer' },
    { value: 'fixed', label: 'Tarif Tetap' },
];

export default function SettingsOps() {
    const { api } = useApi();
    const [loading, setLoading] = useState(true);
    const [ppn, setPpn] = useState<PpnSettings>({ enabled: true, percentage: 11 });
    const [pajak, setPajak] = useState<PajakSettings>({ npwp: null, pph_mode: 'umkm_non_pkp' });
    const [delivery, setDelivery] = useState<DeliverySettingsResponse>({
        fee_mode: 'per_km',
        fee_per_km: 5000,
        min_fee: 0,
        fixed_fee: 10000,
        google_maps_configured: false,
    });
    const [mapsApiKey, setMapsApiKey] = useState('');
    const [savingDelivery, setSavingDelivery] = useState(false);
    const [savingMapsKey, setSavingMapsKey] = useState(false);
    const [testingDelivery, setTestingDelivery] = useState(false);
    const [expirySettings, setExpirySettings] = useState<ExpirySettings>({ alert_days: 30 });
    const [integrations, setIntegrations] = useState<IntegrationSettings>(emptyIntegrations);
    const [failedSyncs, setFailedSyncs] = useState<SheetsSyncEntry[]>([]);
    const [pendingSyncs, setPendingSyncs] = useState<SheetsSyncEntry[]>([]);
    const [exporting, setExporting] = useState(false);
    const [importFile, setImportFile] = useState<File | null>(null);
    const [importResult, setImportResult] = useState<ImportResult | null>(null);
    const [importing, setImporting] = useState(false);
    const [sheetsImporting, setSheetsImporting] = useState(false);
    const [syncing, setSyncing] = useState(false);
    const [connecting, setConnecting] = useState(false);
    const [disconnecting, setDisconnecting] = useState(false);

    async function exportTenantData() {
        setExporting(true);
        try {
            const data = await api.exportTenantData();
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = `tenant-export-${todayISO()}.json`;
            anchor.click();
            URL.revokeObjectURL(url);
            haptic();
            toast.success('Data tenant diekspor');
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal mengekspor data tenant');
        } finally {
            setExporting(false);
        }
    }

    async function loadSyncStatus() {
        const [failed, pending] = await Promise.all([
            api.request<SheetsSyncEntry[]>('/sheets-sync?status=failed').catch(() => []),
            api.request<SheetsSyncEntry[]>('/sheets-sync?status=pending').catch(() => []),
        ]);
        setFailedSyncs(failed);
        setPendingSyncs(pending);
    }

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        if (params.get('google') === 'connected') {
            toast.success('Google Sheets terhubung');
            params.delete('google');
            const next = `${window.location.pathname}${params.toString() ? `?${params}` : ''}`;
            window.history.replaceState({}, '', next);
        } else if (params.get('google') === 'error') {
            toast.error(params.get('message') || 'Gagal menghubungkan Google');
            params.delete('google');
            params.delete('message');
            const next = `${window.location.pathname}${params.toString() ? `?${params}` : ''}`;
            window.history.replaceState({}, '', next);
        }

        Promise.all([
            api.request<PpnSettings>('/settings/ppn'),
            api.request<PajakSettings>('/settings/pajak'),
            api.request<DeliverySettingsResponse>('/settings/delivery'),
            api.request<ExpirySettings>('/settings/expiry'),
            api.request<IntegrationSettings>('/settings/integrations'),
            loadSyncStatus(),
        ])
            .then(([p, pajakSettings, deliverySettings, expiry, integrationSettings]) => {
                setPpn(p);
                setPajak(pajakSettings);
                setDelivery(deliverySettings);
                setExpirySettings(expiry);
                setIntegrations({ ...emptyIntegrations, ...integrationSettings });
            })
            .catch((err) => toast.error(err instanceof Error ? err.message : 'Gagal memuat operasi'))
            .finally(() => setLoading(false));
    }, [api]);

    async function savePpn() {
        try {
            const updated = await api.request<PpnSettings>('/settings/ppn', {
                method: 'PATCH',
                body: ppn,
            });
            setPpn(updated);
            haptic();
            toast.success('Pengaturan PPN disimpan');
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menyimpan PPN');
        }
    }

    async function savePajak() {
        try {
            const updated = await api.request<PajakSettings>('/settings/pajak', {
                method: 'PATCH',
                body: { npwp: pajak.npwp, pph_mode: pajak.pph_mode },
            });
            setPajak(updated);
            haptic();
            toast.success('Pengaturan pajak disimpan');
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menyimpan pajak');
        }
    }

    async function saveDelivery() {
        setSavingDelivery(true);
        try {
            const updated = await api.request<DeliverySettingsResponse>('/settings/delivery', {
                method: 'PATCH',
                body: {
                    fee_mode: delivery.fee_mode,
                    fee_per_km: delivery.fee_per_km,
                    min_fee: delivery.min_fee,
                    fixed_fee: delivery.fixed_fee,
                },
            });
            setDelivery(updated);
            haptic();
            toast.success('Pengaturan pengiriman disimpan');
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menyimpan pengiriman');
        } finally {
            setSavingDelivery(false);
        }
    }

    // Disabled — automatic distance now uses OpenStreetMap (no API key).
    // Re-enable together with fetchMetersGoogle() in DeliveryEstimateService
    // and the google_maps_api_key storage path when the Google fallback is
    // needed again.
    // async function saveMapsKey() {
    //     const key = mapsApiKey.trim();
    //     if (!key) return;
    //     setSavingMapsKey(true);
    //     try {
    //         const updated = await api.request<DeliverySettingsResponse>('/settings/delivery', {
    //             method: 'PATCH',
    //             body: { google_maps_api_key: key },
    //         });
    //         setDelivery(updated);
    //         setMapsApiKey('');
    //         haptic();
    //         toast.success('API key Google Maps disimpan');
    //     } catch (err) {
    //         toast.error(err instanceof Error ? err.message : 'Gagal menyimpan API key');
    //     } finally {
    //         setSavingMapsKey(false);
    //     }
    // }

    async function testDeliveryConnection() {
        setTestingDelivery(true);
        try {
            const result = await api.request<DeliveryTestResponse>('/settings/delivery/test', {
                method: 'POST',
            });
            haptic();
            if (result.connected) {
                toast.success(result.message);
            } else {
                toast.error(result.message);
            }
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menguji koneksi');
        } finally {
            setTestingDelivery(false);
        }
    }

    async function saveExpiry() {
        try {
            const updated = await api.request<ExpirySettings>('/settings/expiry', {
                method: 'PATCH',
                body: { alert_days: expirySettings.alert_days },
            });
            setExpirySettings(updated);
            haptic();
            toast.success('Pengaturan kedaluwarsa disimpan');
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menyimpan pengaturan kedaluwarsa');
        }
    }

    async function saveIntegrations() {
        try {
            const updated = await api.request<IntegrationSettings>('/settings/integrations', {
                method: 'PATCH',
                body: {
                    sheets_sync_enabled: integrations.sheets_sync_enabled,
                    sheets_spreadsheet_id: integrations.sheets_spreadsheet_id,
                    sheets_products_tab: integrations.sheets_products_tab,
                    sheets_orders_tab: integrations.sheets_orders_tab,
                    sheets_reporting_tab: integrations.sheets_reporting_tab,
                },
            });
            setIntegrations({ ...emptyIntegrations, ...updated });
            haptic();
            toast.success('Integrasi disimpan');
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menyimpan integrasi');
        }
    }

    async function connectGoogle() {
        setConnecting(true);
        try {
            const { url } = await api.request<{ url: string }>('/integrations/google/connect');
            window.location.href = url;
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memulai OAuth Google');
            setConnecting(false);
        }
    }

    async function disconnectGoogle() {
        setDisconnecting(true);
        try {
            const updated = await api.request<IntegrationSettings>('/integrations/google', {
                method: 'DELETE',
            });
            setIntegrations({ ...emptyIntegrations, ...updated });
            haptic();
            toast.success('Google Sheets diputuskan');
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal memutuskan Google');
        } finally {
            setDisconnecting(false);
        }
    }

    async function importFromSheets() {
        setSheetsImporting(true);
        setImportResult(null);
        try {
            const result = await api.request<ImportResult>('/integrations/google/import', {
                method: 'POST',
            });
            setImportResult(result);
            haptic();
            toast.success(`Import Sheets selesai: ${result.success}/${result.total} berhasil`);
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal import dari Google Sheets');
        } finally {
            setSheetsImporting(false);
        }
    }

    async function syncNow() {
        setSyncing(true);
        try {
            const result = await api.request<{ sent: number; failed: number; pending: number; failed_total: number }>('/sheets-sync/dispatch', {
                method: 'POST',
            });
            haptic();
            toast.success(`${result.sent} order tersinkron`);
            void loadSyncStatus();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal menjalankan sinkronisasi');
        } finally {
            setSyncing(false);
        }
    }

    async function retrySync(id: number) {
        try {
            await api.request(`/sheets-sync/${id}/retry`, { method: 'POST' });
            setFailedSyncs((prev) => prev.filter((r) => r.id !== id));
            haptic();
            toast.success('Sync dijadwalkan ulang');
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal retry sync');
        }
    }

    async function downloadTemplate() {
        try {
            const blob = await api.requestBlob('/imports/template');
            const url = URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = 'orders-import-template.xlsx';
            anchor.click();
            URL.revokeObjectURL(url);
            haptic();
            toast.success('Template diunduh');
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal unduh template');
        }
    }

    async function uploadImport() {
        if (!importFile) return;
        setImporting(true);
        setImportResult(null);
        try {
            const form = new FormData();
            form.append('file', importFile);
            const result = await api.upload<ImportResult>('/imports', form);
            setImportResult(result);
            haptic();
            if (result.queued) {
                toast.success(`Import dijadwalkan (${result.rows} baris)`);
            } else {
                toast.success(`Import selesai: ${result.success}/${result.total} berhasil`);
            }
            setImportFile(null);
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Gagal mengunggah file');
        } finally {
            setImporting(false);
        }
    }

    if (loading) {
        return (
            <div className="flex flex-col gap-4">
                <Skeleton className="h-32" />
                <Skeleton className="h-32" />
                <Skeleton className="h-32" />
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-4">
            <CollapsibleCard
                title="Pengaturan PPN"
                description="Pajak pertambahan nilai untuk order baru"
            >
                <FieldGroup>
                    <Field orientation="horizontal">
                        <FieldLabel>PPN aktif</FieldLabel>
                        <Switch checked={ppn.enabled} onCheckedChange={(checked) => setPpn({ ...ppn, enabled: checked })} />
                    </Field>
                    <Field>
                        <FieldLabel>Persentase PPN (%)</FieldLabel>
                        <Input
                            type="number"
                            min={0}
                            max={100}
                            step={0.01}
                            value={ppn.percentage}
                            onChange={(e) => setPpn({ ...ppn, percentage: Number(e.target.value) })}
                        />
                    </Field>
                    <Button size="sm" onClick={savePpn}>Simpan PPN</Button>
                </FieldGroup>
            </CollapsibleCard>

            <CollapsibleCard
                title="Pajak"
                description="NPWP dan mode PPh untuk laporan pajak"
            >
                <FieldGroup>
                    <Field>
                        <FieldLabel>NPWP</FieldLabel>
                        <Input
                            value={pajak.npwp ?? ''}
                            onChange={(e) => setPajak({ ...pajak, npwp: e.target.value || null })}
                            placeholder="12.345.678.9-012.345"
                        />
                    </Field>
                    <Field>
                        <FieldLabel>Mode PPh</FieldLabel>
                        <Select
                            value={pajak.pph_mode}
                            onValueChange={(value) => setPajak({ ...pajak, pph_mode: value as PphMode })}
                            items={pphModeItems}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Pilih mode PPh" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {pphModeItems.map((mode) => (
                                        <SelectItem key={mode.value} value={mode.value}>{mode.label}</SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <FieldDescription>Mode PPh dan NPWP dipakai pada perhitungan pajak di tab Pajak pada Laporan</FieldDescription>
                    </Field>
                    <Button size="sm" onClick={savePajak}>Simpan Pajak</Button>
                </FieldGroup>
            </CollapsibleCard>

            <CollapsibleCard
                title="Pengiriman"
                description="Tarif ongkir untuk pesanan dengan metode Diantar"
                contentClassName="flex flex-col gap-3"
            >
                <FieldGroup>
                    <Field>
                        <FieldLabel>Mode Tarif</FieldLabel>
                        <Select
                            value={delivery.fee_mode}
                            onValueChange={(value) =>
                                setDelivery({ ...delivery, fee_mode: value === 'fixed' ? 'fixed' : 'per_km' })
                            }
                            items={deliveryModeItems}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Pilih mode tarif" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {deliveryModeItems.map((mode) => (
                                        <SelectItem key={mode.value} value={mode.value}>{mode.label}</SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </Field>
                    {delivery.fee_mode === 'per_km' ? (
                        <>
                            <Field>
                                <FieldLabel>Tarif per km (Rp)</FieldLabel>
                                <Input
                                    type="number"
                                    min={0}
                                    max={100000000}
                                    step="any"
                                    value={delivery.fee_per_km}
                                    onChange={(e) => setDelivery({ ...delivery, fee_per_km: Number(e.target.value) })}
                                />
                                <FieldDescription>
                                    Kebiasaan pasar umumnya Rp 3.000–10.000 per km (paling sering Rp 5.000/km).
                                    Ongkir tidak dikenai PPN.
                                </FieldDescription>
                            </Field>
                            <Field>
                                <FieldLabel>Ongkir minimum (Rp)</FieldLabel>
                                <Input
                                    type="number"
                                    min={0}
                                    max={100000000}
                                    step="any"
                                    value={delivery.min_fee}
                                    onChange={(e) => setDelivery({ ...delivery, min_fee: Number(e.target.value) })}
                                />
                                <FieldDescription>Batas bawah ongkir untuk jarak dekat, mis. Rp 10.000. Isi 0 bila tidak dipakai.</FieldDescription>
                            </Field>
                        </>
                    ) : (
                        <Field>
                            <FieldLabel>Tarif tetap (Rp)</FieldLabel>
                            <Input
                                type="number"
                                min={0}
                                max={100000000}
                                step="any"
                                value={delivery.fixed_fee}
                                onChange={(e) => setDelivery({ ...delivery, fixed_fee: Number(e.target.value) })}
                            />
                            <FieldDescription>Ongkir sama untuk semua jarak.</FieldDescription>
                        </Field>
                    )}
                    <Button size="sm" disabled={savingDelivery} onClick={saveDelivery}>
                        {savingDelivery ? <Spinner data-icon="inline-start" /> : null}
                        {savingDelivery ? 'Menyimpan...' : 'Simpan Pengiriman'}
                    </Button>
                </FieldGroup>
                <Separator />
                <FieldGroup>
                    {/* Disabled — automatic distance now uses OpenStreetMap (no API key). */}
                    {/* Re-enable together with fetchMetersGoogle() in DeliveryEstimateService */}
                    {/* and the google_maps_api_key storage path when the Google fallback is */}
                    {/* needed again. */}
                    {/* <Field> */}
                    {/*     <FieldLabel>Google Maps API Key</FieldLabel> */}
                    {/*     <Input */}
                    {/*         type="password" */}
                    {/*         value={mapsApiKey} */}
                    {/*         onChange={(e) => setMapsApiKey(e.target.value)} */}
                    {/*         placeholder={delivery.google_maps_configured ? '•••••••• (terisi, biarkan kosong untuk tetap)' : 'Tempel API key Google Maps'} */}
                    {/*     /> */}
                    {/*     <FieldDescription> */}
                    {/*         Dipakai untuk tombol Hitung Ongkir di pesanan. Buat key di Google Cloud Console dan */}
                    {/*         aktifkan Distance Matrix API untuk project-nya. Tanpa key, jarak tetap bisa diisi manual. */}
                    {/*     </FieldDescription> */}
                    {/* </Field> */}
                    <div className="flex gap-2">
                        {/* <Button size="sm" disabled={savingMapsKey || !mapsApiKey.trim()} onClick={saveMapsKey}> */}
                        {/*     {savingMapsKey ? <Spinner data-icon="inline-start" /> : null} */}
                        {/*     Simpan API Key */}
                        {/* </Button> */}
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={testingDelivery}
                            onClick={testDeliveryConnection}
                        >
                            {testingDelivery ? <Spinner data-icon="inline-start" /> : null}
                            {testingDelivery ? 'Menguji...' : 'Tes Koneksi'}
                        </Button>
                    </div>
                    {/* <p className="text-xs text-muted-foreground"> */}
                    {/*     Status: {delivery.google_maps_configured ? 'Terisi' : 'Belum diisi'} */}
                    {/* </p> */}
                    <p className="text-xs text-muted-foreground">
                        Jarak otomatis dihitung memakai OpenStreetMap — tanpa API key. Tombol ini memeriksa konektivitas layanan.
                    </p>
                </FieldGroup>
            </CollapsibleCard>

            <CollapsibleCard
                title="Peringatan Kedaluwarsa"
                description="Batas hari sebelum kedaluwarsa untuk menandai batch sebagai segera kedaluwarsa"
            >
                <FieldGroup>
                    <Field>
                        <FieldLabel>Batas peringatan (hari)</FieldLabel>
                        <Input
                            type="number"
                            min={1}
                            max={365}
                            value={expirySettings.alert_days}
                            onChange={(e) => setExpirySettings({ alert_days: Number(e.target.value) })}
                        />
                    </Field>
                    <Button size="sm" onClick={saveExpiry}>Simpan kedaluwarsa</Button>
                </FieldGroup>
            </CollapsibleCard>

            <CollapsibleCard title="Google Sheets">
                <FieldGroup>
                    <Field>
                        <FieldLabel>Status koneksi</FieldLabel>
                        <p className="text-sm text-muted-foreground">
                            {integrations.google_connected
                                ? `Terhubung${integrations.google_connected_email ? ` sebagai ${integrations.google_connected_email}` : ''}`
                                : 'Belum terhubung'}
                        </p>
                        <div className="flex flex-wrap gap-2 pt-1">
                            {integrations.google_connected ? (
                                <Button size="sm" variant="outline" disabled={disconnecting} onClick={disconnectGoogle}>
                                    {disconnecting ? <Spinner data-icon="inline-start" /> : null}
                                    Putuskan
                                </Button>
                            ) : (
                                <Button size="sm" disabled={connecting} onClick={connectGoogle}>
                                    {connecting ? <Spinner data-icon="inline-start" /> : null}
                                    Hubungkan Google
                                </Button>
                            )}
                        </div>
                    </Field>
                    <Field orientation="horizontal">
                        <FieldLabel>Sync order aktif</FieldLabel>
                        <Switch
                            checked={integrations.sheets_sync_enabled}
                            onCheckedChange={(checked) =>
                                setIntegrations({ ...integrations, sheets_sync_enabled: checked })
                            }
                        />
                    </Field>
                    {pendingSyncs.length > 0 ? (
                        <p className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Badge variant="secondary">{pendingSyncs.length}</Badge>
                            order menunggu sinkronisasi
                        </p>
                    ) : null}
                    <Field>
                        <FieldLabel>Spreadsheet ID</FieldLabel>
                        <Input
                            value={integrations.sheets_spreadsheet_id ?? ''}
                            onChange={(e) =>
                                setIntegrations({
                                    ...integrations,
                                    sheets_spreadsheet_id: e.target.value || null,
                                })
                            }
                            placeholder="ID dari URL docs.google.com/spreadsheets/d/..."
                        />
                    </Field>
                    <Field>
                        <FieldLabel>Tab Products</FieldLabel>
                        <Input
                            value={integrations.sheets_products_tab}
                            onChange={(e) =>
                                setIntegrations({ ...integrations, sheets_products_tab: e.target.value })
                            }
                        />
                    </Field>
                    <Field>
                        <FieldLabel>Tab Orders</FieldLabel>
                        <Input
                            value={integrations.sheets_orders_tab}
                            onChange={(e) =>
                                setIntegrations({ ...integrations, sheets_orders_tab: e.target.value })
                            }
                        />
                    </Field>
                    <Field>
                        <FieldLabel>Tab Reporting</FieldLabel>
                        <Input
                            value={integrations.sheets_reporting_tab}
                            onChange={(e) =>
                                setIntegrations({ ...integrations, sheets_reporting_tab: e.target.value })
                            }
                        />
                    </Field>
                    <div className="flex flex-wrap gap-2">
                        <Button size="sm" onClick={saveIntegrations}>Simpan integrasi</Button>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={
                                !integrations.google_connected
                                || !integrations.sheets_spreadsheet_id
                                || sheetsImporting
                            }
                            onClick={importFromSheets}
                        >
                            {sheetsImporting ? <Spinner data-icon="inline-start" /> : null}
                            {sheetsImporting ? 'Mengimpor…' : 'Import dari Google Sheets'}
                        </Button>
                        <Button
                            size="sm"
                            disabled={
                                !integrations.google_connected
                                || !integrations.sheets_spreadsheet_id
                                || !integrations.sheets_sync_enabled
                                || syncing
                            }
                            onClick={syncNow}
                        >
                            {syncing ? <Spinner data-icon="inline-start" /> : null}
                            {syncing ? 'Menyinkronkan…' : 'Sinkronkan Sekarang'}
                        </Button>
                    </div>
                </FieldGroup>
            </CollapsibleCard>

            <CollapsibleCard
                title={
                    <span className="flex items-center gap-2">
                        Sheets Sync Gagal
                        {failedSyncs.length > 0 ? (
                            <Badge variant="secondary">{failedSyncs.length}</Badge>
                        ) : null}
                    </span>
                }
                description="Order gagal sinkron ke Google Sheets"
                defaultOpen={failedSyncs.length > 0}
                contentClassName="px-0 pb-0"
            >
                {failedSyncs.map((row) => (
                    <CardRow key={row.id}>
                        <div className="min-w-0">
                            <p className="truncate font-medium">
                                Order #{row.order_id} {row.order?.invoice_no ? `(${row.order.invoice_no})` : ''}
                            </p>
                            <p className="truncate text-xs text-muted-foreground">{row.last_error}</p>
                        </div>
                        <Button size="sm" variant="outline" onClick={() => retrySync(row.id)}>
                            Coba lagi
                        </Button>
                    </CardRow>
                ))}
                {failedSyncs.length === 0 && (
                    <CardContent>
                        <p className="text-sm text-muted-foreground">Tidak ada sync gagal</p>
                    </CardContent>
                )}
            </CollapsibleCard>

            <Card>
                <CardHeader>
                    <CardTitle>Ekspor Data Tenant</CardTitle>
                    <CardDescription>Unduh data bisnis Anda dalam format JSON</CardDescription>
                    <CardAction>
                        <Button size="sm" variant="outline" disabled={exporting} onClick={exportTenantData}>
                            {exporting ? <Spinner data-icon="inline-start" /> : null}
                            {exporting ? 'Mengekspor...' : 'Ekspor data'}
                        </Button>
                    </CardAction>
                </CardHeader>
            </Card>

            <CollapsibleCard
                title="Import dari Excel"
                description="Cadangan: upload file Excel/CSV (tab Products + Orders). Atau pakai Import dari Google Sheets di atas."
                contentClassName="flex flex-col gap-3"
            >
                <Button size="sm" variant="outline" onClick={downloadTemplate}>
                    Unduh template Excel
                </Button>
                <Separator />
                <FieldGroup>
                    <Field>
                        <FieldLabel>File import</FieldLabel>
                        <Input
                            type="file"
                            accept=".csv,.txt,.xlsx,.xls"
                            onChange={(e) => setImportFile(e.target.files?.[0] ?? null)}
                        />
                    </Field>
                </FieldGroup>
                <Button size="sm" disabled={!importFile || importing} onClick={uploadImport}>
                    {importing ? <Spinner data-icon="inline-start" /> : null}
                    {importing ? 'Mengunggah…' : 'Upload & Import'}
                </Button>
                {importResult && (
                    <div className="flex flex-col gap-1 text-sm">
                        {importResult.queued ? (
                            <p>{importResult.message ?? `Queued ${importResult.rows} rows`}</p>
                        ) : (
                            <>
                                <p>Total: <strong>{importResult.total}</strong></p>
                                <p>Berhasil: <strong>{importResult.success}</strong></p>
                                <p>Gagal: <strong>{importResult.failed}</strong></p>
                            </>
                        )}
                    </div>
                )}
            </CollapsibleCard>
        </div>
    );
}
