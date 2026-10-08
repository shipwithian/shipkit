import { Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { CreateRoleDialog } from '@/pages/roles/partials/create-role-dialog';
import { RolesTable } from '@/pages/roles/partials/roles-table';
import type { Auth, RoleListItem } from '@/types';

export default function RolesIndex({ roles }: { roles: RoleListItem[] }) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const canManageRoles = auth.permissions.includes('manage roles resource');
    const canAssignPermissions =
        auth.permissions.includes('assign permissions');

    return (
        <>
            <Head title="Roles" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Roles"
                        description="Create roles and control the permissions assigned to them."
                    />

                    {canManageRoles && <CreateRoleDialog />}
                </div>

                <RolesTable
                    roles={roles}
                    canManageRoles={canManageRoles}
                    canAssignPermissions={canAssignPermissions}
                />
            </div>
        </>
    );
}
