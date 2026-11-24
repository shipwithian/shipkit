import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { DeletePermissionDialog } from '@/pages/permissions/partials/delete-permission-dialog';
import { EditPermissionDialog } from '@/pages/permissions/partials/edit-permission-dialog';
import type { PermissionListItem } from '@/types';

export function PermissionsTable({
    permissions,
    emptyMessage,
}: {
    permissions: PermissionListItem[];
    emptyMessage: string;
}) {
    return (
        <div className="bg-card overflow-hidden rounded-xl border">
            <div className="overflow-x-auto">
                <Table>
                    <TableHeader className="bg-muted/50">
                        <TableRow>
                            <TableHead className="px-4 py-3 font-medium">
                                Permission
                            </TableHead>
                            <TableHead className="px-4 py-3 font-medium">
                                Roles
                            </TableHead>
                            <TableHead className="px-4 py-3 font-medium">
                                Direct users
                            </TableHead>
                            <TableHead className="px-4 py-3 text-right font-medium">
                                Actions
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {permissions.map((permission) => (
                            <TableRow key={permission.id}>
                                <TableCell className="px-4 py-3">
                                    <div className="flex items-center gap-2 font-medium">
                                        {permission.name}
                                        {permission.is_protected && (
                                            <Badge variant="secondary">
                                                Protected
                                            </Badge>
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell className="text-muted-foreground px-4 py-3">
                                    {permission.roles_count}
                                </TableCell>
                                <TableCell className="text-muted-foreground px-4 py-3">
                                    {permission.users_count}
                                </TableCell>
                                <TableCell className="px-4 py-3">
                                    <div className="flex justify-end gap-2">
                                        {permission.can.update &&
                                            !permission.is_protected && (
                                                <EditPermissionDialog
                                                    permission={permission}
                                                />
                                            )}
                                        {permission.can.delete &&
                                            !permission.is_protected && (
                                                <DeletePermissionDialog
                                                    permission={permission}
                                                />
                                            )}
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                        {permissions.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={4}
                                    className="text-muted-foreground px-4 py-10 text-center"
                                >
                                    {emptyMessage}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}
