/**
 * The year shown in the footer, read once when the page loads rather than on every render.
 */
const currentYear = new Date().getFullYear();

export function SiteFooter({ name }: { name: string }) {
    return (
        <footer className="text-muted-foreground mx-auto w-full max-w-6xl px-6 py-8 text-sm">
            © {currentYear} {name}
        </footer>
    );
}
