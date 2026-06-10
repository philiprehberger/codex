import Link from 'next/link';
import { Heatmap } from '@/components/Heatmap';
import { getHeatmap, getGapReport, listPackages } from '@/lib/codex-api';

export const revalidate = 3600;

export default async function HomePage() {
    const [{ data: heatmap }, { data: gaps }, { data: packages }] = await Promise.all([
        getHeatmap(),
        getGapReport(),
        listPackages({ per_page: '1000' }),
    ]);

    const packageCount = packages.length;
    const languages = new Set(packages.map((p) => p.language));

    const topCapabilities = [...heatmap.capabilities]
        .sort((a, b) => b.count - a.count)
        .slice(0, 3);

    return (
        <div className="space-y-16">
            <section>
                <h1 className="text-4xl font-bold tracking-tight text-(--color-ink)">
                    The codex of every project, capability, and gap.
                </h1>
                <p className="mt-4 max-w-2xl text-lg text-(--color-ink-dim)">
                    Every project Philip has built — demos, packages, client engagements —
                    catalogued and tagged by capability, technology, industry, and
                    architecture. Below: a heatmap of where the work concentrates and a
                    gap report of what&apos;s missing. The dashboard you&apos;re looking
                    at is itself one of the entries.
                </p>
                <div className="mt-6 flex gap-4 text-sm">
                    <Link
                        href="/heatmap"
                        className="rounded bg-(--color-ink) px-4 py-2 text-(--color-paper) no-underline hover:opacity-90"
                    >
                        Full heatmap →
                    </Link>
                    <Link
                        href="/projects"
                        className="rounded border border-(--color-paper-dim) px-4 py-2 text-(--color-ink) no-underline hover:bg-(--color-paper-dim)/50"
                    >
                        All projects →
                    </Link>
                    <Link
                        href="/packages"
                        className="rounded border border-(--color-paper-dim) px-4 py-2 text-(--color-ink) no-underline hover:bg-(--color-paper-dim)/50"
                    >
                        All packages →
                    </Link>
                </div>
            </section>

            {/* Stats strip */}
            <section className="grid grid-cols-2 md:grid-cols-4 gap-4 -mt-8">
                <div className="rounded border border-(--color-paper-dim) bg-(--color-paper) p-4">
                    <div className="text-2xl font-bold text-(--color-ink)">{heatmap.projects.length}</div>
                    <div className="text-xs text-(--color-ink-dim)">projects</div>
                </div>
                <div className="rounded border border-(--color-paper-dim) bg-(--color-paper) p-4">
                    <div className="text-2xl font-bold text-(--color-ink)">{packageCount}</div>
                    <div className="text-xs text-(--color-ink-dim)">packages across {languages.size} languages</div>
                </div>
                <div className="rounded border border-(--color-paper-dim) bg-(--color-paper) p-4">
                    <div className="text-2xl font-bold text-(--color-ink)">{heatmap.capabilities.length}</div>
                    <div className="text-xs text-(--color-ink-dim)">capabilities</div>
                </div>
                <div className="rounded border border-(--color-paper-dim) bg-(--color-paper) p-4">
                    <div className="text-2xl font-bold text-(--color-ink)">{gaps.capability_gaps.length}</div>
                    <div className="text-xs text-(--color-ink-dim)">gaps to fill</div>
                </div>
            </section>

            <section>
                <h2 className="text-xl font-semibold text-(--color-ink) mb-4">Where the work concentrates</h2>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {topCapabilities.map((cap) => (
                        <Link
                            key={cap.id}
                            href={`/capabilities/${cap.slug}`}
                            className="rounded-lg border border-(--color-paper-dim) bg-(--color-paper) p-5 no-underline hover:border-(--color-accent)"
                        >
                            <div className="text-sm text-(--color-ink-dim)">{cap.category}</div>
                            <div className="mt-1 text-lg font-semibold text-(--color-ink)">{cap.name}</div>
                            <div className="mt-2 text-3xl font-bold text-(--color-accent)">{cap.count}</div>
                            <div className="text-xs text-(--color-ink-dim)">projects</div>
                        </Link>
                    ))}
                </div>
            </section>

            <section className="hidden md:block">
                <h2 className="text-xl font-semibold text-(--color-ink) mb-4">Heatmap (top 10 capabilities)</h2>
                <Heatmap heatmap={{
                    ...heatmap,
                    capabilities: [...heatmap.capabilities].sort((a, b) => b.count - a.count).slice(0, 10),
                }} />
            </section>
            <section className="md:hidden">
                <h2 className="text-xl font-semibold text-(--color-ink) mb-4">Top capabilities</h2>
                <Heatmap heatmap={{
                    ...heatmap,
                    capabilities: [...heatmap.capabilities].sort((a, b) => b.count - a.count).slice(0, 6),
                }} layout="stacked" />
            </section>

            <section>
                <h2 className="text-xl font-semibold text-(--color-ink) mb-2">What&apos;s missing</h2>
                <p className="text-sm text-(--color-ink-dim) mb-4">
                    Capabilities used 0–2 times. Most freelancer portfolios hide their
                    gaps; Codex publishes them.
                </p>
                <ul className="flex flex-wrap gap-2 text-sm">
                    {gaps.capability_gaps.slice(0, 12).map((g) => (
                        <li key={g.slug} className="rounded border border-(--color-paper-dim) bg-(--color-paper) px-3 py-1.5">
                            {g.name}{' '}
                            <span className="text-xs text-(--color-ink-dim)">({g.count})</span>
                        </li>
                    ))}
                </ul>
                <Link href="/gaps" className="mt-4 inline-block text-sm">
                    Full gap report →
                </Link>
            </section>
        </div>
    );
}
