import { notFound } from 'next/navigation';
import Link from 'next/link';
import { CodexApiError, getCapability } from '@/lib/codex-api';

export const revalidate = 3600;

type RouteParams = { slug: string };

export async function generateMetadata({ params }: { params: Promise<RouteParams> }) {
    const { slug } = await params;
    try {
        const { data } = await getCapability(slug);
        return {
            title: `${data.name} — Codex`,
            description: data.description.slice(0, 160),
        };
    } catch {
        return { title: 'Capability not found — Codex' };
    }
}

export default async function CapabilityDetailPage({ params }: { params: Promise<RouteParams> }) {
    const { slug } = await params;
    let cap;
    try {
        const { data } = await getCapability(slug);
        cap = data;
    } catch (e) {
        if (e instanceof CodexApiError && e.status === 404) notFound();
        throw e;
    }

    return (
        <article className="space-y-10">
            <header>
                <div className="text-xs text-(--color-ink-dim)">
                    <Link href="/capabilities" className="no-underline">Capabilities</Link>
                    <span className="mx-2">/</span>
                    <span>{cap.category}</span>
                </div>
                <h1 className="mt-2 text-3xl font-bold tracking-tight text-(--color-ink)">{cap.name}</h1>
                {cap.redirected_from ? (
                    <p className="mt-2 text-sm text-(--color-ink-dim)">
                        Merged from <code className="font-mono">{cap.redirected_from}</code>.
                    </p>
                ) : null}
                <p className="mt-4 max-w-3xl text-(--color-ink-dim)">{cap.description}</p>
                <div className="mt-3 text-sm text-(--color-ink-dim)">
                    {cap.project_count} project{cap.project_count === 1 ? '' : 's'} carry this capability.
                </div>
            </header>

            <section>
                <h2 className="text-lg font-semibold text-(--color-ink) mb-3">Projects</h2>
                <ul className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    {cap.projects.map((p) => (
                        <li key={p.slug}>
                            <Link
                                href={`/projects/${p.slug}`}
                                className="block rounded border border-(--color-paper-dim) bg-(--color-paper) p-3 no-underline hover:border-(--color-accent)"
                            >
                                <div className="flex items-baseline justify-between gap-2">
                                    <div className="text-(--color-ink) font-medium">{p.name}</div>
                                    <span className="text-xs text-(--color-ink-dim) shrink-0">
                                        {p.shipped_date ?? p.status}
                                    </span>
                                </div>
                                <p className="mt-1 text-sm text-(--color-ink-dim)">{p.short_description}</p>
                                {p.client_industry ? (
                                    <div className="mt-1 text-xs text-(--color-ink-dim)">Industry: {p.client_industry}</div>
                                ) : null}
                            </Link>
                        </li>
                    ))}
                </ul>
            </section>

            {cap.aliases.length ? (
                <section>
                    <h2 className="text-lg font-semibold text-(--color-ink) mb-3">Merged-in capabilities</h2>
                    <ul className="flex flex-wrap gap-2 text-sm">
                        {cap.aliases.map((slug) => (
                            <li key={slug} className="rounded bg-(--color-paper-dim) px-2 py-1 text-(--color-ink-dim) font-mono text-xs">
                                {slug}
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}
        </article>
    );
}
