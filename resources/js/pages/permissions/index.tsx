import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { ListToolbar } from '@/components/list-toolbar';
import { useListFilters } from '@/hooks/use-list-filters';
import { CreatePermissionDialog } from '@/pages/permissions/partials/create-permission-dialog';
import { PermissionsTable } from '@/pages/permissions/partials/permissions-table';
import { index } from '@/routes/permissions';
import type { PermissionIndexProps } from '@/types';

export default function PermissionsIndex({
    permissions,
    can,
    filters,
}: PermissionIndexProps) {
    const { apply } = useListFilters(filters);

    return (
        <>
            <Head title="Permissions" />

            <div className="flex flex-col gap-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Permissions"
                        description="Manage the abilities that can be assigned to roles."
                    />

                    {can.create && <CreatePermissionDialog />}
                </div>

                <ListToolbar
                    search={filters.search}
                    onSearch={(search) => apply({ search })}
                    placeholder="Search permissions"
                />

                <PermissionsTable
                    permissions={permissions}
                    emptyMessage={
                        filters.search
                            ? 'No permissions match your search.'
                            : 'No permissions have been created.'
                    }
                />
            </div>
        </>
    );
}

PermissionsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Permissions',
            href: index(),
        },
    ],
};
