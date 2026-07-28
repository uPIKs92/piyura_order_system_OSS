import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardRow, CardTitle } from '@/components/ui/card';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Skeleton } from '@/components/ui/skeleton';
import { useApi } from '@/lib/ApiProvider';
import { haptic } from '@/lib/format';
import type { ImportResult, IntegrationSettings, SheetsSyncEntry, PpnSettings } from '@/lib/types';

const emptyIntegrations: IntegrationSettings = {
    sheets_sync_enabled: false,
    sheets_spreadsheet_id: null,
    sheets_products_tab: 'Products',
    sheets_orders_tab: 'Orders',
    sheets_reporting_tab: 'Reporting',
    google_connected: false,
    google_connected_email: null,
};

export default function SettingsOps() {
    const { api } = useApi();
    const [loading, setLoading] = useState(true);
    const [ppn, setPpn] = useState<PpnSettings>({ enabled: true, percentage: 11 });
    const [integrations, setIntegrations] = useState<IntegrationSettings>(emptyIntegrations);
    const [failedSyncs, setFailedSyncs] = useState<SheetsSyncEntry[]>([]);
    const [exporting, setExporting] = useState(false);
    const [importFile, setImportFile] = useState<File | null>(null);
    const [importResult, setImportResult] = useState<ImportResult | null>(null);
    const [importing, setImporting] = useState(false);
    const [sheetsImporting, setSheetsImporting] = useState(false);
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
            anchor.download = `tenant-export-${new Date().toISOString().slice(0, 10)}.json`;
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
            api.request<IntegrationSettings>('/settings/integrations'),
            api.request<SheetsSyncEntry[]>('/sheets-sync?status=failed').catch(() => []),
        ])
            .then(([p, integrationSettings, failed]) => {
                setPpn(p);
                setIntegrations({ ...emptyIntegrations, ...integrationSettings });
                setFailedSyncs(failed);
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
            <Card>
                <CardHeader>
                    <CardTitle>Pengaturan PPN</CardTitle>
                    <CardDescription>Pajak pertambahan nilai untuk order baru</CardDescription>
                </CardHeader>
                <CardContent>
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
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Google Sheets</CardTitle>
                    <CardDescription>Hubungkan akun Google untuk sync dan import langsung, tanpa n8n</CardDescription>
                </CardHeader>
                <CardContent>
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
                        </div>
                    </FieldGroup>
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="border-b">
                    <CardTitle className="flex items-center gap-2">
                        Sheets Sync Gagal
                        {failedSyncs.length > 0 ? (
                            <Badge variant="secondary">{failedSyncs.length}</Badge>
                        ) : null}
                    </CardTitle>
                    <CardDescription>Order gagal sinkron ke Google Sheets</CardDescription>
                </CardHeader>
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
            </Card>

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

            <Card>
                <CardHeader>
                    <CardTitle>Import dari Excel</CardTitle>
                    <CardDescription>
                        Cadangan: upload file Excel/CSV (tab Products + Orders). Atau pakai Import dari Google Sheets di atas.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-3">
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
                </CardContent>
            </Card>
        </div>
    );
}
