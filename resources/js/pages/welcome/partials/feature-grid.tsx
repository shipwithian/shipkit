import { welcomeContent } from '@/pages/welcome/partials/content';

export function FeatureGrid() {
    const { heading, items } = welcomeContent.features;

    return (
        <section className="mx-auto w-full max-w-6xl px-6 py-20">
            <h2 className="max-w-[24ch] text-2xl font-semibold tracking-tight text-balance sm:text-3xl">
                {heading}
            </h2>
            <dl className="mt-10 grid gap-x-10 gap-y-8 md:grid-cols-3">
                {items.map((item) => (
                    <div key={item.title} className="border-t pt-5">
                        <dt className="font-medium">{item.title}</dt>
                        <dd className="text-muted-foreground mt-2 leading-relaxed">
                            {item.description}
                        </dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}
