import { Head } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useClipboard } from '@/hooks/use-clipboard';
import { ConnectedApps } from '@/pages/settings/partials/connected-apps';
import { edit } from '@/routes/ai-assistants';
import type { AiAssistantsEditProps } from '@/types';

function CopyableUrl({ id, url }: { id: string; url: string }) {
    const [copiedText, copy] = useClipboard();
    const copied = copiedText === url;

    return (
        <div className="flex gap-2">
            <Input id={id} value={url} readOnly className="font-mono" />
            <Button
                type="button"
                variant="outline"
                onClick={() => copy(url)}
                aria-label={copied ? 'Copied' : 'Copy address'}
            >
                {copied ? <Check /> : <Copy />}
            </Button>
        </div>
    );
}

function Step({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-1">
            <h3 className="text-sm font-medium">{title}</h3>
            <div className="text-muted-foreground space-y-1 text-sm">
                {children}
            </div>
        </div>
    );
}

export default function AiAssistants(props: AiAssistantsEditProps) {
    const serverSlug =
        props.serverName
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '') || 'app';

    return (
        <>
            <Head title="AI assistants" />

            <h1 className="sr-only">AI assistants</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Connect your AI assistant"
                    description={`Let Claude or ChatGPT use ${props.serverName} for you. It acts as you, can only do what you can, and asks before it changes anything.`}
                />

                <CopyableUrl id="mcp-url" url={props.mcpUrl} />

                <Step title="Claude (claude.ai and Claude Desktop)">
                    <p>
                        Open Customize, then Connectors. Choose Add, then Add
                        custom connector, paste the address above, and continue.
                        Choose Sign in now, then sign in here and approve.
                    </p>
                </Step>

                <Step title="Claude Code">
                    <p>Run this, then type /mcp in Claude Code to sign in:</p>
                    <code className="bg-muted block rounded px-2 py-1 font-mono text-xs break-all">
                        claude mcp add --transport http {serverSlug}{' '}
                        {props.mcpUrl}
                    </code>
                </Step>

                <Step title="ChatGPT">
                    <p>
                        On chatgpt.com, open Plugins, choose the plus button,
                        then Add custom MCP server. Paste the address above as
                        the Server URL, choose OAuth, and create it. Then sign
                        in here and approve.
                    </p>
                </Step>

                <p className="text-muted-foreground text-sm">
                    Claude.ai and ChatGPT connect from their own servers, so
                    they can reach {props.serverName} only at a public address.
                    Claude Code also works with a copy running on your computer.
                </p>
            </div>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="For anyone without an account"
                    description={`This address answers questions about ${props.serverName} without signing in. It can't see or change anyone's account.`}
                />

                <CopyableUrl id="public-mcp-url" url={props.publicMcpUrl} />
            </div>

            <ConnectedApps apps={props.connectedApps} />
        </>
    );
}

AiAssistants.layout = {
    breadcrumbs: [
        {
            title: 'AI assistants',
            href: edit(),
        },
    ],
};
