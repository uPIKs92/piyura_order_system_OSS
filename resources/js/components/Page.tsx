import { cn } from '@/lib/utils';
import { PAGE_CLASS } from '@/lib/layout';

export function Page({ children, className }: { children: React.ReactNode; className?: string }) {
    return <div className={cn(PAGE_CLASS, className)}>{children}</div>;
}
