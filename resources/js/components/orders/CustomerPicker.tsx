import { useEffect, useRef, useState, type KeyboardEvent as ReactKeyboardEvent } from 'react';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useApi } from '@/lib/ApiProvider';
import { cn } from '@/lib/utils';
import type { Customer, PaginatedResponse } from '@/lib/types';

const EMPTY_STATE_TEXT =
    'Tidak ada hasil — akan disimpan sebagai pelanggan baru saat pesanan disimpan';

interface CustomerPickerProps {
    /** Controlled input value (the typed customer name). */
    value: string;
    onValueChange: (value: string) => void;
    /** Explicit row pick; parent should overwrite name/phone/address with this record. */
    onPick: (customer: Customer) => void;
    /** Fired on blur/Enter without a row pick, with the latest fetched suggestions. */
    onTypedNameCommit?: (name: string, suggestions: Customer[]) => void;
    placeholder?: string;
    disabled?: boolean;
}

/**
 * Member-directory dropdown for the order form. Rendered as an absolutely
 * positioned panel directly under the input (no portal) so it also works
 * inside the mobile bottom Drawer and the desktop right Sheet.
 */
export function CustomerPicker({
    value,
    onValueChange,
    onPick,
    onTypedNameCommit,
    placeholder,
    disabled,
}: CustomerPickerProps) {
    const { api } = useApi();
    const [open, setOpen] = useState(false);
    const [customers, setCustomers] = useState<Customer[]>([]);
    const [loading, setLoading] = useState(false);
    const [highlight, setHighlight] = useState(-1);
    const containerRef = useRef<HTMLDivElement>(null);
    const customersRef = useRef<Customer[]>([]);
    customersRef.current = customers;

    // Debounced directory search; an empty query loads the default
    // recency-ranked set so focusing the field still offers saved pelanggan.
    useEffect(() => {
        if (!open) return;
        let cancelled = false;
        setLoading(true);
        const timer = window.setTimeout(() => {
            api.list<PaginatedResponse<Customer>>('customers', {
                search: value.trim(),
                per_page: '20',
            })
                .then((res) => {
                    if (cancelled) return;
                    setCustomers(res.data);
                    setHighlight(-1);
                })
                .catch(() => {
                    /* suggestions are best-effort */
                })
                .finally(() => {
                    if (!cancelled) setLoading(false);
                });
        }, 300);
        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [api, open, value]);

    // Outside click closes; row picks keep focus via preventDefault on
    // mousedown, so this never races an in-flight selection.
    useEffect(() => {
        if (!open) return;
        function onPointerDown(event: PointerEvent) {
            if (!containerRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        }
        document.addEventListener('pointerdown', onPointerDown);
        return () => document.removeEventListener('pointerdown', onPointerDown);
    }, [open]);

    function pick(customer: Customer) {
        setOpen(false);
        onPick(customer);
    }

    function commitTyped() {
        setOpen(false);
        onTypedNameCommit?.(value, customersRef.current);
    }

    function handleKeyDown(event: ReactKeyboardEvent<HTMLInputElement>) {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            if (!open) {
                setOpen(true);
                return;
            }
            event.preventDefault();
            const delta = event.key === 'ArrowDown' ? 1 : -1;
            setHighlight((current) => {
                if (customers.length === 0) return -1;
                return Math.min(customers.length - 1, Math.max(0, current + delta));
            });
            return;
        }
        if (event.key === 'Enter') {
            if (open && highlight >= 0 && customers[highlight]) {
                event.preventDefault();
                pick(customers[highlight]);
                return;
            }
            commitTyped();
            return;
        }
        if (event.key === 'Escape' && open) {
            // Close only the picker; the surrounding Drawer/Sheet stays open.
            event.stopPropagation();
            setOpen(false);
        }
    }

    function secondaryLine(customer: Customer): string {
        return [customer.phone, customer.address]
            .map((part) => part?.trim())
            .filter(Boolean)
            .join(' · ');
    }

    return (
        <div ref={containerRef} className="relative">
            <Input
                value={value}
                placeholder={placeholder ?? 'Cari atau ketik nama pelanggan…'}
                disabled={disabled}
                role="combobox"
                aria-expanded={open}
                aria-autocomplete="list"
                aria-controls="customer-picker-listbox"
                onChange={(e) => {
                    onValueChange(e.target.value);
                    if (!open) setOpen(true);
                }}
                onFocus={() => setOpen(true)}
                onBlur={() => {
                    if (open) commitTyped();
                    setOpen(false);
                }}
                onKeyDown={handleKeyDown}
            />
            {open && (
                <div
                    id="customer-picker-listbox"
                    role="listbox"
                    aria-busy={loading}
                    className="absolute inset-x-0 top-full z-50 mt-1 max-h-64 overflow-y-auto rounded-3xl bg-popover p-1.5 text-popover-foreground shadow-lg ring-1 ring-foreground/5 dark:ring-foreground/10"
                >
                    {loading && customers.length === 0 ? (
                        <div className="flex items-center gap-2 px-3 py-2 text-sm text-muted-foreground">
                            <Spinner />
                            Memuat…
                        </div>
                    ) : customers.length === 0 ? (
                        <p className="px-3 py-2 text-sm text-muted-foreground">{EMPTY_STATE_TEXT}</p>
                    ) : (
                        customers.map((customer, index) => {
                            const secondary = secondaryLine(customer);
                            return (
                                <button
                                    key={customer.id}
                                    type="button"
                                    role="option"
                                    aria-selected={index === highlight}
                                    className={cn(
                                        'flex w-full cursor-default flex-col gap-0.5 rounded-2xl px-3 py-2 text-left text-sm font-medium outline-hidden select-none',
                                        index === highlight && 'bg-accent text-accent-foreground',
                                    )}
                                    // Keep input focus so blur-commit never races row picks.
                                    onMouseDown={(e) => e.preventDefault()}
                                    onMouseEnter={() => setHighlight(index)}
                                    onClick={() => pick(customer)}
                                >
                                    <span className="truncate">{customer.name}</span>
                                    {secondary && (
                                        <span className="truncate text-xs font-normal text-muted-foreground">
                                            {secondary}
                                        </span>
                                    )}
                                </button>
                            );
                        })
                    )}
                </div>
            )}
        </div>
    );
}
