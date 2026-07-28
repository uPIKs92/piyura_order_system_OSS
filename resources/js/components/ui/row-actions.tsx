import { MoreHorizontal } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export type RowAction = {
    label: string;
    onClick: () => void;
    destructive?: boolean;
};

export function RowActions({ actions }: { actions: RowAction[] }) {
    if (!actions.length) return null;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <Button type="button" variant="ghost" size="icon-sm" aria-label="Aksi baris">
                        <MoreHorizontal />
                    </Button>
                }
            />
            <DropdownMenuContent align="end">
                <DropdownMenuGroup>
                    {actions.map((action) => (
                        <DropdownMenuItem
                            key={action.label}
                            variant={action.destructive ? 'destructive' : 'default'}
                            onClick={action.onClick}
                        >
                            {action.label}
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
