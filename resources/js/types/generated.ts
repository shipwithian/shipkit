// Generated from app/Http/Props by `composer types:generate`. Do not edit.

export type AssignablePermission = {
    readonly id: number;
    readonly name: string;
    readonly is_protected: boolean;
};
export type AssignableRole = {
    readonly id: number;
    readonly name: string;
};
export type AuthProps = {
    readonly user: AuthUser | null;
    readonly can: NavigationAbilities;
};
export type AuthUser = {
    readonly id: number;
    readonly name: string;
    readonly email: string;
    readonly email_verified_at: string | null;
    readonly is_protected: boolean;
    readonly created_at: string | null;
    readonly updated_at: string | null;
};
export type ForgotPasswordProps = {
    readonly status: string | null;
};
export type LoginProps = {
    readonly canResetPassword: boolean;
    readonly status: string | null;
};
export type NavigationAbilities = {
    readonly viewRoles: boolean;
    readonly viewPermissions: boolean;
    readonly viewUsers: boolean;
};
export type Pagination = {
    readonly current_page: number;
    readonly last_page: number;
    readonly total: number;
    readonly previous_page_url: string | null;
    readonly next_page_url: string | null;
};
export type PasskeyListItem = {
    readonly id: number;
    readonly name: string;
    readonly authenticator: string | null;
    readonly created_at_diff: string | null;
    readonly last_used_at_diff: string | null;
};
export type PermissionAbilities = {
    readonly update: boolean;
    readonly delete: boolean;
};
export type PermissionIndexAbilities = {
    readonly create: boolean;
};
export type PermissionIndexProps = {
    readonly permissions: PermissionListItem[];
    readonly can: PermissionIndexAbilities;
};
export type PermissionListItem = {
    readonly id: number;
    readonly name: string;
    readonly is_protected: boolean;
    readonly roles_count: number;
    readonly users_count: number;
    readonly can: PermissionAbilities;
};
export type ProfileEditProps = {
    readonly mustVerifyEmail: boolean;
    readonly status: string | null;
};
export type RegisterProps = {
    readonly passwordRules: string;
};
export type ResetPasswordProps = {
    readonly email: string | null;
    readonly token: string;
    readonly passwordRules: string;
};
export type RoleAbilities = {
    readonly update: boolean;
    readonly delete: boolean;
    readonly assignPermissions: boolean;
};
export type RoleIndexAbilities = {
    readonly create: boolean;
};
export type RoleIndexProps = {
    readonly roles: RoleListItem[];
    readonly can: RoleIndexAbilities;
};
export type RoleListItem = {
    readonly id: number;
    readonly name: string;
    readonly is_protected: boolean;
    readonly permissions_count: number;
    readonly users_count: number;
    readonly can: RoleAbilities;
};
export type RolePermissionsEditProps = {
    readonly role: RoleWithPermissionIds;
    readonly permissions: AssignablePermission[];
};
export type RoleWithPermissionIds = {
    readonly id: number;
    readonly name: string;
    readonly is_protected: boolean;
    readonly permission_ids: number[];
};
export type SecurityEditProps = {
    readonly canManageTwoFactor: boolean;
    readonly twoFactorEnabled: boolean;
    readonly requiresConfirmation: boolean;
    readonly canManagePasskeys: boolean;
    readonly passkeys: PasskeyListItem[];
    readonly passwordRules: string;
};
export type SharedProps = {
    readonly name: string;
    readonly auth: AuthProps;
    readonly sidebarOpen: boolean;
};
export type UserRoleIndexProps = {
    readonly users: UserRoleListItem[];
    readonly pagination: Pagination;
    readonly roles: AssignableRole[];
};
export type UserRoleListItem = {
    readonly id: number;
    readonly name: string;
    readonly email: string;
    readonly role_ids: number[];
};
export type VerifyEmailProps = {
    readonly status: string | null;
};
