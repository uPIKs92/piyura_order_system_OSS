import { useEffect, useRef } from 'react';
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { LogOut } from 'lucide-react';
import { Toaster } from '@/components/ui/sonner';
import { DesktopSidebar, MobileTabBar } from '@/components/AppNav';
import { useApi } from '@/lib/ApiProvider';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { APP_FRAME_CLASS } from '@/lib/layout';
import { safeLogoUrl } from '@/lib/branding';
import { InventoryAlertButton } from '@/components/inventory/InventoryAlertButton';

export function AppShell() {
    const { user, tenant, loading, logout } = useApi();
    const location = useLocation();
    const mainRef = useRef<HTMLElement>(null);

    useEffect(() => {
        mainRef.current?.scrollTo(0, 0);
    }, [location.pathname]);

    if (loading) {
        return (
            <div className={`${APP_FRAME_CLASS} flex min-h-dvh flex-col gap-4 py-4`}>
                <Skeleton className="h-8 w-48" />
                <Skeleton className="h-32 w-full" />
                <Skeleton className="h-32 w-full" />
            </div>
        );
    }

    if (!user) {
        return <Navigate to="/login" replace />;
    }

    const tenantInitial = tenant?.name?.charAt(0)?.toUpperCase() ?? '?';
    const tenantLogoUrl = safeLogoUrl(tenant?.logo_url);

    return (
        <div className="flex h-dvh overflow-hidden bg-background">
            <DesktopSidebar />

            <div className="flex h-dvh min-w-0 flex-1 flex-col overflow-hidden">
                <header className="safe-top shrink-0 border-b lg:hidden">
                    <div className={`${APP_FRAME_CLASS} flex w-full items-center justify-between gap-3 py-3`}>
                        <div className="flex min-w-0 flex-1 items-center gap-2.5">
                            <Avatar className="size-9 rounded-lg">
                                {tenantLogoUrl ? (
                                    <AvatarImage src={tenantLogoUrl} alt={tenant.name} />
                                ) : null}
                                <AvatarFallback className="rounded-lg">{tenantInitial}</AvatarFallback>
                            </Avatar>
                            <div className="min-w-0">
                                <p className="truncate text-sm font-semibold">{tenant?.name ?? 'Bisnis'}</p>
                                {tenant?.tagline ? (
                                    <p className="truncate text-xs text-muted-foreground">{tenant.tagline}</p>
                                ) : (
                                    <p className="text-xs capitalize text-muted-foreground">{user.role}</p>
                                )}
                            </div>
                        </div>
                        <div className="flex shrink-0 items-center gap-1">
                            {user.role === 'owner' && <InventoryAlertButton compact />}
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                onClick={() => logout()}
                                className="text-destructive"
                                aria-label="Keluar"
                            >
                                <LogOut />
                            </Button>
                        </div>
                    </div>
                </header>

                <main
                    ref={mainRef}
                    className="w-full min-h-0 min-w-0 flex-1 overflow-x-hidden overflow-y-auto py-4 pb-28 lg:pb-8"
                >
                    <div className={APP_FRAME_CLASS}>
                        <Outlet />
                    </div>
                </main>

                <MobileTabBar />
                <Toaster position="top-center" duration={2500} richColors />
            </div>
        </div>
    );
}
