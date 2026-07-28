import { NavLink } from 'react-router-dom';
import { ClipboardList, Package, BarChart3, Users, Settings } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useApi } from '@/lib/ApiProvider';

const tabs = [
    { path: '/orders', label: 'Orders', roles: ['owner', 'staff'], icon: ClipboardList },
    { path: '/catalog', label: 'Catalog', roles: ['owner'], icon: Package },
    { path: '/reports', label: 'Reports', roles: ['owner'], icon: BarChart3 },
    { path: '/users', label: 'Users', roles: ['owner'], icon: Users },
    { path: '/settings', label: 'Settings', roles: ['owner'], icon: Settings },
];

export function IosTabBar() {
    const { user } = useApi();
    const role = user?.role || 'staff';
    const visibleTabs = tabs.filter((t) => t.roles.includes(role));

    return (
        <nav className="fixed inset-x-0 bottom-0 z-50 mx-auto flex max-w-lg justify-around border-t bg-background/80 backdrop-blur-md pb-[env(safe-area-inset-bottom)]">
            {visibleTabs.map((tab) => {
                const Icon = tab.icon;
                return (
                    <NavLink
                        key={tab.path}
                        to={tab.path}
                        className={({ isActive }) =>
                            cn(
                                'flex flex-col items-center px-3 py-2 text-xs transition-colors',
                                isActive ? 'text-primary' : 'text-muted-foreground'
                            )
                        }
                    >
                        <Icon className="mb-0.5 h-6 w-6" />
                        <span>{tab.label}</span>
                    </NavLink>
                );
            })}
        </nav>
    );
}
