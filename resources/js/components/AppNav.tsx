import { NavLink } from 'react-router-dom';
import {
    BarChart3,
    ClipboardList,
    LogOut,
    Package,
    Settings,
    Users,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { APP_FRAME_CLASS } from '@/lib/layout';
import { useApi } from '@/lib/ApiProvider';
import { safeLogoUrl } from '@/lib/branding';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { InventoryAlertButton } from '@/components/inventory/InventoryAlertButton';

type TabConfig = {
    path: string;
    label: string;
    roles: readonly ('owner' | 'staff')[];
    icon: typeof ClipboardList;
    primary?: boolean;
};

const tabs: TabConfig[] = [
    { path: '/orders', label: 'Pesanan', roles: ['owner', 'staff'], icon: ClipboardList, primary: true },
    { path: '/catalog', label: 'Katalog', roles: ['owner'], icon: Package },
    { path: '/reports', label: 'Laporan', roles: ['owner'], icon: BarChart3 },
    { path: '/users', label: 'Pengguna', roles: ['owner'], icon: Users },
    { path: '/settings', label: 'Pengaturan', roles: ['owner'], icon: Settings },
];

function navClass(isActive: boolean, compact = false) {
    return cn(
        'flex items-center gap-3 rounded-xl text-sm font-medium transition-colors',
        compact ? 'flex-col px-2 py-2 text-[11px]' : 'px-3 py-2.5',
        isActive
            ? 'bg-primary text-primary-foreground'
            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
    );
}

function splitMobileTabs(visibleTabs: TabConfig[]) {
    const primary = visibleTabs.find((tab) => tab.primary);
    const secondary = visibleTabs.filter((tab) => !tab.primary);
    const mid = Math.ceil(secondary.length / 2);

    return {
        primary,
        left: secondary.slice(0, mid),
        right: secondary.slice(mid),
    };
}

function MobileTabItem({ tab }: { tab: TabConfig }) {
    const Icon = tab.icon;

    return (
        <NavLink
            to={tab.path}
            className={({ isActive }) =>
                cn(
                    'flex min-w-0 flex-1 flex-col items-center gap-0.5 px-1 py-2 text-[10px] font-medium transition-colors',
                    isActive ? 'text-foreground' : 'text-muted-foreground',
                )
            }
        >
            {({ isActive }) => (
                <>
                    <span
                        className={cn(
                            'flex size-8 items-center justify-center rounded-full',
                            isActive && 'bg-muted text-foreground',
                        )}
                    >
                        <Icon />
                    </span>
                    <span className="max-w-full truncate">{tab.label}</span>
                </>
            )}
        </NavLink>
    );
}

function MobileHeroTab({ tab }: { tab: TabConfig }) {
    const Icon = tab.icon;

    return (
        <NavLink
            to={tab.path}
            aria-label={tab.label}
            className={({ isActive }) =>
                cn(
                    'flex flex-col items-center gap-1',
                    isActive ? 'text-primary' : 'text-muted-foreground',
                )
            }
        >
            {({ isActive }) => (
                <>
                    <span
                        className={cn(
                            'flex size-14 -translate-y-4 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg ring-4 ring-background transition-transform',
                            isActive && 'scale-105',
                        )}
                    >
                        <Icon />
                    </span>
                    <span className="text-[10px] font-semibold">{tab.label}</span>
                </>
            )}
        </NavLink>
    );
}

export function MobileTabBar() {
    const { user } = useApi();
    const role = user?.role || 'staff';
    const visibleTabs = tabs.filter((tab) => tab.roles.includes(role));
    const { primary, left, right } = splitMobileTabs(visibleTabs);

    return (
        <nav
            aria-label="Navigasi utama"
            className="fixed inset-x-0 bottom-0 z-50 border-t bg-background/90 backdrop-blur-md pb-[env(safe-area-inset-bottom)] lg:hidden"
        >
            <div className={`${APP_FRAME_CLASS} flex w-full items-end`}>
                <div className="flex min-h-16 flex-1 items-end justify-evenly">
                    {left.map((tab) => (
                        <MobileTabItem key={tab.path} tab={tab} />
                    ))}
                </div>

                <div className="flex w-[4.5rem] shrink-0 justify-center pb-1">
                    {primary ? <MobileHeroTab tab={primary} /> : null}
                </div>

                <div className="flex min-h-16 flex-1 items-end justify-evenly">
                    {right.map((tab) => (
                        <MobileTabItem key={tab.path} tab={tab} />
                    ))}
                </div>
            </div>
        </nav>
    );
}

export function DesktopSidebar() {
    const { user, tenant, logout } = useApi();
    const role = user?.role || 'staff';
    const visibleTabs = tabs.filter((tab) => tab.roles.includes(role));
    const tenantInitial = tenant?.name?.charAt(0)?.toUpperCase() ?? '?';
    const tenantLogoUrl = safeLogoUrl(tenant?.logo_url);

    return (
        <aside className="hidden h-dvh w-60 shrink-0 flex-col border-r bg-card lg:flex">
            <div className="border-b px-4 py-5">
                <div className="flex items-center justify-between gap-3">
                    <div className="flex min-w-0 flex-1 items-center gap-3">
                        <Avatar className="size-10 rounded-lg">
                            {tenantLogoUrl ? (
                                <AvatarImage src={tenantLogoUrl} alt={tenant.name} />
                            ) : null}
                            <AvatarFallback className="rounded-lg">{tenantInitial}</AvatarFallback>
                        </Avatar>
                        <div className="min-w-0">
                            <p className="truncate text-sm font-semibold">{tenant?.name ?? 'Order Tracker'}</p>
                            <p className="truncate text-xs text-muted-foreground">{user?.name}</p>
                        </div>
                    </div>
                    {role === 'owner' && <InventoryAlertButton />}
                </div>
            </div>
            <nav aria-label="Navigasi utama" className="flex flex-1 flex-col gap-1 p-3">
                {visibleTabs.map((tab) => {
                    const Icon = tab.icon;
                    return (
                        <NavLink key={tab.path} to={tab.path} className={({ isActive }) => navClass(isActive)}>
                            <Icon />
                            <span>{tab.label}</span>
                        </NavLink>
                    );
                })}
            </nav>
            <div className="flex flex-col gap-2 border-t p-3">
                <Button variant="ghost" size="sm" className="justify-start text-destructive" onClick={() => logout()}>
                    <LogOut data-icon="inline-start" />
                    Keluar
                </Button>
            </div>
        </aside>
    );
}
