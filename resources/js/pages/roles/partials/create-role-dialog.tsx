import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import InputError from '@/components/input-error';
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

export function CreateRoleDialog() {
    const [creating, setCreating] = useState(false);

    return (
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
                        Add a role, then assign its permissions from the roles
                        table.
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
                                <Label htmlFor="new-role-name">Name</Label>
                                <Input
                                    id="new-role-name"
                                    name="name"
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
                                    Create role
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
