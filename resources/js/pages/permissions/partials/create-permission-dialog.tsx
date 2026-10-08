import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import PermissionController from '@/actions/App/Http/Controllers/PermissionController';
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

export function CreatePermissionDialog() {
    const [creating, setCreating] = useState(false);

    return (
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
                        Add a permission that can be assigned to application
                        roles.
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
                                    <Button type="button" variant="secondary">
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
    );
}
