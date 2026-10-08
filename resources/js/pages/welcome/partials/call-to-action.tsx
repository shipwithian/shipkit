import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { welcomeContent } from '@/pages/welcome/partials/content';
import { dashboard, register } from '@/routes';

export function CallToAction({
    isAuthenticated,
}: {
    isAuthenticated: boolean;
}) {
    const { heading, description } = welcomeContent.callToAction;

    return (
        <section className="bg-muted/40 border-y">
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-6 py-16 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        {heading}
                    </h2>
                    <p className="text-muted-foreground mt-3 max-w-[56ch] leading-relaxed">
                        {description}
                    </p>
                </div>
                <Button size="lg" className="shrink-0" asChild>
                    {isAuthenticated ? (
                        <Link href={dashboard()}>Go to dashboard</Link>
                    ) : (
                        <Link href={register()}>Create an account</Link>
                    )}
                </Button>
            </div>
        </section>
    );
}
