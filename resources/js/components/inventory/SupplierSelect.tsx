import { useEffect, useState } from 'react';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useApi } from '@/lib/ApiProvider';
import { listAll } from '@/lib/listAll';
import type { Supplier } from '@/lib/types';

const MANUAL = '__manual__';

interface SupplierSelectProps {
    supplierId: number | null;
    onSupplierIdChange: (id: number | null) => void;
    manualName: string;
    onManualNameChange: (name: string) => void;
}

export function SupplierSelect({
    supplierId,
    onSupplierIdChange,
    manualName,
    onManualNameChange,
}: SupplierSelectProps) {
    const { api } = useApi();
    const [suppliers, setSuppliers] = useState<Supplier[]>([]);

    useEffect(() => {
        let cancelled = false;
        // Endpoint khusus owner: staf (403) atau kegagalan lain → diam,
        // input manual tetap tersedia sebagai fallback.
        // Dropdown needs every supplier; endpoint paginates, so walk bounded pages.
        listAll<Supplier>(api, 'suppliers')
            .then((data) => {
                if (!cancelled) setSuppliers(data);
            })
            .catch(() => {});
        return () => {
            cancelled = true;
        };
    }, [api]);

    return (
        <div className="flex flex-col gap-2">
            <Select
                value={supplierId === null ? MANUAL : String(supplierId)}
                onValueChange={(value) => onSupplierIdChange(value === MANUAL ? null : Number(value))}
            >
                <SelectTrigger className="w-full">
                    <SelectValue placeholder="Pilih supplier" />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectItem value={MANUAL}>— Ketik manual —</SelectItem>
                        {suppliers.map((supplier) => (
                            <SelectItem key={supplier.id} value={String(supplier.id)}>
                                {supplier.name}
                            </SelectItem>
                        ))}
                    </SelectGroup>
                </SelectContent>
            </Select>
            {supplierId === null && (
                <Input
                    value={manualName}
                    onChange={(e) => onManualNameChange(e.target.value)}
                    placeholder="Nama supplier..."
                />
            )}
        </div>
    );
}
