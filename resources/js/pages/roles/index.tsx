import { Form, Head, Link, usePage } from '@inertiajs/react';
import { KeyRound, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import AlertError from '@/components/alert-error';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import RolePermissionController from '@/actions/App/Http/Controllers/RolePermissionController';
import type { Auth } from '@/types';

type Role = {
    id: number;
    name: string;
    is_protected: boolean;
    permissions_count: number;
    users_count: number;
};

export default function RolesIndex({ roles }: { roles: Role[] }) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const canManageRoles = auth.permissions.includes('manage roles resource');
    const canAssignPermissions =
        auth.permissions.includes('assign permissions');
    const [creating, setCreating] = useState(false);

    return (
        <>
            <Head title="Roles" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Roles"
                        description="Create roles and control the permissions assigned to them."
                    />

                    {canManageRoles && (
                        <Dialog open={creating} onOpenChange={setCreating}>
                            <DialogTrigger asChild>
                                <Button>
                                    <Plus />
                                    New role
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Create role</DialogTitle>
                                    <DialogDescription>
                                        Add a role, then assign its permissions
                                        from the roles table.
                                    </DialogDescription>
                                </DialogHeader>
                                <Form
                                    {...RoleController.store.form()}
                                    onSuccess={() => setCreating(false)}
                                    resetOnSuccess
                                    className="space-y-4"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="new-role-name">
                                                    Name
                                                </Label>
                                                <Input
                                                    id="new-role-name"
                                                    name="name"
                                                    autoFocus
                                                    required
                                                />
                                                <InputError
                                                    message={errors.name}
                                                />
                                            </div>
                                            <DialogFooter>
                                                <DialogClose asChild>
                                                    <Button
                                                        type="button"
                                                        variant="secondary"
                                                    >
                                                        Cancel
                                                    </Button>
                                                </DialogClose>
                                                <Button disabled={processing}>
                                                    Create role
                                                </Button>
                                            </DialogFooter>
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>

                <div className="bg-card overflow-hidden rounded-xl border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 border-b text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Role
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Permissions
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Users
                                    </th>
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
                                                        <EditRoleDialog
                                                            role={role}
                                                        />
                                                    )}
                                                {canManageRoles &&
                                                    !role.is_protected && (
                                                        <DeleteRoleDialog
                                                            role={role}
                                                        />
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
            </div>
        </>
    );
}

function EditRoleDialog({ role }: { role: Role }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    size="icon"
                    aria-label={`Edit ${role.name}`}
                >
                    <Pencil />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit role</DialogTitle>
                    <DialogDescription>Update the role name.</DialogDescription>
                </DialogHeader>
                <Form
                    {...RoleController.update.form(role)}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`role-name-${role.id}`}>
                                    Name
                                </Label>
                                <Input
                                    id={`role-name-${role.id}`}
                                    name="name"
                                    defaultValue={role.name}
                                    autoFocus
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button disabled={processing}>
                                    Save changes
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteRoleDialog({ role }: { role: Role }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="destructive"
                    size="icon"
                    aria-label={`Delete ${role.name}`}
                >
                    <Trash2 />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete role?</DialogTitle>
                    <DialogDescription>
                        The role can only be deleted when it is not assigned to
                        any users.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...RoleController.destroy.form(role)}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            {errors.role && (
                                <AlertError errors={[errors.role]} />
                            )}
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    Delete role
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
