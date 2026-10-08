import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { welcomeContent } from '@/pages/welcome/partials/content';
import { dashboard, login, register } from '@/routes';

export function Hero({ isAuthenticated }: { isAuthenticated: boolean }) {
    const { headline, description, screenshot } = welcomeContent.hero;

    return (
        <section className="mx-auto grid w-full max-w-6xl gap-12 px-6 pt-12 pb-20 lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)] lg:items-center lg:pt-20 lg:pb-28">
            <div>
                <h1 className="max-w-[16ch] text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                    {headline}
                </h1>
                <p className="text-muted-foreground mt-6 max-w-[52ch] text-lg leading-relaxed">
                    {description}
                </p>
                <div className="mt-8 flex flex-wrap gap-3">
                    {isAuthenticated ? (
                        <Button size="lg" asChild>
                            <Link href={dashboard()}>Go to dashboard</Link>
                        </Button>
                    ) : (
                        <>
                            <Button size="lg" asChild>
                                <Link href={register()}>Create an account</Link>
                            </Button>
                            <Button size="lg" variant="outline" asChild>
                                <Link href={login()}>Log in</Link>
                            </Button>
                        </>
                    )}
                </div>
            </div>

            <div
                role="img"
                aria-label={screenshot}
                className="border-foreground/25 bg-muted/40 text-muted-foreground flex aspect-[16/10] items-center justify-center rounded-xl border-2 border-dashed p-6 text-center text-sm"
            >
                {screenshot}
            </div>
        </section>
    );
}
