import { getGapReport } from '@/lib/codex-api';

export const revalidate = 3600;
export const metadata = {
    title: 'Gaps — Codex',
    description: 'Capabilities used 0–2 times and tech × industry coverage.',
};

export default async function GapsPage() {
    const { data: gaps } = await getGapReport();

    // Build a sorted, sparse tech × industry coverage view.
    const techs = Array.from(new Set(gaps.tech_industry_coverage.map((c) => c.technology_slug)))
        .map((slug) => {
            const found = gaps.tech_industry_coverage.find((c) => c.technology_slug === slug);
            return found ? { slug, name: found.technology_name } : null;
        })
        .filter((t): t is { slug: string; name: string } => t !== null)
        .sort((a, b) => a.name.localeCompare(b.name));

    const industries = Array.from(new Set(gaps.tech_industry_coverage.map((c) => c.industry_slug)))
        .map((slug) => {
            const found = gaps.tech_industry_coverage.find((c) => c.industry_slug === slug);
            return found ? { slug, name: found.industry_name } : null;
        })
        .filter((i): i is { slug: string; name: string } => i !== null)
        .sort((a, b) => a.name.localeCompare(b.name));

    const lookup = new Map<string, number>();
    for (const cell of gaps.tech_industry_coverage) {
        lookup.set(`${cell.technology_slug}|${cell.industry_slug}`, cell.count);
    }

    return (
        <div className="space-y-12">
            <header>
                <h1 className="text-3xl font-bold tracking-tight text-(--color-ink)">Gap report</h1>
                <p className="mt-2 max-w-2xl text-(--color-ink-dim)">
                    Capabilities and tech × industry pairs with the lowest coverage.
                    What&apos;s missing is a signal, not a secret.
                </p>
            </header>

            <section>
                <h2 className="text-xl font-semibold text-(--color-ink) mb-3">
                    Underused capabilities ({gaps.capability_gaps.length})
                </h2>
                <ul className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    {gaps.capability_gaps.map((cap) => (
                        <li key={cap.slug} className="rounded border border-(--color-paper-dim) bg-(--color-paper) p-4">
                            <div className="text-xs text-(--color-ink-dim)">{cap.category}</div>
                            <div className="font-medium text-(--color-ink)">{cap.name}</div>
                            <div className="text-xs text-(--color-ink-dim) mt-1">
                                {cap.count === 0 ? 'No projects yet.' : `${cap.count} project${cap.count === 1 ? '' : 's'} so far.`}
                            </div>
                        </li>
                    ))}
                </ul>
            </section>

            <section>
                <h2 className="text-xl font-semibold text-(--color-ink) mb-3">
                    Tech × industry coverage
                </h2>
                <p className="text-sm text-(--color-ink-dim) mb-4">
                    Empty cells are unworked combinations — Phase 2 candidates.
                </p>
                <div className="overflow-x-auto -mx-6 px-6">
                    <table className="border-collapse text-xs">
                        <thead>
                            <tr>
                                <th className="text-left font-medium text-(--color-ink-dim) pr-3 pb-2">Technology</th>
                                {industries.map((i) => (
                                    <th key={i.slug} className="font-medium text-(--color-ink-dim) px-1 pb-2 whitespace-nowrap [writing-mode:vertical-rl] rotate-180">
                                        {i.name}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {techs.map((t) => (
                                <tr key={t.slug} className="hover:bg-(--color-paper-dim)/50">
                                    <th className="text-left font-normal text-(--color-ink) pr-3 py-1 whitespace-nowrap">
                                        {t.name}
                                    </th>
                                    {industries.map((i) => {
                                        const count = lookup.get(`${t.slug}|${i.slug}`) ?? 0;
                                        return (
                                            <td
                                                key={i.slug}
                                                className={
                                                    'text-center align-middle px-1 ' +
                                                    (count === 0
                                                        ? 'text-(--color-paper-dim)'
                                                        : count >= 3
                                                            ? 'bg-(--color-accent) text-white'
                                                            : 'bg-(--color-accent-soft) text-(--color-accent)')
                                                }
                                            >
                                                {count === 0 ? '·' : count}
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    );
}
