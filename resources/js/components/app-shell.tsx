import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { SidebarProvider } from '@/components/ui/sidebar';

/**
 * The application shell.
 *
 * There was a `variant` prop here with a header-bar alternative, inherited from
 * the starter kit. Nothing rendered it — DevLab has always used the sidebar —
 * and a branch with no caller is not flexibility, it is a second layout nobody
 * is keeping working. The sidebar starts open or closed from the cookie the
 * middleware shares.
 */
export function AppShell({ children }: { children: ReactNode }) {
    const isOpen = usePage().props.sidebarOpen;

    return <SidebarProvider defaultOpen={isOpen}>{children}</SidebarProvider>;
}
