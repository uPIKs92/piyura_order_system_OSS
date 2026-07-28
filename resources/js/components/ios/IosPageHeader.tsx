import { ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

interface IosPageHeaderProps {
    title: string;
    description?: string;
    action?: React.ReactNode;
    className?: string;
}

export function IosPageHeader({ title, description, action, className }: IosPageHeaderProps) {
    return (
        <div className={cn('flex items-start justify-between gap-3', className)}>
            <div className="min-w-0">
                <h1 className="text-2xl font-bold tracking-tight text-foreground">{title}</h1>
                {description && <p className="mt-1 text-sm text-muted-foreground">{description}</p>}
            </div>
            {action}
        </div>
    );
}
