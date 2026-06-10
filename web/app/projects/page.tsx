import Link from 'next/link';
import { listProjects } from '@/lib/codex-api';

export const revalidate = 3600;
export const metadata = {
    title: 'Projects — Codex',
    description: 'Every project Philip has built — demos, packages, client engagements.',
};

const TYPE_LABEL: Record<string, string> = {
    demo: 'Demo',
    client: 'Client',
    personal: 'Personal',
    open_source: 'Open source',
    package: 'Package',
};

export default async function ProjectsPage() {
    const { data } = await listProjects({ per_page: '100' });

    const byType = data.reduce<Record<string, typeof data>>((acc, p) => {
        (acc[p.project_type] ??= []).push(p);
        return acc;
    }, {});

    return (
        <div className="space-y-12">
            <header>
                <h1 className="text-3xl font-bold tracking-tight text-(--color-ink)">Projects</h1>
                <p className="mt-2 max-w-2xl text-(--color-ink-dim)">
                    {data.length} projects across demos, packages, client work, and
                    personal sites. Client work is redacted by name; industry and
                    capabilities stay at full fidelity.
                </p>
            </header>

            {Object.entries(byType).map(([type, projects]) => (
                <section key={type}>
                    <h2 className="text-xl font-semibold text-(--color-ink) mb-3">
                        {TYPE_LABEL[type] ?? type}
                        <span className="ml-2 text-sm font-normal text-(--color-ink-dim)">({projects.length})</span>
                    </h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {projects.map((p) => (
                            <Link
                                key={p.id}
                                href={`/projects/${p.slug}`}
                                className="rounded-lg border border-(--color-paper-dim) bg-(--color-paper) p-4 no-underline hover:border-(--color-accent)"
                            >
                                <div className="flex items-baseline justify-between gap-2">
                                    <div className="text-(--color-ink) font-medium">{p.name}</div>
                                    <span className="text-xs text-(--color-ink-dim) shrink-0">
                                        {p.shipped_date ?? p.status}
                                    </span>
                                </div>
                                <p className="mt-1 text-sm text-(--color-ink-dim)">{p.short_description}</p>
                                <div className="mt-3 flex flex-wrap gap-1">
                                    {p.capabilities.slice(0, 5).map((c) => (
                                        <span
                                            key={c.slug}
                                            className={
                                                'rounded px-1.5 py-0.5 text-xs ' +
                                                (c.is_primary
                                                    ? 'bg-(--color-accent) text-white'
                                                    : 'bg-(--color-accent-soft) text-(--color-accent)')
                                            }
                                        >
                                            {c.name}
                                        </span>
                                    ))}
                                    {p.capabilities.length > 5 && (
                                        <span className="text-xs text-(--color-ink-dim) px-1">+{p.capabilities.length - 5}</span>
                                    )}
                                </div>
                                {p.client_industry && (
                                    <div className="mt-2 text-xs text-(--color-ink-dim)">
                                        Industry: {p.client_industry}
                                    </div>
                                )}
                            </Link>
                        ))}
                    </div>
                </section>
            ))}
        </div>
    );
}
