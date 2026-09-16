import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import RolePermissionController from '@/actions/App/Http/Controllers/RolePermissionController';
import AlertError from '@/components/alert-error';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { index as rolesIndex } from '@/routes/roles';

type Permission = {
    id: number;
    name: string;
    is_protected: boolean;
};

type Role = {
    id: number;
    name: string;
    is_protected: boolean;
    permission_ids: number[];
};

export default function RolePermissions({
    role,
    permissions,
}: {
    role: Role;
    permissions: Permission[];
}) {
    const form = useForm({ permissions: role.permission_ids });

    function togglePermission(permissionId: number, enabled: boolean): void {
        form.setData(
            'permissions',
            enabled
                ? [...form.data.permissions, permissionId]
                : form.data.permissions.filter((id) => id !== permissionId),
        );
    }

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.submit(RolePermissionController.update(role));
    }

    return (
        <>
            <Head title={`${role.name} permissions`} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <Heading
                        title={`${role.name} permissions`}
                        description="Choose the abilities granted to this role."
                    />
                    <Button variant="outline" asChild>
                        <Link href={rolesIndex()}>Back to roles</Link>
                    </Button>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    {Object.keys(form.errors).length > 0 && (
                        <AlertError errors={Object.values(form.errors)} />
                    )}

                    <div className="bg-card overflow-hidden rounded-xl border">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 border-b text-left">
                                    <tr>
                                        <th className="w-16 px-4 py-3 font-medium">
                                            Granted
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Permission
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {permissions.map((permission) => {
                                        const locked =
                                            role.is_protected &&
                                            permission.is_protected;
                                        const checked =
                                            form.data.permissions.includes(
                                                permission.id,
                                            );

                                        return (
                                            <tr key={permission.id}>
                                                <td className="px-4 py-3">
                                                    <Checkbox
                                                        id={`permission-${permission.id}`}
                                                        checked={
                                                            checked || locked
                                                        }
                                                        disabled={locked}
                                                        onCheckedChange={(
                                                            value,
                                                        ) =>
                                                            togglePermission(
                                                                permission.id,
                                                                value === true,
                                                            )
                                                        }
                                                    />
                                                </td>
                                                <td className="px-4 py-3">
                                                    <label
                                                        htmlFor={`permission-${permission.id}`}
                                                        className="flex cursor-pointer items-center gap-2 font-medium"
                                                    >
                                                        {permission.name}
                                                        {permission.is_protected && (
                                                            <Badge variant="secondary">
                                                                Protected
                                                            </Badge>
                                                        )}
                                                    </label>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                    {permissions.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={2}
                                                className="text-muted-foreground px-4 py-10 text-center"
                                            >
                                                No permissions are available.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="flex justify-end">
                        <Button disabled={form.processing}>
                            Save permissions
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
