import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ChevronDown, MapPin, Truck, X } from 'lucide-react';
import { LocationPickerPanel } from '@/components/LocationPickerPanel';
import { CartLine } from '@/components/orders/CartLine';
import { CustomerPicker } from '@/components/orders/CustomerPicker';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    Drawer,
    DrawerContent,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import { Empty, EmptyDescription, EmptyTitle } from '@/components/ui/empty';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/lib/ApiProvider';
import { formatCurrency, haptic } from '@/lib/format';
import { resolveDeliveryFee } from '@/lib/delivery';
import type { Customer, DeliveryEstimateResult, DeliveryFeeSettings, OrderFormData, ProductUnit } from '@/lib/types';

type OngkirMode = 'otomatis' | 'jarak' | 'tarif';

interface CartReviewContentProps {
    cartItems: { item: OrderFormData['items'][number]; idx: number }[];
    unitMap: Map<number, ProductUnit>;
    total: number;
    form: OrderFormData;
    onFormChange: (patch: Partial<OrderFormData>) => void;
    onUpdateItem: (idx: number, patch: Partial<OrderFormData['items'][number]>) => void;
    onRemoveItem: (idx: number) => void;
    onSave: () => void;
    onSubmit: () => void;
    canSubmit: boolean;
    /** Stored order fee, shown when editing a non-draft order (delivery locked). */
    storedDeliveryFee?: number | string;
    /** Stored order distance; mirrors the server's km fallback when the field is cleared. */
    storedDeliveryKm?: number | string | null;
}

