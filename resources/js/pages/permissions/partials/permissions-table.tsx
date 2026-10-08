import { Badge } from '@/components/ui/badge';
import { DeletePermissionDialog } from '@/pages/permissions/partials/delete-permission-dialog';
import { EditPermissionDialog } from '@/pages/permissions/partials/edit-permission-dialog';
import type { PermissionListItem } from '@/types';

export function PermissionsTable({
    permissions,
}: {
    permissions: PermissionListItem[];
}) {
    return (
        <div className="bg-card overflow-hidden rounded-xl border">
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 border-b text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">
                                Permission
                            </th>
                            <th className="px-4 py-3 font-medium">Roles</th>
                            <th className="px-4 py-3 font-medium">
                                Direct users
                            </th>
                            <th className="px-4 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {permissions.map((permission) => (
                            <tr key={permission.id}>
                                <td className="px-4 py-3">
                                    <div className="flex items-center gap-2 font-medium">
                                        {permission.name}
                                        {permission.is_protected && (
                                            <Badge variant="secondary">
                                                Protected
                                            </Badge>
                                        )}
                                    </div>
                                </td>
                                <td className="text-muted-foreground px-4 py-3">
                                    {permission.roles_count}
                                </td>
                                <td className="text-muted-foreground px-4 py-3">
                                    {permission.users_count}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-2">
                                        {!permission.is_protected && (
                                            <EditPermissionDialog
                                                permission={permission}
                                            />
                                        )}
                                        {!permission.is_protected && (
                                            <DeletePermissionDialog
                                                permission={permission}
                                            />
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {permissions.length === 0 && (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="text-muted-foreground px-4 py-10 text-center"
                                >
                                    No permissions have been created.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
