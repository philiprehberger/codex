import { notFound } from 'next/navigation';
import Link from 'next/link';
import { CodexApiError, getProject } from '@/lib/codex-api';

export const revalidate = 3600;

type RouteParams = { slug: string };

export async function generateMetadata({ params }: { params: Promise<RouteParams> }) {
    const { slug } = await params;
    try {
        const { data } = await getProject(slug);
        const description = (data.short_description as string) ?? '';
        const name = (data.name as string) ?? slug;
        return {
            title: `${name} — Codex`,
            description,
            openGraph: {
                title: name,
                description,
                type: 'article',
            },
            twitter: {
                card: 'summary_large_image',
                title: name,
                description,
            },
        };
    } catch {
        return { title: 'Project not found — Codex' };
    }
}

export default async function ProjectDetailPage({ params }: { params: Promise<RouteParams> }) {
    const { slug } = await params;
    let project: Record<string, unknown>;
    try {
        const { data } = await getProject(slug);
        project = data;
    } catch (e) {
        if (e instanceof CodexApiError && e.status === 404) notFound();
        throw e;
    }

    const capabilities = (project.capabilities as Array<{
        slug: string;
        name: string;
        category: string;
        is_primary: boolean;
    }>) ?? [];
    const technologies = (project.technologies as Array<{ slug: string; name: string; category: string; is_primary: boolean }>) ?? [];
    const industries = (project.industries as Array<{ slug: string; name: string }>) ?? [];
    const architectures = (project.architectures as Array<{ slug: string; name: string }>) ?? [];
    const learnings = (project.learnings as Array<{ title: string; description: string }>) ?? [];
    const metrics = project.latest_metrics as
        | { recorded_at: string | null; test_count: number | null; lighthouse: { perf: number | null; a11y: number | null; best: number | null; seo: number | null } }
        | null;

    return (
        <article className="space-y-10">
            <header>
                <div className="flex items-baseline gap-2 text-xs text-(--color-ink-dim)">
                    <Link href="/projects" className="no-underline">Projects</Link>
                    <span>/</span>
                    <span>{(project.project_type as string).replace('_', ' ')}</span>
                </div>
                <h1 className="mt-2 text-3xl font-bold tracking-tight text-(--color-ink)">{project.name as string}</h1>
                <p className="mt-3 max-w-2xl text-(--color-ink-dim)">{project.short_description as string}</p>

                <div className="mt-4 flex flex-wrap gap-4 text-xs text-(--color-ink-dim)">
                    {project.shipped_date ? <span>Shipped {project.shipped_date as string}</span> : null}
                    {project.hours_actual ? <span>{project.hours_actual as number} hours</span> : null}
                    {project.team_size ? <span>{project.team_size as number}-person team</span> : null}
                    {project.client_industry ? <span>Industry: {project.client_industry as string}</span> : null}
                </div>

                <div className="mt-4 flex flex-wrap gap-2">
                    {project.live_url ? (
                        <a href={project.live_url as string} className="rounded bg-(--color-ink) px-3 py-1.5 text-xs text-(--color-paper) no-underline">Live</a>
                    ) : null}
                    {project.repo_url ? (
                        <a href={project.repo_url as string} className="rounded border border-(--color-paper-dim) px-3 py-1.5 text-xs text-(--color-ink) no-underline">GitHub</a>
                    ) : null}
                    {project.docs_url ? (
                        <a href={project.docs_url as string} className="rounded border border-(--color-paper-dim) px-3 py-1.5 text-xs text-(--color-ink) no-underline">Docs</a>
                    ) : null}
                </div>
            </header>

            <section>
                <h2 className="text-lg font-semibold text-(--color-ink) mb-3">Capabilities</h2>
                <ul className="flex flex-wrap gap-2 text-sm">
                    {capabilities.map((c) => (
                        <li key={c.slug}>
                            <Link
                                href={`/capabilities/${c.slug}`}
                                className={
                                    'rounded px-2 py-1 no-underline ' +
                                    (c.is_primary
                                        ? 'bg-(--color-accent) text-white'
                                        : 'bg-(--color-accent-soft) text-(--color-accent)')
                                }
                            >
                                {c.name}
                            </Link>
                        </li>
                    ))}
                </ul>
            </section>

            <section>
                <h2 className="text-lg font-semibold text-(--color-ink) mb-3">Stack</h2>
                <ul className="flex flex-wrap gap-2 text-sm">
                    {technologies.map((t) => (
                        <li
                            key={t.slug}
                            className={
                                'rounded border border-(--color-paper-dim) px-2 py-1 ' +
                                (t.is_primary ? 'bg-(--color-paper-dim) font-medium' : '')
                            }
                        >
                            {t.name}
                        </li>
                    ))}
                </ul>
            </section>

            {project.long_description ? (
                <section>
                    <h2 className="text-lg font-semibold text-(--color-ink) mb-3">Details</h2>
                    <div className="prose">
                        {(project.long_description as string).split('\n\n').map((para, i) => (
                            <p key={i}>{para}</p>
                        ))}
                    </div>
                </section>
            ) : null}

            {metrics ? (
                <section>
                    <h2 className="text-lg font-semibold text-(--color-ink) mb-3">Metrics</h2>
                    <dl className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        {metrics.test_count !== null ? (
                            <div><dt className="text-(--color-ink-dim) text-xs">Tests</dt><dd className="text-xl font-semibold text-(--color-ink)">{metrics.test_count}</dd></div>
                        ) : null}
                        {metrics.lighthouse?.perf !== null ? (
                            <div><dt className="text-(--color-ink-dim) text-xs">Perf</dt><dd className="text-xl font-semibold text-(--color-ink)">{metrics.lighthouse.perf}</dd></div>
                        ) : null}
                        {metrics.lighthouse?.a11y !== null ? (
                            <div><dt className="text-(--color-ink-dim) text-xs">A11y</dt><dd className="text-xl font-semibold text-(--color-ink)">{metrics.lighthouse.a11y}</dd></div>
                        ) : null}
                        {metrics.lighthouse?.seo !== null ? (
                            <div><dt className="text-(--color-ink-dim) text-xs">SEO</dt><dd className="text-xl font-semibold text-(--color-ink)">{metrics.lighthouse.seo}</dd></div>
                        ) : null}
                    </dl>
                </section>
            ) : null}

            {industries.length || architectures.length ? (
                <section className="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                    {industries.length ? (
                        <div>
                            <h3 className="font-semibold text-(--color-ink) mb-2">Industries</h3>
                            <ul className="flex flex-wrap gap-2">
                                {industries.map((i) => <li key={i.slug} className="rounded bg-(--color-paper-dim) px-2 py-0.5 text-(--color-ink-dim) text-xs">{i.name}</li>)}
                            </ul>
                        </div>
                    ) : null}
                    {architectures.length ? (
                        <div>
                            <h3 className="font-semibold text-(--color-ink) mb-2">Architectures</h3>
                            <ul className="flex flex-wrap gap-2">
                                {architectures.map((a) => <li key={a.slug} className="rounded bg-(--color-paper-dim) px-2 py-0.5 text-(--color-ink-dim) text-xs">{a.name}</li>)}
                            </ul>
                        </div>
                    ) : null}
                </section>
            ) : null}

            {learnings.length ? (
                <section>
                    <h2 className="text-lg font-semibold text-(--color-ink) mb-3">What I&apos;d do differently</h2>
                    <ul className="space-y-3 text-sm">
                        {learnings.map((l, i) => (
                            <li key={i} className="rounded border border-(--color-paper-dim) bg-(--color-paper) p-3">
                                <div className="font-medium text-(--color-ink)">{l.title}</div>
                                <p className="text-(--color-ink-dim) mt-1">{l.description}</p>
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}
        </article>
    );
}
