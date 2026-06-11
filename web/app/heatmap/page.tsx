import { CapabilityMatrix } from '@/components/CapabilityMatrix';
import { Heatmap } from '@/components/Heatmap';
import { getCapabilityCategoryMatrix, getHeatmap } from '@/lib/codex-api';

// Dynamic so the per-request CSP nonce from middleware.ts actually
// lands on Next.js's inline hydration scripts. Pre-rendering would
// freeze the HTML with `nonce:"$undefined"`, which makes the page-
// level CSP useless. The upstream API is cached server-side under
// codex:capability-matrix + codex:heatmap, so each request is still
// fast — the cost is React server-rendering per hit.
export const dynamic = 'force-dynamic';
export const metadata = {
    title: 'Heatmap — Codex',
    description: 'Capability × project matrix across the portfolio.',
};

export default async function HeatmapPage() {
    const [{ data: matrix }, { data: heatmap }] = await Promise.all([
        getCapabilityCategoryMatrix(),
        getHeatmap(),
    ]);

    return (
        <div className="space-y-12">
            <header>
                <h1 className="text-3xl font-bold tracking-tight text-(--color-ink)">Capability heatmap</h1>
                <p className="mt-2 max-w-2xl text-(--color-ink-dim)">
                    Where the work is concentrated. Capability categories on the rows,
                    industries on the columns. Cells count the projects that carry any
                    capability in that pair.
                </p>
                <p className="mt-2 text-sm text-(--color-ink-dim)">
                    {matrix.categories.length} categories · {matrix.industries.length} industries · {heatmap.projects.length} projects · {heatmap.capabilities.length} capabilities
                </p>
            </header>

            <section>
                <CapabilityMatrix matrix={matrix} />
            </section>

            <section>
                <details className="group rounded-lg border border-(--color-paper-dim) bg-(--color-paper)">
                    <summary className="cursor-pointer list-none px-4 py-3 text-sm font-medium text-(--color-ink) flex items-center justify-between">
                        <span>Full capability × project matrix</span>
                        <span className="text-xs text-(--color-ink-dim) group-open:hidden">
                            {heatmap.capabilities.length} × {heatmap.projects.length} grid, {heatmap.cells.length} cells — expand to view
                        </span>
                        <span className="text-xs text-(--color-ink-dim) hidden group-open:inline">
                            Collapse
                        </span>
                    </summary>
                    <div className="border-t border-(--color-paper-dim) p-4">
                        <p className="text-xs text-(--color-ink-dim) mb-3">
                            Every capability × project pair. Filled cells mean the project carries
                            the capability; outlined cells are the project&apos;s primary capability.
                        </p>
                        <div className="hidden md:block">
                            <Heatmap heatmap={heatmap} />
                        </div>
                        <div className="md:hidden">
                            <Heatmap heatmap={heatmap} layout="stacked" />
                        </div>
                    </div>
                </details>
            </section>
        </div>
    );
}
