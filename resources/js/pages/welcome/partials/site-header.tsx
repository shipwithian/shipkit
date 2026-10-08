import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, home, login, register } from '@/routes';

type Props = {
    name: string;
    isAuthenticated: boolean;
};

export function SiteHeader({ name, isAuthenticated }: Props) {
    return (
        <header className="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-6 py-5">
            <Link
                href={home()}
                className="flex items-center gap-2 font-semibold"
            >
                <AppLogoIcon className="size-6 fill-current" />
                <span>{name}</span>
            </Link>

            <nav className="flex items-center gap-2">
                {isAuthenticated ? (
                    <Button asChild>
                        <Link href={dashboard()}>Go to dashboard</Link>
                    </Button>
                ) : (
                    <>
                        <Button
                            variant="ghost"
                            className="hidden sm:inline-flex"
                            asChild
                        >
                            <Link href={login()}>Log in</Link>
                        </Button>
                        <Button asChild>
                            <Link href={register()}>Create an account</Link>
                        </Button>
                    </>
                )}
            </nav>
        </header>
    );
}
