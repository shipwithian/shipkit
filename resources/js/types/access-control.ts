export type Role = {
    id: number;
    name: string;
    is_protected: boolean;
};

export type RoleListItem = Role & {
    permissions_count: number;
    users_count: number;
};

export type RoleWithPermissionIds = Role & {
    permission_ids: number[];
};

export type Permission = {
    id: number;
    name: string;
    is_protected: boolean;
};

export type PermissionListItem = Permission & {
    roles_count: number;
    users_count: number;
};
