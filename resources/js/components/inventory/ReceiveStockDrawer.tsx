import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    Drawer,
    DrawerContent,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import { ReceiveStockPanel } from '@/pages/catalog/ReceiveStockPanel';

const FORM_ID = 'receive-stock-form';

interface ReceiveStockDrawerProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSuccess?: () => void;
}

export function ReceiveStockDrawer({ open, onOpenChange, onSuccess }: ReceiveStockDrawerProps) {
    const [saving, setSaving] = useState(false);
    const [lineCount, setLineCount] = useState(0);

    function handleSuccess() {
        onOpenChange(false);
        onSuccess?.();
    }

    return (
        <Drawer open={open} onOpenChange={onOpenChange} showSwipeHandle>
            <DrawerContent className="max-h-[90vh]">
                <DrawerHeader>
                    <DrawerTitle>Penerimaan supplier</DrawerTitle>
                </DrawerHeader>
                <div className="overflow-y-auto px-4 pb-4">
                    <ReceiveStockPanel
                        formId={FORM_ID}
                        hideSubmit
                        onSuccess={handleSuccess}
                        onSavingChange={setSaving}
                        onLinesChange={setLineCount}
                    />
                </div>
                <DrawerFooter>
                    <div className="flex gap-2">
                        <Button variant="outline" className="flex-1" onClick={() => onOpenChange(false)}>
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            form={FORM_ID}
                            className="flex-1"
                            disabled={saving || lineCount === 0}
                        >
                            {saving ? <Spinner data-icon="inline-start" /> : null}
                            Simpan
                        </Button>
                    </div>
                </DrawerFooter>
            </DrawerContent>
        </Drawer>
    );
}
