export function SiteFooter({ name }: { name: string }) {
    return (
        <footer className="text-muted-foreground mx-auto w-full max-w-6xl px-6 py-8 text-sm">
            © {new Date().getFullYear()} {name}
        </footer>
    );
}
