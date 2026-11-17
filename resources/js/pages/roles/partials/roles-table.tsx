import { Link } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import RolePermissionController from '@/actions/App/Http/Controllers/AccessControl/RolePermissionController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { DeleteRoleDialog } from '@/pages/roles/partials/delete-role-dialog';
import { EditRoleDialog } from '@/pages/roles/partials/edit-role-dialog';
import type { RoleListItem } from '@/types';

type Props = {
    roles: RoleListItem[];
};

export function RolesTable({ roles }: Props) {
    return (
        <div className="bg-card overflow-hidden rounded-xl border">
            <div className="overflow-x-auto">
                <Table>
                    <TableHeader className="bg-muted/50">
                        <TableRow>
                            <TableHead className="px-4 py-3 font-medium">
                                Role
                            </TableHead>
                            <TableHead className="px-4 py-3 font-medium">
                                Permissions
                            </TableHead>
                            <TableHead className="px-4 py-3 font-medium">
                                Users
                            </TableHead>
                            <TableHead className="px-4 py-3 text-right font-medium">
                                Actions
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {roles.map((role) => (
                            <TableRow key={role.id}>
                                <TableCell className="px-4 py-3">
                                    <div className="flex items-center gap-2 font-medium">
                                        {role.name}
                                        {role.is_protected && (
                                            <Badge variant="secondary">
                                                Protected
                                            </Badge>
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell className="text-muted-foreground px-4 py-3">
                                    {role.permissions_count}
                                </TableCell>
                                <TableCell className="text-muted-foreground px-4 py-3">
                                    {role.users_count}
                                </TableCell>
                                <TableCell className="px-4 py-3">
                                    <div className="flex justify-end gap-2">
                                        {role.can.assignPermissions && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={RolePermissionController.edit(
                                                        role,
                                                    )}
                                                >
                                                    <KeyRound />
                                                    Permissions
                                                </Link>
                                            </Button>
                                        )}
                                        {role.can.update &&
                                            !role.is_protected && (
                                                <EditRoleDialog role={role} />
                                            )}
                                        {role.can.delete &&
                                            !role.is_protected && (
                                                <DeleteRoleDialog role={role} />
                                            )}
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                        {roles.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={4}
                                    className="text-muted-foreground px-4 py-10 text-center"
                                >
                                    No roles have been created.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}
