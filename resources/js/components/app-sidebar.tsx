import { Link, usePage } from '@inertiajs/react';
import { KeyRound, LayoutGrid, ShieldCheck, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as permissionsIndex } from '@/routes/permissions';
import { index as rolesIndex } from '@/routes/roles';
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { can } = usePage().props.auth;
    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        ...(can.viewRoles
            ? [{ title: 'Roles', href: rolesIndex(), icon: ShieldCheck }]
            : []),
        ...(can.viewPermissions
            ? [
                  {
                      title: 'Permissions',
                      href: permissionsIndex(),
                      icon: KeyRound,
                  },
              ]
            : []),
        ...(can.viewUsers
            ? [{ title: 'Users', href: usersIndex(), icon: Users }]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
