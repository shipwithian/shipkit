import { Link } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import RolePermissionController from '@/actions/App/Http/Controllers/RolePermissionController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DeleteRoleDialog } from '@/pages/roles/partials/delete-role-dialog';
import { EditRoleDialog } from '@/pages/roles/partials/edit-role-dialog';
import type { RoleListItem } from '@/types';

type Props = {
    roles: RoleListItem[];
    canManageRoles: boolean;
    canAssignPermissions: boolean;
};

export function RolesTable({
    roles,
    canManageRoles,
    canAssignPermissions,
}: Props) {
    return (
        <div className="bg-card overflow-hidden rounded-xl border">
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 border-b text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Role</th>
                            <th className="px-4 py-3 font-medium">
                                Permissions
                            </th>
                            <th className="px-4 py-3 font-medium">Users</th>
                            <th className="px-4 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {roles.map((role) => (
                            <tr key={role.id}>
                                <td className="px-4 py-3">
                                    <div className="flex items-center gap-2 font-medium">
                                        {role.name}
                                        {role.is_protected && (
                                            <Badge variant="secondary">
                                                Protected
                                            </Badge>
                                        )}
                                    </div>
                                </td>
                                <td className="text-muted-foreground px-4 py-3">
                                    {role.permissions_count}
                                </td>
                                <td className="text-muted-foreground px-4 py-3">
                                    {role.users_count}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-2">
                                        {canAssignPermissions && (
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
                                        {canManageRoles &&
                                            !role.is_protected && (
                                                <EditRoleDialog role={role} />
                                            )}
                                        {canManageRoles &&
                                            !role.is_protected && (
                                                <DeleteRoleDialog role={role} />
                                            )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {roles.length === 0 && (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="text-muted-foreground px-4 py-10 text-center"
                                >
                                    No roles have been created.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
