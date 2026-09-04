import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Monitor, Moon, Sun } from 'lucide-react';
import { useTheme } from 'next-themes';
import { CollapsibleCard } from '@/components/CollapsibleCard';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Skeleton } from '@/components/ui/skeleton';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useApi } from '@/lib/ApiProvider';
import {
    applyTenantAppearance,
    basicPaletteOptions,
    isPaletteColor,
    isThemeMode,
    presetPaletteOptions,
    type PaletteColor,
    type PaletteOption,
    type ThemeMode,
} from '@/lib/theme';
import { cn } from '@/lib/utils';

const themeModes: { value: ThemeMode; label: string; icon: typeof Sun }[] = [
    { value: 'system', label: 'Sistem', icon: Monitor },
    { value: 'light', label: 'Terang', icon: Sun },
    { value: 'dark', label: 'Gelap', icon: Moon },
];

function PalettePicker({
    options,
    palette,
    saving,
    onSelect,
}: {
    options: PaletteOption[];
    palette: PaletteColor;
    saving: boolean;
    onSelect: (value: PaletteColor) => void;
}) {
    return (
        <ToggleGroup
            value={[palette]}
            onValueChange={(values) => {
                const next = values[0];
                if (!next || !isPaletteColor(next) || saving) return;
                onSelect(next);
            }}
            variant="outline"
            spacing={2}
            className="grid w-full grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-5"
            disabled={saving}
        >
            {options.map((option) => (
                <ToggleGroupItem
                    key={option.value}
                    value={option.value}
                    aria-label={option.label}
                    className="min-w-0 flex-col gap-1.5 px-2 py-2.5"
                >
                    <span
                        aria-hidden
                        className={cn(
                            'flex size-8 overflow-hidden rounded-lg border border-border',
                            palette === option.value &&
                                'ring-2 ring-ring ring-offset-2 ring-offset-background',
                        )}
                    >
                        <span className="flex-1" style={{ backgroundColor: option.surface }} />
                        <span className="flex-1" style={{ backgroundColor: option.preview }} />
                    </span>
                    <span className="max-w-full truncate text-xs">{option.label}</span>
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}

export function ThemeSettings() {
    const { api, tenant, refreshTenant } = useApi();
    const { theme, setTheme } = useTheme();
    const [palette, setPalette] = useState<PaletteColor>('neutral');
    const [mounted, setMounted] = useState(false);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        const nextPalette = isPaletteColor(tenant?.theme_palette ?? null) ? tenant!.theme_palette! : 'neutral';
        setPalette(nextPalette);
        setMounted(true);
    }, [tenant?.theme_palette]);

    const saveAppearance = useCallback(
        async (nextMode: ThemeMode, nextPalette: PaletteColor) => {
            setSaving(true);
            try {
                const updated = await api.updateTenantAppearance({
                    theme_mode: nextMode,
                    theme_palette: nextPalette,
                });
                applyTenantAppearance(updated, setTheme);
                setPalette(isPaletteColor(updated.theme_palette ?? null) ? updated.theme_palette! : 'neutral');
                await refreshTenant();
            } catch (err) {
                toast.error(err instanceof Error ? err.message : 'Gagal menyimpan tampilan');
            } finally {
                setSaving(false);
            }
        },
        [api, refreshTenant, setTheme],
    );

    if (!mounted) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Preferensi Tampilan</CardTitle>
                    <CardDescription>Atur mode terang/gelap dan palet warna aplikasi.</CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    <Skeleton className="h-10 w-full" />
                    <Skeleton className="h-20 w-full" />
                </CardContent>
            </Card>
        );
    }

    const activeTheme = isThemeMode(theme ?? tenant?.theme_mode ?? null)
        ? ((theme ?? tenant?.theme_mode) as ThemeMode)
        : 'system';

    return (
        <CollapsibleCard
            title="Preferensi Tampilan"
            description="Berlaku untuk semua pengguna bisnis ini — latar, kartu, tombol, navigasi, dan grafik."
        >
            <FieldGroup>
                <Field>
                    <FieldLabel>Mode tampilan</FieldLabel>
                    <FieldDescription>Pilih tampilan terang, gelap, atau ikuti pengaturan perangkat.</FieldDescription>
                    <ToggleGroup
                        value={[activeTheme]}
                        onValueChange={(values) => {
                            const next = values[0];
                            if (!next || !isThemeMode(next) || saving) return;
                            void saveAppearance(next, palette);
                        }}
                        variant="outline"
                        spacing={0}
                        className="w-full"
                        disabled={saving}
                    >
                        {themeModes.map((mode) => {
                            const Icon = mode.icon;
                            return (
                                <ToggleGroupItem
                                    key={mode.value}
                                    value={mode.value}
                                    aria-label={mode.label}
                                    className="flex-1"
                                >
                                    <Icon data-icon="inline-start" />
                                    {mode.label}
                                </ToggleGroupItem>
                            );
                        })}
                    </ToggleGroup>
                </Field>

                <Field>
                    <FieldLabel>Warna dasar</FieldLabel>
                    <FieldDescription>Palet sederhana untuk tampilan sehari-hari.</FieldDescription>
                    <PalettePicker
                        options={basicPaletteOptions}
                        palette={palette}
                        saving={saving}
                        onSelect={(next) => void saveAppearance(activeTheme, next)}
                    />
                </Field>

                <Field>
                    <FieldLabel>Populer sepanjang masa</FieldLabel>
                    <FieldDescription>12 tema teratas dari komunitas shadcnpreset, diurutkan berdasarkan suka.</FieldDescription>
                    <PalettePicker
                        options={presetPaletteOptions}
                        palette={palette}
                        saving={saving}
                        onSelect={(next) => void saveAppearance(activeTheme, next)}
                    />
                </Field>
            </FieldGroup>
        </CollapsibleCard>
    );
}