interface CartReviewSheetProps extends CartReviewContentProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function CartReviewContent({
    cartItems,
    unitMap,
    total,
    form,
    onFormChange,
    onUpdateItem,
    onRemoveItem,
    onSave,
    onSubmit,
    canSubmit,
    storedDeliveryFee,
    storedDeliveryKm,
}: CartReviewContentProps) {
    const { api } = useApi();
    const [customerOpen, setCustomerOpen] = useState(false);
    const [deliverySettings, setDeliverySettings] = useState<DeliveryFeeSettings | null>(null);
    const [estimating, setEstimating] = useState(false);
    const [ongkirOpen, setOngkirOpen] = useState(false);
    const [pinCoords, setPinCoords] = useState<{ lat: number; lon: number } | null>(null);
    const [mapOpen, setMapOpen] = useState(false);
    const [reversing, setReversing] = useState(false);
    const [ongkirMode, setOngkirMode] = useState<OngkirMode>(
        () => Number(form.delivery_distance_km) > 0 ? 'jarak'
            : Number(form.delivery_fee_override) > 0 ? 'tarif'
            : 'otomatis',
    );

    useEffect(() => {
        let cancelled = false;
        api.request<DeliveryFeeSettings>('/delivery/settings')
            .then((data) => { if (!cancelled) setDeliverySettings(data); })
            .catch(() => { /* fee preview falls back to override/manual only */ });
        return () => { cancelled = true; };
    }, [api]);

    // Delivery money fields lock on non-draft orders (server ignores them);
    // the address textarea stays editable at any status.
    const deliveryLocked = form.status !== 'draft';
    const isDiantar = form.delivery_method === 'diantar';
    // Server falls back to the stored order km when the payload omits it.
    const effectiveKm = form.delivery_distance_km != null && String(form.delivery_distance_km).trim() !== ''
        ? form.delivery_distance_km
        : (storedDeliveryKm != null && storedDeliveryKm !== '' ? String(storedDeliveryKm) : null);
    const deliveryFee = deliveryLocked
        ? Math.max(0, Number(storedDeliveryFee ?? 0))
        : resolveDeliveryFee(deliverySettings, form.delivery_method, effectiveKm, form.delivery_fee_override);
    const grandTotal = total + deliveryFee;
    const totalLabel = deliveryFee > 0 ? 'Total (termasuk ongkir)' : 'Total';
    const kmText = effectiveKm != null ? `${Number(effectiveKm).toLocaleString('id-ID')} km` : null;

    // Explicit directory pick shows clear intent: overwrite name, phone and
    // address with the record's values. The pin is dropped because the
    // address it reversed no longer matches.
    function pickCustomer(customer: Customer) {
        onFormChange({
            customer_name: customer.name,
            customer_phone: customer.phone ?? '',
            customer_address: customer.address ?? '',
        });
        setPinCoords(null);
    }

    // Free-typed names autofill only when they exactly match (case-insensitive)
    // exactly one fetched suggestion, and only into still-empty fields.
    function commitTypedCustomerName(name: string, suggestions: Customer[]) {
        const query = name.trim().toLowerCase();
        if (!query) return;
        const matches = suggestions.filter(
            (c) => c.name.trim().toLowerCase() === query,
        );
        if (matches.length !== 1) return;
        const match = matches[0];
        const patch: Partial<OrderFormData> = {};
        if ((form.customer_phone ?? '').trim() === '' && (match.phone ?? '').trim() !== '') {
            patch.customer_phone = match.phone ?? '';
        }
        if ((form.customer_address ?? '').trim() === '' && (match.address ?? '').trim() !== '') {
            patch.customer_address = match.address ?? '';
        }
        if (Object.keys(patch).length > 0) onFormChange(patch);
    }

    // Switching modes clears the inactive mode's field so hidden stale
    // values never reach the order payload.
    function changeOngkirMode(mode: OngkirMode) {
        setOngkirMode(mode);
        if (mode === 'jarak') onFormChange({ delivery_fee_override: '' });
        if (mode === 'tarif') onFormChange({ delivery_distance_km: '' });
    }

    async function hitungOngkir() {
        const address = (form.customer_address ?? '').trim();
        if ((!address && !pinCoords) || estimating) return;
        setEstimating(true);
        try {
            const estimate = await api.request<DeliveryEstimateResult>('/delivery/estimate', {
                method: 'POST',
                body: pinCoords ? { lat: pinCoords.lat, lon: pinCoords.lon } : { address },
            });
            onFormChange({
                delivery_distance_km: String(estimate.distance_km),
                delivery_fee_override: '',
            });
            haptic();
            toast.success(`Ongkir ${formatCurrency(estimate.fee_amount)}`);
        } catch (err) {
            const reason = err instanceof Error && err.message && err.message !== 'Request failed'
                ? err.message
                : 'Gagal menghitung ongkir. Isi jarak secara manual.';
            toast.error(reason);
        } finally {
            setEstimating(false);
        }
    }

    async function handlePinConfirm(lat: number, lon: number) {
        setPinCoords({ lat, lon });
        setReversing(true);
        try {
            const result = await api.request<{ address: string }>('/delivery/reverse', {
                method: 'POST',
                body: { lat, lon },
            });
            onFormChange({ customer_address: result.address });
            haptic();
            toast.success('Lokasi tersimpan di Alamat');
        } catch {
            toast.info('Pin tersimpan; teks alamat tidak berubah — bisa diedit manual.');
        } finally {
            setReversing(false);
        }
    }

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div className="flex-1 overflow-y-auto px-4 pt-4 pb-4">
                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-2">
                        {cartItems.length === 0 ? (
                            <Empty className="py-8">
                                <EmptyTitle>Keranjang kosong</EmptyTitle>
                                <EmptyDescription>Pilih produk untuk mulai</EmptyDescription>
                            </Empty>
                        ) : (
                            cartItems.map(({ item, idx }) => {
                                const unit = unitMap.get(Number(item.product_unit_id));
                                if (!unit) return null;
                                return (
                                    <CartLine
                                        key={`${idx}-${item.product_unit_id}`}
                                        unit={unit}
                                        quantity={item.quantity}
                                        discountType={item.discount_type ?? 'fixed'}
                                        discountValue={item.discount_value ?? 0}
                                        onQuantityChange={(quantity) => onUpdateItem(idx, { quantity })}
                                        onDiscountChange={(discountType, discountValue) =>
                                            onUpdateItem(idx, { discount_type: discountType, discount_value: discountValue })
                                        }
                                        onRemove={() => onRemoveItem(idx)}
                                    />
                                );
                            })
                        )}
                    </div>
                    <div className="flex items-center justify-between">
                        <span className="text-sm text-muted-foreground">{totalLabel}</span>
                        <span className="text-base font-semibold">{formatCurrency(grandTotal)}</span>
                    </div>
                    <div className="overflow-hidden rounded-xl border">
                        <Collapsible open={customerOpen} onOpenChange={(open) => setCustomerOpen(open)}>
                            <CollapsibleTrigger className="flex w-full items-center justify-between px-3 py-2.5 text-sm font-medium">
                                {form.customer_name ? (
                                    <span className="truncate">
                                        <span className="text-muted-foreground">Pelanggan: </span>
                                        {form.customer_name}
                                    </span>
                                ) : (
                                    <span className="text-muted-foreground">+ Tambah Pelanggan</span>
                                )}
                                <ChevronDown
                                    data-icon="inline-end"
                                    className={customerOpen ? 'rotate-180' : ''}
                                />
                            </CollapsibleTrigger>
                            <CollapsibleContent className="border-t p-3">
                                <FieldGroup>
                                    <Field>
                                        <FieldLabel>Nama Pelanggan</FieldLabel>
                                        <CustomerPicker
                                            value={form.customer_name}
                                            onValueChange={(value) => onFormChange({ customer_name: value })}
                                            onPick={pickCustomer}
                                            onTypedNameCommit={commitTypedCustomerName}
                                        />
                                    </Field>
                                    <Field>
                                        <FieldLabel>Telepon</FieldLabel>
                                        <Input
                                            value={form.customer_phone}
                                            onChange={(e) => onFormChange({ customer_phone: e.target.value })}
                                        />
                                    </Field>
                                    <Field>
                                        <FieldLabel>Pengiriman</FieldLabel>
                                        <RadioGroup
                                            value={form.delivery_method ?? 'diambil'}
                                            onValueChange={(value) => onFormChange({
                                                delivery_method: value === 'diantar' ? 'diantar' : 'diambil',
                                            })}
                                            disabled={deliveryLocked}
                                            className="grid grid-cols-2 gap-2"
                                        >
                                            <Field orientation="horizontal">
                                                <RadioGroupItem value="diantar" id="delivery-diantar" />
                                                <FieldLabel htmlFor="delivery-diantar">Diantar</FieldLabel>
                                            </Field>
                                            <Field orientation="horizontal">
                                                <RadioGroupItem value="diambil" id="delivery-diambil" />
                                                <FieldLabel htmlFor="delivery-diambil">Diambil</FieldLabel>
                                            </Field>
                                        </RadioGroup>
                                        {deliveryLocked && (
                                            <p className="text-xs text-muted-foreground">
                                                Metode dan ongkir hanya bisa diubah pada pesanan draf.
                                            </p>
                                        )}
                                    </Field>
                                    {isDiantar && (
                                        <>
                                            <Field>
                                                <div className="flex items-center justify-between">
                                                    <FieldLabel>Alamat</FieldLabel>
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            haptic();
                                                            setMapOpen(true);
                                                        }}
                                                        aria-label="Pilih lokasi pelanggan di peta"
                                                        disabled={reversing}
                                                        className={`relative flex items-center rounded-md border bg-background px-2.5 py-1.5 text-xs ${pinCoords ? 'text-primary' : 'text-muted-foreground'}`}
                                                    >
                                                        <MapPin className={`size-4 ${reversing ? 'animate-spin' : ''}`} />
                                                        {pinCoords && (
                                                            <span className="absolute -right-0.5 -top-0.5 size-2 rounded-full bg-primary ring-2 ring-background" />
                                                        )}
                                                    </button>
                                                </div>
                                                <Textarea
                                                    rows={2}
                                                    placeholder="Alamat lengkap tujuan pengiriman"
                                                    value={form.customer_address ?? ''}
                                                    onChange={(e) => {
                                                        onFormChange({ customer_address: e.target.value });
                                                        setPinCoords(null);
                                                    }}
                                                />
                                            </Field>
                                            {!deliveryLocked && !ongkirOpen && (
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        haptic();
                                                        setOngkirOpen(true);
                                                    }}
                                                    className="relative flex w-fit items-center gap-1.5 rounded-md border bg-background px-2.5 py-1.5 text-xs font-medium text-muted-foreground"
                                                >
                                                    <Truck className="size-4" />
                                                    {deliveryFee > 0 ? (
                                                        <span className="tabular-nums">
                                                            Ongkir {formatCurrency(deliveryFee)}{kmText ? ` · ${kmText}` : ''}
                                                        </span>
                                                    ) : (
                                                        'Atur Ongkir'
                                                    )}
                                                    {deliveryFee > 0 && (
                                                        <span className="absolute -right-0.5 -top-0.5 size-2 rounded-full bg-warning ring-2 ring-background" />
                                                    )}
                                                </button>
                                            )}
                                            {!deliveryLocked && ongkirOpen && (
                                                <div className="rounded-lg border bg-muted/40 p-2.5">
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-xs font-medium text-muted-foreground">
                                                            Ongkir
                                                        </span>
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                haptic();
                                                                setOngkirOpen(false);
                                                            }}
                                                            aria-label="Tutup ongkir"
                                                            className="-mr-1 rounded-md p-1 text-muted-foreground/60"
                                                        >
                                                            <X className="size-3.5" />
                                                        </button>
                                                    </div>
                                                    <div className="mt-2 flex flex-col gap-2">
                                                        <Select
                                                            value={ongkirMode}
                                                            onValueChange={(value) => changeOngkirMode(value as OngkirMode)}
                                                        >
                                                            <SelectTrigger size="sm" className="h-8 w-full px-2">
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectGroup>
                                                                    <SelectItem value="otomatis">Otomatis</SelectItem>
                                                                    <SelectItem value="jarak">Jarak manual</SelectItem>
                                                                    <SelectItem value="tarif">Tarif manual</SelectItem>
                                                                </SelectGroup>
                                                            </SelectContent>
                                                        </Select>
                                                        {ongkirMode === 'otomatis' && (
                                                            <>
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="outline"
                                                                    className="w-full"
                                                                    disabled={estimating || (!pinCoords && !(form.customer_address ?? '').trim())}
                                                                    onClick={() => { void hitungOngkir(); }}
                                                                >
                                                                    {estimating ? 'Menghitung…' : 'Hitung Ongkir'}
                                                                </Button>
                                                                {kmText != null && (
                                                                    <p className="text-xs text-muted-foreground">
                                                                        {kmText} · {formatCurrency(deliveryFee)}
                                                                    </p>
                                                                )}
                                                            </>
                                                        )}
                                                        {ongkirMode === 'jarak' && (
                                                            <>
                                                                <Input
                                                                    type="number"
                                                                    min={0}
                                                                    max={999}
                                                                    step={0.01}
                                                                    inputMode="decimal"
                                                                    placeholder="0"
                                                                    value={form.delivery_distance_km ?? ''}
                                                                    onChange={(e) => onFormChange({ delivery_distance_km: e.target.value })}
                                                                    className="h-8 px-2"
                                                                    aria-label="Jarak (km)"
                                                                />
                                                                <p className="text-xs text-muted-foreground">
                                                                    Tarif: {formatCurrency(resolveDeliveryFee(deliverySettings, 'diantar', effectiveKm, ''))}
                                                                </p>
                                                            </>
                                                        )}
                                                        {ongkirMode === 'tarif' && (
                                                            <Input
                                                                type="number"
                                                                min={0}
                                                                placeholder="Rp"
                                                                value={form.delivery_fee_override ?? ''}
                                                                onChange={(e) => onFormChange({ delivery_fee_override: e.target.value })}
                                                                className="h-8 px-2"
                                                                aria-label="Ongkir manual"
                                                            />
                                                        )}
                                                    </div>
                                                </div>
                                            )}
                                            <div className="flex items-center justify-between rounded-lg bg-muted/50 px-3 py-2 text-sm">
                                                <span className="text-muted-foreground">Ongkir</span>
                                                <span className="font-medium tabular-nums">{formatCurrency(deliveryFee)}</span>
                                            </div>
                                        </>
                                    )}
                                    <Field>
                                        <FieldLabel>Tanggal Pesanan</FieldLabel>
                                        <Input
                                            type="date"
                                            value={form.order_date}
                                            onChange={(e) => onFormChange({ order_date: e.target.value })}
                                        />
                                    </Field>
                                    <Field>
                                        <FieldLabel>Catatan</FieldLabel>
                                        <Input
                                            value={form.notes}
                                            onChange={(e) => onFormChange({ notes: e.target.value })}
                                        />
                                    </Field>
                                </FieldGroup>
                            </CollapsibleContent>
                        </Collapsible>
                    </div>
                </div>
            </div>
            <div className="mt-auto flex shrink-0 flex-col gap-2 border-t p-4">
                <div className="flex items-baseline justify-between">
                    <span className="text-sm text-muted-foreground">{totalLabel}</span>
                    <span className="text-lg font-bold tabular-nums">{formatCurrency(grandTotal)}</span>
                </div>
                {form.status === 'draft' ? (
                    <>
                        <Button className="w-full" disabled={!canSubmit} onClick={onSubmit}>
                            Buat Pesanan
                        </Button>
                        <Button variant="ghost" className="w-full text-muted-foreground" onClick={onSave}>
                            Simpan Draft
                        </Button>
                    </>
                ) : (
                    <Button className="w-full" onClick={onSave}>
                        Simpan
                    </Button>
                )}
            </div>
            <LocationPickerPanel
                open={mapOpen}
                onOpenChange={setMapOpen}
                title="Lokasi Pelanggan"
                initialCoords={pinCoords}
                originCoords={deliverySettings?.origin ?? null}
                onConfirm={(lat, lon) => {
                    void handlePinConfirm(lat, lon);
                }}
            />
        </div>
    );
}

export function CartReviewSheet({ open, onOpenChange, ...content }: CartReviewSheetProps) {
    return (
        <Drawer open={open} onOpenChange={onOpenChange} showSwipeHandle>
            <DrawerContent className="max-h-[90vh]">
                <DrawerHeader>
                    <DrawerTitle>Review Pesanan</DrawerTitle>
                </DrawerHeader>
                <CartReviewContent {...content} />
            </DrawerContent>
        </Drawer>
    );
}
