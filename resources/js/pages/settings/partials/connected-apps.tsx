import { Plug } from 'lucide-react';
import ConnectedAppController from '@/actions/App/Http/Controllers/Settings/ConnectedAppController';
import Heading from '@/components/heading';
import { RevokeRow } from '@/pages/settings/partials/revoke-row';
import type { ConnectedAppListItem } from '@/types';

export function ConnectedApps({ apps }: { apps: ConnectedAppListItem[] }) {
    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Connected apps"
                description="Apps you allowed to use your account"
            />

            {apps.length > 0 ? (
                <div className="border-border overflow-hidden rounded-lg border">
                    {apps.map((app) => (
                        <RevokeRow
                            key={app.id}
                            icon={Plug}
                            title={app.name}
                            details={[
                                `Approved ${app.approved_at_diff}`,
                                app.last_used_at_diff &&
                                    `Last used ${app.last_used_at_diff}`,
                            ]}
                            confirmTitle={`Disconnect ${app.name}?`}
                            confirmDescription="It will lose access to your account immediately. Your other apps keep working."
                            actionLabel="Disconnect"
                            url={ConnectedAppController.destroy.url(app.id)}
                        />
                    ))}
                </div>
            ) : (
                <p className="text-muted-foreground text-sm">
                    You haven't connected any apps.
                </p>
            )}
        </div>
    );
}
