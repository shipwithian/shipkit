import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { OAuthAuthorizeProps } from '@/types';

/**
 * Approve and Deny submit as full-page forms, not Inertia visits, because Passport answers with a
 * redirect to the app's own URL.
 */
function AuthorizationForm({
    action,
    method,
    props,
    children,
}: {
    action: string;
    method: 'POST' | 'DELETE';
    props: OAuthAuthorizeProps;
    children: React.ReactNode;
}) {
    return (
        <form method="post" action={action} className="flex-1">
            <input type="hidden" name="_token" value={props.csrfToken} />
            {method === 'DELETE' && (
                <input type="hidden" name="_method" value="DELETE" />
            )}
            <input type="hidden" name="state" value={props.state} />
            <input type="hidden" name="client_id" value={props.clientId} />
            <input type="hidden" name="auth_token" value={props.authToken} />
            {children}
        </form>
    );
}

export default function OAuthAuthorize(props: OAuthAuthorizeProps) {
    return (
        <>
            <Head title="Authorize application" />

            <div className="space-y-6">
                <p className="text-muted-foreground text-center text-sm">
                    <span className="text-foreground font-medium">
                        {props.clientName}
                    </span>{' '}
                    is asking to use your account{' '}
                    <span className="text-foreground font-medium">
                        {props.accountName} ({props.accountEmail})
                    </span>
                    .
                </p>

                {props.scopes.length > 0 && (
                    <div className="space-y-2">
                        <p className="text-sm font-medium">
                            It will be able to:
                        </p>
                        <ul className="text-muted-foreground list-disc space-y-1 pl-5 text-sm">
                            {props.scopes.map((scope) => (
                                <li key={scope.id}>{scope.description}</li>
                            ))}
                        </ul>
                    </div>
                )}

                <div className="flex gap-3">
                    <AuthorizationForm
                        action={props.denyUrl}
                        method="DELETE"
                        props={props}
                    >
                        <Button
                            type="submit"
                            variant="outline"
                            className="w-full"
                            data-test="deny-authorization-button"
                        >
                            Deny
                        </Button>
                    </AuthorizationForm>

                    <AuthorizationForm
                        action={props.approveUrl}
                        method="POST"
                        props={props}
                    >
                        <Button
                            type="submit"
                            className="w-full"
                            data-test="approve-authorization-button"
                        >
                            Approve
                        </Button>
                    </AuthorizationForm>
                </div>
            </div>
        </>
    );
}

OAuthAuthorize.layout = {
    title: 'Authorize application',
    description:
        'Only approve apps you trust. You can disconnect it at any time from your security settings.',
};
