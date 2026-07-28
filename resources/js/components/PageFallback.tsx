import { Skeleton } from '@/components/ui/skeleton';
import { Page } from '@/components/Page';
import { APP_FRAME_CLASS } from '@/lib/layout';

export function PageFallback() {
    return (
        <div className="flex min-h-dvh flex-col py-4">
            <div className={APP_FRAME_CLASS}>
                <Page>
                    <Skeleton className="h-8 w-48" />
                    <Skeleton className="h-32 w-full" />
                    <Skeleton className="h-32 w-full" />
                </Page>
            </div>
        </div>
    );
}
