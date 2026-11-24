import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import RolePermissionController from '@/actions/App/Http/Controllers/AccessControl/RolePermissionController';
import AlertError from '@/components/alert-error';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Label } from '@/components/ui/label';
import { index as rolesIndex } from '@/routes/roles';
import type { RolePermissionsEditProps } from '@/types';

export default function RolePermissions({
    role,
    permissions,
}: RolePermissionsEditProps) {
    const form = useForm({ permissions: [...role.permission_ids] });

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

            <div className="flex flex-col gap-6">
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
                            <Table>
                                <TableHeader className="bg-muted/50">
                                    <TableRow>
                                        <TableHead className="w-16 px-4 py-3 font-medium">
                                            Granted
                                        </TableHead>
                                        <TableHead className="px-4 py-3 font-medium">
                                            Permission
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {permissions.map((permission) => {
                                        const locked =
                                            role.is_protected &&
                                            permission.is_protected;
                                        const checked =
                                            form.data.permissions.includes(
                                                permission.id,
                                            );

                                        return (
                                            <TableRow key={permission.id}>
                                                <TableCell className="px-4 py-3">
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
                                                </TableCell>
                                                <TableCell className="px-4 py-3">
                                                    <Label
                                                        htmlFor={`permission-${permission.id}`}
                                                        className="flex cursor-pointer items-center gap-2 font-medium"
                                                    >
                                                        {permission.name}
                                                        {permission.is_protected && (
                                                            <Badge variant="secondary">
                                                                Protected
                                                            </Badge>
                                                        )}
                                                    </Label>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                    {permissions.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={2}
                                                className="text-muted-foreground px-4 py-10 text-center"
                                            >
                                                No permissions are available.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </div>

                    <div className="flex justify-end">
                        <Button disabled={form.processing || !form.isDirty}>
                            Save permissions
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
