import { ResumeBulletList } from '@/components/ResumeBulletList';
import { getResumeBullets } from '@/lib/codex-api';

// Dynamic — same rationale as /heatmap: per-request CSP nonce must
// reach the inline hydration scripts. See web/middleware.ts.
export const dynamic = 'force-dynamic';
export const metadata = {
    title: 'Resume bullets — Codex',
    description: 'Aggregated bullets by capability, industry, and architecture.',
};

export default async function ResumeBulletsPage() {
    const { data: bullets } = await getResumeBullets();

    const sections = [
        {
            title: 'By capability',
            scopeType: 'capability' as const,
            items: bullets.by_capability.map((b) => ({
                key: b.capability_slug,
                label: b.capability_name,
                count: b.count,
                bullet: b.bullet,
            })),
        },
        {
            title: 'By industry',
            scopeType: 'industry' as const,
            items: bullets.by_industry.map((b) => ({
                key: b.industry_slug,
                label: b.industry_name,
                count: b.count,
                bullet: b.bullet,
            })),
        },
        {
            title: 'By architecture',
            scopeType: 'architecture' as const,
            items: bullets.by_architecture.map((b) => ({
                key: b.architecture_slug,
                label: b.architecture_name,
                count: b.count,
                bullet: b.bullet,
            })),
        },
    ];

    return (
        <div className="space-y-12">
            <header>
                <h1 className="text-3xl font-bold tracking-tight text-(--color-ink)">Resume bullets</h1>
                <p className="mt-2 max-w-2xl text-(--color-ink-dim)">
                    Copy-pasteable bullets generated from the catalogue. Use them as-is
                    for resume drafts, Upwork cover letters, Fiverr gigs. Click any bullet
                    to see the projects and packages it represents.
                </p>
            </header>

            <ResumeBulletList sections={sections} />
        </div>
    );
}
