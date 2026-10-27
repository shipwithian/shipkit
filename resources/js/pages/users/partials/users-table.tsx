import { Badge } from '@/components/ui/badge';
import { EditUserRolesDialog } from '@/pages/users/partials/edit-user-roles-dialog';
import type { AssignableRole, UserRoleListItem } from '@/types';

type Props = {
    users: UserRoleListItem[];
    roles: AssignableRole[];
};

export function UsersTable({ users, roles }: Props) {
    const roleNames = new Map(roles.map((role) => [role.id, role.name]));

    return (
        <div className="bg-card overflow-hidden rounded-xl border">
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 border-b text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">User</th>
                            <th className="px-4 py-3 font-medium">Roles</th>
                            <th className="px-4 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {users.map((user) => (
                            <tr key={user.id}>
                                <td className="px-4 py-3">
                                    <div className="font-medium">
                                        {user.name}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {user.email}
                                    </div>
                                </td>
                                <td className="px-4 py-3">
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
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end">
                                        <EditUserRolesDialog
                                            user={user}
                                            roles={roles}
                                        />
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {users.length === 0 && (
                            <tr>
                                <td
                                    colSpan={3}
                                    className="text-muted-foreground px-4 py-10 text-center"
                                >
                                    No users found.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
