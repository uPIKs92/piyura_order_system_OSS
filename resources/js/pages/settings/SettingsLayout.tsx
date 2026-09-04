import { NavLink, Outlet } from 'react-router-dom';
import { cn } from '@/lib/utils';
import { IosPageHeader } from '@/components/ios/IosPageHeader';
import { Page } from '@/components/Page';

const navClass = ({ isActive }: { isActive: boolean }) =>
    cn(
        'inline-flex h-10 flex-1 items-center justify-center rounded-xl px-3 text-sm font-medium whitespace-nowrap transition-colors',
        isActive ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground',
    );

export default function SettingsLayout() {
    return (
        <Page>
            <IosPageHeader title="Pengaturan" />
            <div className="flex w-full flex-wrap gap-2 rounded-2xl bg-muted p-1">
                <NavLink to="/settings" end className={navClass}>
                    Profil
                </NavLink>
                <NavLink to="/settings/appearance" className={navClass}>
                    Tampilan
                </NavLink>
                <NavLink to="/settings/ops" className={navClass}>
                    Operasi
                </NavLink>
                <NavLink to="/settings/payment" className={navClass}>
                    Pembayaran
                </NavLink>
            </div>
            <Outlet />
        </Page>
    );
}
