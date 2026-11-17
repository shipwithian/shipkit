import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { CreateRoleDialog } from '@/pages/roles/partials/create-role-dialog';
import { RolesTable } from '@/pages/roles/partials/roles-table';
import { index } from '@/routes/roles';
import type { RoleIndexProps } from '@/types';

export default function RolesIndex({ roles, can }: RoleIndexProps) {
    return (
        <>
            <Head title="Roles" />

            <div className="flex flex-col gap-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Roles"
                        description="Create roles and control the permissions assigned to them."
                    />

                    {can.create && <CreateRoleDialog />}
                </div>

                <RolesTable roles={roles} />
            </div>
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Roles',
            href: index(),
        },
    ],
};
