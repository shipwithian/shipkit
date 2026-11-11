import { Form, router } from '@inertiajs/react';
import { KeySquare } from 'lucide-react';
import { useEffect, useState } from 'react';
import PersonalAccessTokenController from '@/actions/App/Http/Controllers/Settings/PersonalAccessTokenController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RevokeRow } from '@/pages/settings/partials/revoke-row';
import type { PersonalAccessTokenListItem } from '@/types';

type NewToken = { name: string; secret: string };

/**
 * The secret of a token created on this page, which the server sends once as flash data.
 */
function useNewToken(): [NewToken | null, () => void] {
    const [newToken, setNewToken] = useState<NewToken | null>(null);

    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const token = flash?.personalAccessToken as NewToken | undefined;

            if (token) {
                setNewToken(token);
            }
        });
    }, []);

    return [newToken, () => setNewToken(null)];
}

export function PersonalAccessTokens({
    tokens,
}: {
    tokens: PersonalAccessTokenListItem[];
}) {
    const [newToken, dismissNewToken] = useNewToken();

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Personal access tokens"
                description="Tokens let your scripts and tools use the API as you"
            />

            {newToken && (
                <div
                    className="space-y-2 rounded-lg border p-4"
                    data-test="new-token-secret"
                >
                    <p className="text-sm font-medium">
                        Copy your new token for {newToken.name} now. You won't
                        see it again.
                    </p>
                    <code className="bg-muted block overflow-x-auto rounded p-2 text-xs break-all">
                        {newToken.secret}
                    </code>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={dismissNewToken}
                    >
                        Done
                    </Button>
                </div>
            )}

            <Form
                {...PersonalAccessTokenController.store.form()}
                options={{ preserveScroll: true }}
                resetOnSuccess
                className="flex items-end gap-3"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="grid flex-1 gap-2">
                            <Label htmlFor="token_name">Token name</Label>
                            <Input
                                id="token_name"
                                name="name"
                                placeholder="Deploy script"
                            />
                            <InputError message={errors.name} />
                        </div>
                        <Button disabled={processing}>Create token</Button>
                    </>
                )}
            </Form>

            {tokens.length > 0 ? (
                <div className="border-border overflow-hidden rounded-lg border">
                    {tokens.map((token) => (
                        <RevokeRow
                            key={token.id}
                            icon={KeySquare}
                            title={token.name ?? 'Unnamed token'}
                            details={[
                                `Created ${token.created_at_diff}`,
                                token.last_used_at_diff &&
                                    `Last used ${token.last_used_at_diff}`,
                                token.expires_at_diff &&
                                    `Expires ${token.expires_at_diff}`,
                            ]}
                            confirmTitle="Revoke this token?"
                            confirmDescription="Scripts using it will stop working immediately."
                            actionLabel="Revoke"
                            url={PersonalAccessTokenController.destroy.url(
                                token.id,
                            )}
                        />
                    ))}
                </div>
            ) : (
                <p className="text-muted-foreground text-sm">
                    No personal access tokens yet.
                </p>
            )}
        </div>
    );
}
