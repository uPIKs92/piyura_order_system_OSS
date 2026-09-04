import { ResponsiveFormPanel } from '@/components/ResponsiveFormPanel';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Empty, EmptyDescription, EmptyTitle } from '@/components/ui/empty';
import { GroupedList, GroupedListDivider } from '@/components/ui/grouped-list';
import { IosListRow } from '@/components/ios/IosListRow';
import { expiryChipClass } from '@/lib/inventory';
import type { ExpiryAlertBatch, ExpiryAlertsResponse } from '@/lib/types';

interface ExpirySheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    data: ExpiryAlertsResponse | null;
    onWriteOff: (batch: ExpiryAlertBatch) => void;
}

function batchSubtitle(batch: ExpiryAlertBatch): string {
    return `${batch.category_name ? `${batch.category_name} • ` : ''}${batch.satuan}${batch.batch_no ? ` • Batch ${batch.batch_no}` : ''}`;
}

function BatchRow({
    batch,
    alertDays,
    onWriteOff,
    showDivider,
}: {
    batch: ExpiryAlertBatch;
    alertDays: number;
    onWriteOff: (batch: ExpiryAlertBatch) => void;
    showDivider: boolean;
}) {
    return (
        <div>
            {showDivider && <GroupedListDivider />}
            <IosListRow
                title={batch.product_name}
                subtitle={batchSubtitle(batch)}
                trailing={
                    <div className="flex items-center gap-2">
                        <Badge variant="secondary">× {batch.qty}</Badge>
                        <Badge className={expiryChipClass(batch.expired_at, alertDays)}>
                            {batch.days_left < 0 ? 'Kedaluwarsa' : `${batch.days_left} hari`}
                        </Badge>
                        <Button
                            size="sm"
                            variant="outline"
                            className="border-destructive/30 text-destructive hover:bg-destructive/10"
                            onClick={() => onWriteOff(batch)}
                        >
                            Write-off
                        </Button>
                    </div>
                }
            />
        </div>
    );
}

export function ExpirySheet({ open, onOpenChange, data, onWriteOff }: ExpirySheetProps) {
    const expired = data?.expired ?? [];
    const nearExpiry = data?.near_expiry ?? [];
    const alertDays = data?.alert_days ?? 30;
    const isEmpty = expired.length === 0 && nearExpiry.length === 0;

    return (
        <ResponsiveFormPanel
            open={open}
            onOpenChange={onOpenChange}
            title="Peringatan Kedaluwarsa"
            description={
                isEmpty
                    ? 'Tidak ada batch kedaluwarsa.'
                    : `Batch kedaluwarsa dan mendekati batas ${alertDays} hari.`
            }
            mobileContentClass="max-h-[85vh]"
        >
            {isEmpty ? (
                <Empty>
                    <EmptyTitle>Semua batch aman</EmptyTitle>
                    <EmptyDescription>Tidak ada batch kedaluwarsa atau mendekati batas.</EmptyDescription>
                </Empty>
            ) : (
                <div className="flex flex-col gap-4">
                    <section>
                        <h3 className="mb-2 flex items-center gap-2 px-1 text-sm font-medium">
                            Kedaluwarsa
                            <Badge variant="secondary">{expired.length}</Badge>
                        </h3>
                        <GroupedList>
                            {expired.map((batch, index) => (
                                <BatchRow
                                    key={batch.id}
                                    batch={batch}
                                    alertDays={alertDays}
                                    onWriteOff={onWriteOff}
                                    showDivider={index > 0}
                                />
                            ))}
                        </GroupedList>
                    </section>
                    {nearExpiry.length > 0 && (
                        <section>
                            <h3 className="mb-2 flex items-center gap-2 px-1 text-sm font-medium">
                                Segera kedaluwarsa
                                <Badge variant="secondary">{nearExpiry.length}</Badge>
                            </h3>
                            <GroupedList>
                                {nearExpiry.map((batch, index) => (
                                    <BatchRow
                                        key={batch.id}
                                        batch={batch}
                                        alertDays={alertDays}
                                        onWriteOff={onWriteOff}
                                        showDivider={index > 0}
                                    />
                                ))}
                            </GroupedList>
                        </section>
                    )}
                </div>
            )}
        </ResponsiveFormPanel>
    );
}
