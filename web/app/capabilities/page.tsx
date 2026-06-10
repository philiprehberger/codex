import Link from 'next/link';
import { listCapabilities } from '@/lib/codex-api';

export const revalidate = 3600;
export const metadata = {
    title: 'Capabilities — Codex',
    description: 'Capability vocabulary with descriptions and project counts.',
};

export default async function CapabilitiesPage() {
    const { data: caps } = await listCapabilities();

    const byCategory = caps.reduce<Record<string, typeof caps>>((acc, c) => {
        (acc[c.category] ??= []).push(c);
        return acc;
    }, {});

    return (
        <div className="space-y-12">
            <header>
                <h1 className="text-3xl font-bold tracking-tight text-(--color-ink)">Capabilities</h1>
                <p className="mt-2 max-w-2xl text-(--color-ink-dim)">
                    The vocabulary that drives the heatmap. Capabilities are
                    buyer-shaped — what prospects say (&quot;I need X&quot;) rather than
                    how the thing was built. {caps.length} entries across {Object.keys(byCategory).length} categories.
                </p>
            </header>

            {Object.entries(byCategory).map(([category, list]) => (
                <section key={category}>
                    <h2 className="text-xl font-semibold text-(--color-ink) mb-3">{category}</h2>
                    <ul className="space-y-4">
                        {list.map((cap) => (
                            <li key={cap.id} className="rounded-lg border border-(--color-paper-dim) bg-(--color-paper) p-4">
                                <div className="flex items-baseline justify-between gap-2">
                                    <Link
                                        href={`/capabilities/${cap.slug}`}
                                        className="text-(--color-ink) font-medium no-underline"
                                    >
                                        {cap.name}
                                    </Link>
                                    <span className="text-xs text-(--color-ink-dim) shrink-0">
                                        {cap.project_count} project{cap.project_count === 1 ? '' : 's'}
                                        {' · '}
                                        {cap.package_count} package{cap.package_count === 1 ? '' : 's'}
                                    </span>
                                </div>
                                <p className="mt-2 text-sm text-(--color-ink-dim) max-w-3xl">{cap.description}</p>
                            </li>
                        ))}
                    </ul>
                </section>
            ))}
        </div>
    );
}
