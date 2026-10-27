import { useForm } from '@inertiajs/react';
import { UserCog } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import UserRoleController from '@/actions/App/Http/Controllers/AccessControl/UserRoleController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import type { AssignableRole, UserRoleListItem } from '@/types';

type Props = {
    user: UserRoleListItem;
    roles: AssignableRole[];
};

export function EditUserRolesDialog({ user, roles }: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm({ roles: [...user.role_ids] });

    function openChange(next: boolean): void {
        if (next) {
            form.setData('roles', [...user.role_ids]);
            form.clearErrors();
        }

        setOpen(next);
    }

    function toggleRole(roleId: number, enabled: boolean): void {
        form.setData(
            'roles',
            enabled
                ? [...form.data.roles, roleId]
                : form.data.roles.filter((id) => id !== roleId),
        );
    }

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.submit(UserRoleController.update(user), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={openChange}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <UserCog />
                    Roles
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Roles for {user.name}</DialogTitle>
                    <DialogDescription>
                        Choose the roles this user holds.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-3">
                        {roles.map((role) => (
                            <div
                                key={role.id}
                                className="flex items-center gap-3"
                            >
                                <Checkbox
                                    id={`user-${user.id}-role-${role.id}`}
                                    checked={form.data.roles.includes(role.id)}
                                    onCheckedChange={(value) =>
                                        toggleRole(role.id, value === true)
                                    }
                                />
                                <label
                                    htmlFor={`user-${user.id}-role-${role.id}`}
                                    className="cursor-pointer text-sm font-medium"
                                >
                                    {role.name}
                                </label>
                            </div>
                        ))}
                        {roles.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No roles have been created.
                            </p>
                        )}
                        <InputError message={form.errors.roles} />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>Save roles</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
