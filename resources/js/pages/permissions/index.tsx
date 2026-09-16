import { Form, Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import PermissionController from '@/actions/App/Http/Controllers/PermissionController';
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

type Permission = {
    id: number;
    name: string;
    is_protected: boolean;
    roles_count: number;
    users_count: number;
};

export default function PermissionsIndex({
    permissions,
}: {
    permissions: Permission[];
}) {
    const [creating, setCreating] = useState(false);

    return (
        <>
            <Head title="Permissions" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Permissions"
                        description="Manage the abilities that can be assigned to roles."
                    />

                    <Dialog open={creating} onOpenChange={setCreating}>
                        <DialogTrigger asChild>
                            <Button>
                                <Plus />
                                New permission
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Create permission</DialogTitle>
                                <DialogDescription>
                                    Add a permission that can be assigned to
                                    application roles.
                                </DialogDescription>
                            </DialogHeader>
                            <Form
                                {...PermissionController.store.form()}
                                onSuccess={() => setCreating(false)}
                                resetOnSuccess
                                className="space-y-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="new-permission-name">
                                                Name
                                            </Label>
                                            <Input
                                                id="new-permission-name"
                                                name="name"
                                                autoFocus
                                                required
                                            />
                                            <InputError message={errors.name} />
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
                                                Create permission
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="bg-card overflow-hidden rounded-xl border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 border-b text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Permission
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Roles
                                    </th>
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
            </div>
        </>
    );
}

function EditPermissionDialog({ permission }: { permission: Permission }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    size="icon"
                    aria-label={`Edit ${permission.name}`}
                >
                    <Pencil />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit permission</DialogTitle>
                    <DialogDescription>
                        Update the permission name.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...PermissionController.update.form(permission)}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`permission-name-${permission.id}`}
                                >
                                    Name
                                </Label>
                                <Input
                                    id={`permission-name-${permission.id}`}
                                    name="name"
                                    defaultValue={permission.name}
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

function DeletePermissionDialog({ permission }: { permission: Permission }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="destructive"
                    size="icon"
                    aria-label={`Delete ${permission.name}`}
                >
                    <Trash2 />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete permission?</DialogTitle>
                    <DialogDescription>
                        The permission can only be deleted when it is not
                        assigned to any role or user.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...PermissionController.destroy.form(permission)}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            {errors.permission && (
                                <AlertError errors={[errors.permission]} />
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
                                    Delete permission
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
