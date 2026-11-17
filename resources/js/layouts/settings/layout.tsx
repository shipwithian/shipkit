import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAiAssistants } from '@/routes/ai-assistants';
import { edit as editAppearance } from '@/routes/appearance';
import { index as permissionsIndex } from '@/routes/permissions';
import { index as rolesIndex } from '@/routes/roles';
import { index as usersIndex } from '@/routes/users';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: null,
    },
    {
        title: 'Security',
        href: editSecurity(),
        icon: null,
    },
    {
        title: 'AI assistants',
        href: editAiAssistants(),
        icon: null,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: null,
    },
];

/**
 * The access-control pages, each shown only to who may open it, as its policy decides.
 */
function accessControlNavItems(can: {
    viewRoles: boolean;
    viewPermissions: boolean;
    viewUsers: boolean;
}): NavItem[] {
    return [
        ...(can.viewUsers
            ? [{ title: 'Users', href: usersIndex(), icon: null }]
            : []),
        ...(can.viewRoles
            ? [{ title: 'Roles', href: rolesIndex(), icon: null }]
            : []),
        ...(can.viewPermissions
            ? [{ title: 'Permissions', href: permissionsIndex(), icon: null }]
            : []),
    ];
}

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const page = usePage();
    const accessControlItems = accessControlNavItems(page.props.auth.can);
    const isAccountPage = page.component.startsWith('settings/');
    const navItems = [...sidebarNavItems, ...accessControlItems];
    return (
        <div className="px-4 py-6">
            <Heading
                title="Settings"
                description={
                    accessControlItems.length > 0
                        ? 'Manage your account, and who can do what in the app'
                        : 'Manage your profile and account settings'
                }
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label="Settings"
                    >
                        {navItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': isCurrentOrParentUrl(item.href),
                                })}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div
                    className={cn(
                        'min-w-0 flex-1',
                        isAccountPage && 'md:max-w-2xl',
                    )}
                >
                    <section
                        className={cn(
                            'space-y-12',
                            isAccountPage && 'max-w-xl',
                        )}
                    >
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
