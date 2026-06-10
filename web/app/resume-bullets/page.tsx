import { getResumeBullets } from '@/lib/codex-api';

export const revalidate = 3600;
export const metadata = {
    title: 'Resume bullets — Codex',
    description: 'Aggregated bullets by capability, industry, and architecture.',
};

export default async function ResumeBulletsPage() {
    const { data: bullets } = await getResumeBullets();

    return (
        <div className="space-y-12">
            <header>
                <h1 className="text-3xl font-bold tracking-tight text-(--color-ink)">Resume bullets</h1>
                <p className="mt-2 max-w-2xl text-(--color-ink-dim)">
                    Copy-pasteable bullets generated from the catalogue. Use them as-is
                    for resume drafts, Upwork cover letters, Fiverr gigs.
                </p>
            </header>

            <Section title="By capability" items={bullets.by_capability.map((b) => ({ key: b.capability_slug, label: b.capability_name, count: b.count, bullet: b.bullet }))} />
            <Section title="By industry" items={bullets.by_industry.map((b) => ({ key: b.industry_slug, label: b.industry_name, count: b.count, bullet: b.bullet }))} />
            <Section title="By architecture" items={bullets.by_architecture.map((b) => ({ key: b.architecture_slug, label: b.architecture_name, count: b.count, bullet: b.bullet }))} />
        </div>
    );
}

function Section({
    title,
    items,
}: {
    title: string;
    items: Array<{ key: string; label: string; count: number; bullet: string }>;
}) {
    return (
        <section>
            <h2 className="text-xl font-semibold text-(--color-ink) mb-3">{title}</h2>
            <ul className="space-y-2 text-sm">
                {items.map((it) => (
                    <li key={it.key} className="rounded border border-(--color-paper-dim) bg-(--color-paper) p-3">
                        <div className="text-xs text-(--color-ink-dim) mb-1">
                            {it.label} ({it.count})
                        </div>
                        <div className="text-(--color-ink)">{it.bullet}</div>
                    </li>
                ))}
            </ul>
        </section>
    );
}
