import { router } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type Props = {
    icon: LucideIcon;
    title: string;
    details: Array<string | null | false>;
    confirmTitle: string;
    confirmDescription: string;
    actionLabel: string;
    url: string;
};

/**
 * One row of a list of credentials with a confirmed destructive action, such as revoking a token.
 */
export function RevokeRow({
    icon: Icon,
    title,
    details,
    confirmTitle,
    confirmDescription,
    actionLabel,
    url,
}: Props) {
    const [processing, setProcessing] = useState(false);

    const revoke = () => {
        setProcessing(true);
        router.delete(url, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div className="flex items-center justify-between border-b p-4 last:border-b-0">
            <div className="flex items-center gap-4">
                <div className="bg-muted flex h-10 w-10 shrink-0 items-center justify-center rounded-xl">
                    <Icon className="text-muted-foreground h-5 w-5" />
                </div>
                <div className="space-y-1">
                    <p className="font-medium tracking-tight">{title}</p>
                    <p className="text-muted-foreground text-sm">
                        {details.filter(Boolean).join(' / ')}
                    </p>
                </div>
            </div>

            <Dialog>
                <DialogTrigger asChild>
                    <Button variant="outline" size="sm">
                        {actionLabel}
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogTitle>{confirmTitle}</DialogTitle>
                    <DialogDescription>{confirmDescription}</DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            disabled={processing}
                            onClick={revoke}
                        >
                            {actionLabel}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
