import { Head, usePage } from '@inertiajs/react';
import { CallToAction } from '@/pages/welcome/partials/call-to-action';
import { FeatureGrid } from '@/pages/welcome/partials/feature-grid';
import { Hero } from '@/pages/welcome/partials/hero';
import { SiteFooter } from '@/pages/welcome/partials/site-footer';
import { SiteHeader } from '@/pages/welcome/partials/site-header';

export default function Welcome() {
    const { auth, name } = usePage().props;
    const isAuthenticated = Boolean(auth.user);

    return (
        <>
            <Head title="Welcome" />

            <div className="bg-background text-foreground flex min-h-screen flex-col">
                <SiteHeader name={name} isAuthenticated={isAuthenticated} />
                <main className="flex-1">
                    <Hero isAuthenticated={isAuthenticated} />
                    <FeatureGrid />
                    <CallToAction isAuthenticated={isAuthenticated} />
                </main>
                <SiteFooter name={name} />
            </div>
        </>
    );
}
