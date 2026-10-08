import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { CreatePermissionDialog } from '@/pages/permissions/partials/create-permission-dialog';
import { PermissionsTable } from '@/pages/permissions/partials/permissions-table';
import type { PermissionListItem } from '@/types';

export default function PermissionsIndex({
    permissions,
}: {
    permissions: PermissionListItem[];
}) {
    return (
        <>
            <Head title="Permissions" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Permissions"
                        description="Manage the abilities that can be assigned to roles."
                    />

                    <CreatePermissionDialog />
                </div>

                <PermissionsTable permissions={permissions} />
            </div>
        </>
    );
}
