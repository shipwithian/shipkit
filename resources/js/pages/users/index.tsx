import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { ListFilter } from '@/components/list-filter';
import { ListPagination } from '@/components/list-pagination';
import { ListToolbar } from '@/components/list-toolbar';
import { useListFilters } from '@/hooks/use-list-filters';
import { UsersTable } from '@/pages/users/partials/users-table';
import { index } from '@/routes/users';
import type { UserRoleIndexProps } from '@/types';

export default function UsersIndex({
    users,
    pagination,
    roles,
    filters,
}: UserRoleIndexProps) {
    const { apply } = useListFilters(filters);
    const filtered = filters.search !== null || filters.role !== null;

    return (
        <>
            <Head title="Users" />

            <div className="flex flex-col gap-6">
                <Heading
                    title="Users"
                    description="Choose the roles each user holds."
                />

                <ListToolbar
                    search={filters.search}
                    onSearch={(search) => apply({ search })}
                    placeholder="Search by name or email"
                >
                    <ListFilter
                        value={filters.role}
                        onChange={(role) =>
                            apply({ role: role === null ? null : Number(role) })
                        }
                        allLabel="All roles"
                        options={roles.map((role) => ({
                            value: role.id,
                            label: role.name,
                        }))}
                    />
                </ListToolbar>

                <UsersTable
                    users={users}
                    roles={roles}
                    emptyMessage={
                        filtered
                            ? 'No users match these filters.'
                            : 'No users found.'
                    }
                />
                <ListPagination pagination={pagination} noun="users" />
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: index(),
        },
    ],
};
