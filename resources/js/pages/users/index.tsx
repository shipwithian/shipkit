import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { UsersPagination } from '@/pages/users/partials/users-pagination';
import { UsersTable } from '@/pages/users/partials/users-table';
import type { UserRoleIndexProps } from '@/types';

export default function UsersIndex({
    users,
    pagination,
    roles,
}: UserRoleIndexProps) {
    return (
        <>
            <Head title="Users" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Users"
                    description="Choose the roles each user holds."
                />

                <UsersTable users={users} roles={roles} />
                <UsersPagination pagination={pagination} />
            </div>
        </>
    );
}
