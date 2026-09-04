import type { ReactNode } from 'react';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useMediaQuery } from '@/lib/useMediaQuery';

interface ResponsiveFormPanelProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: ReactNode;
    description?: ReactNode;
    children: ReactNode;
    footer?: ReactNode;
    desktopWidthClass?: string;
    mobileContentClass?: string;
    /**
     * When false, the popup does not move focus to the first field on open
     * (prevents auto-focusing search inputs / mobile keyboard).
     */
    autoFocusFirstField?: boolean;
}

/**
 * Shared popup container for catalog/users forms: bottom Drawer with swipe
 * handle on mobile, right-side Sheet on desktop (>= 1024px). Only one branch
 * renders; `open` state carries over when the viewport crosses the breakpoint.
 */
export function ResponsiveFormPanel({
    open,
    onOpenChange,
    title,
    description,
    children,
    footer,
    desktopWidthClass = 'data-[side=right]:sm:max-w-md',
    mobileContentClass = 'max-h-[90vh]',
    autoFocusFirstField = true,
}: ResponsiveFormPanelProps) {
    const isDesktop = useMediaQuery('(min-width: 1024px)');

    if (isDesktop) {
        return (
            <Sheet open={open} onOpenChange={onOpenChange}>
                <SheetContent
                    side="right"
                    className={desktopWidthClass}
                    initialFocus={autoFocusFirstField ? undefined : false}
                >
                    <SheetHeader>
                        <SheetTitle>{title}</SheetTitle>
                        {description != null && <SheetDescription>{description}</SheetDescription>}
                    </SheetHeader>
                    <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pb-4">
                        {children}
                    </div>
                    {footer != null && <SheetFooter className="border-t">{footer}</SheetFooter>}
                </SheetContent>
            </Sheet>
        );
    }

    return (
        <Drawer open={open} onOpenChange={onOpenChange} showSwipeHandle>
            <DrawerContent className={mobileContentClass} initialFocus={autoFocusFirstField ? undefined : false}>
                <DrawerHeader>
                    <DrawerTitle>{title}</DrawerTitle>
                    {description != null && <DrawerDescription>{description}</DrawerDescription>}
                </DrawerHeader>
                <div className="flex-1 min-h-0 overflow-y-auto px-4 pb-4">
                    {children}
                </div>
                {footer != null && <DrawerFooter>{footer}</DrawerFooter>}
            </DrawerContent>
        </Drawer>
    );
}
