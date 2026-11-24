import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { EditUserRolesDialog } from '@/pages/users/partials/edit-user-roles-dialog';
import type { AssignableRole, UserRoleListItem } from '@/types';

type Props = {
    users: UserRoleListItem[];
    roles: AssignableRole[];
    emptyMessage: string;
};

export function UsersTable({ users, roles, emptyMessage }: Props) {
    const roleNames = new Map(roles.map((role) => [role.id, role.name]));

    return (
        <div className="bg-card overflow-hidden rounded-xl border">
            <div className="overflow-x-auto">
                <Table>
                    <TableHeader className="bg-muted/50">
                        <TableRow>
                            <TableHead className="px-4 py-3 font-medium">
                                User
                            </TableHead>
                            <TableHead className="px-4 py-3 font-medium">
                                Roles
                            </TableHead>
                            <TableHead className="px-4 py-3 text-right font-medium">
                                Actions
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {users.map((user) => (
                            <TableRow key={user.id}>
                                <TableCell className="px-4 py-3">
                                    <div className="font-medium">
                                        {user.name}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {user.email}
                                    </div>
                                </TableCell>
                                <TableCell className="px-4 py-3">
                                    <div className="flex flex-wrap gap-1">
                                        {user.role_ids.map((roleId) => (
                                            <Badge
                                                key={roleId}
                                                variant="secondary"
                                            >
                                                {roleNames.get(roleId)}
                                            </Badge>
                                        ))}
                                        {user.role_ids.length === 0 && (
                                            <span className="text-muted-foreground">
                                                No roles
                                            </span>
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell className="px-4 py-3">
                                    <div className="flex justify-end">
                                        <EditUserRolesDialog
                                            user={user}
                                            roles={roles}
                                        />
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                        {users.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={3}
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
