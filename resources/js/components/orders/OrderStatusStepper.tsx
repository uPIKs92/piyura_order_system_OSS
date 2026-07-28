import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';
import { getPipelineIndex, ORDER_PIPELINE, STATUS_LABELS } from '@/lib/orderStatus';

interface OrderStatusStepperProps {
    status: string;
    compact?: boolean;
}

export function OrderStatusStepper({ status, compact }: OrderStatusStepperProps) {
    const currentIndex = getPipelineIndex(status);
    const isCancelled = status === 'cancelled';

    return (
        <div className={cn('w-full', compact ? 'py-1' : 'py-2')}>
            {isCancelled ? (
                <p className="text-center text-sm font-medium text-destructive">Pesanan dibatalkan</p>
            ) : (
                <ol className="flex items-start justify-between gap-1">
                    {ORDER_PIPELINE.map((step, index) => {
                        const isComplete = currentIndex > index;
                        const isCurrent = currentIndex === index;

                        return (
                            <li key={step} className="flex min-w-0 flex-1 flex-col items-center gap-1">
                                <div className="flex w-full items-center">
                                    {index > 0 && (
                                        <span
                                            className={cn(
                                                'h-0.5 flex-1',
                                                isComplete || isCurrent ? 'bg-primary' : 'bg-muted',
                                            )}
                                        />
                                    )}
                                    <span
                                        className={cn(
                                            'flex size-6 shrink-0 items-center justify-center rounded-full border text-[10px] font-semibold',
                                            isComplete && 'border-primary bg-primary text-primary-foreground',
                                            isCurrent && 'border-primary bg-background text-primary',
                                            !isComplete && !isCurrent && 'border-muted-foreground/30 text-muted-foreground',
                                        )}
                                    >
                                        {isComplete ? <Check className="size-3" /> : index + 1}
                                    </span>
                                    {index < ORDER_PIPELINE.length - 1 && (
                                        <span
                                            className={cn(
                                                'h-0.5 flex-1',
                                                isComplete ? 'bg-primary' : 'bg-muted',
                                            )}
                                        />
                                    )}
                                </div>
                                <span
                                    className={cn(
                                        'max-w-full truncate text-center text-[10px] leading-tight',
                                        isCurrent ? 'font-semibold text-foreground' : 'text-muted-foreground',
                                    )}
                                >
                                    {STATUS_LABELS[step]}
                                </span>
                            </li>
                        );
                    })}
                </ol>
            )}
        </div>
    );
}
