export const metadata = {
    title: 'About — Codex',
    description: 'How Codex is built and what the heatmap is and isn\'t.',
};

export default function AboutPage() {
    return (
        <article className="prose">
            <h1>About Codex</h1>

            <p>
                Codex is a portfolio intelligence dashboard. Every project Philip
                Rehberger has built — demos, packages, client engagements — is
                catalogued in one place, tagged along five axes (capability,
                technology, industry, architecture, deliverable), and rendered as a
                heatmap + gap report.
            </p>

            <h2>Why capability-led, not technology-led</h2>
            <p>
                Buyers don&apos;t hire freelancers for a stack — they hire for a job.
                &quot;I need a customer portal with reporting and payments&quot; sells
                better than &quot;I need Laravel.&quot; The capability vocabulary
                (Authentication, Payments, Search, Reporting, Real-time, Document
                Generation, Multi-tenant, …) is the load-bearing layer. Technologies
                are how; capabilities are what.
            </p>

            <h2>What the heatmap is, and isn&apos;t</h2>
            <p>
                It&apos;s a portfolio map. Each cell means &quot;this project carries
                this capability.&quot; The count next to each capability is the project
                count — concentration of shipped work, not a skills certification or a
                ranking.
            </p>
            <p>
                Client work is redacted by client identity (RedactedScope on the
                Laravel side) but tagged at full fidelity. Industry stays visible
                because that&apos;s the proof-of-portfolio shape; the client&apos;s
                name is private to the engagement.
            </p>

            <h2>About the package representation</h2>
            <p>
                Philip&apos;s open-source library covers ~630 packages across PHP,
                TypeScript, Python, and Go. Listing each as its own row would drown
                the heatmap. Codex represents the package collection as
                cluster-per-language rows (PHP / Laravel — Feature Flags, TypeScript
                — Caching, Go — Resilience, …) — about 25 rows. The full list lives
                at{' '}
                <a href="https://philiprehberger.com/open-source-packages">
                    philiprehberger.com/open-source-packages
                </a>.
            </p>

            <h2>Stack</h2>
            <ul>
                <li>Laravel 13 + Filament v5 + MySQL 8 (read API + admin)</li>
                <li>Next.js 16 + React 19 + Tailwind 4 (this dashboard)</li>
                <li>Apache + mod_php + PM2 (deploy)</li>
                <li>Sentry + BetterStack + Plausible (observability)</li>
                <li>All sub-$0.50/mo in cloud cost at Phase 1 traffic</li>
            </ul>

            <p>
                Source at{' '}
                <a href="https://github.com/philiprehberger/codex">
                    github.com/philiprehberger/codex
                </a>.
            </p>
        </article>
    );
}
