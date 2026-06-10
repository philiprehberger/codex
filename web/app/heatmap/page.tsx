import { Heatmap } from '@/components/Heatmap';
import { getHeatmap } from '@/lib/codex-api';

export const revalidate = 3600;
export const metadata = {
    title: 'Heatmap — Codex',
    description: 'Capability × project matrix across the portfolio.',
};

export default async function HeatmapPage() {
    const { data: heatmap } = await getHeatmap();

    return (
        <div className="space-y-6">
            <header>
                <h1 className="text-3xl font-bold tracking-tight text-(--color-ink)">Capability heatmap</h1>
                <p className="mt-2 max-w-2xl text-(--color-ink-dim)">
                    Rows are capabilities, columns are projects. Filled cells mean the
                    project carries the capability; outlined cells are the project&apos;s
                    primary capability.
                </p>
                <p className="mt-2 text-sm text-(--color-ink-dim)">
                    {heatmap.capabilities.length} capabilities · {heatmap.projects.length} projects · {heatmap.cells.length} cells
                </p>
            </header>

            <div className="hidden md:block">
                <Heatmap heatmap={heatmap} />
            </div>
            <div className="md:hidden">
                <Heatmap heatmap={heatmap} layout="stacked" />
            </div>
        </div>
    );
}
