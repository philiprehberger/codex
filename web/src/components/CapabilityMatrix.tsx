import type { CapabilityCategoryMatrix } from '@/lib/codex-api';

type Props = {
    matrix: CapabilityCategoryMatrix;
};

/**
 * Capability-category × industry matrix.
 *
 * The headline view at /heatmap. Categories on rows (~9), industries on
 * columns (~10). Cell shows project count with a 4-bucket color ramp;
 * tooltip surfaces the capability count. Empty cells render a dim ·.
 *
 * No D3, no chart library — CSS Grid with one rendered <div> per cell.
 * Sparse cell payload from the API is materialised into a dense lookup
 * map at render time.
 */
export function CapabilityMatrix({ matrix }: Props) {
    const { categories, industries, cells } = matrix;

    const lookup = new Map<string, { project_count: number; capability_count: number }>();
    for (const cell of cells) {
        lookup.set(`${cell.category}|${cell.industry_slug}`, {
            project_count: cell.project_count,
            capability_count: cell.capability_count,
        });
    }

    return (
        <div className="overflow-x-auto -mx-6 px-6">
            <table className="border-collapse text-sm">
                <caption className="sr-only">
                    Capability category × industry matrix. Rows are capability
                    categories, columns are industries, cells show project counts.
                </caption>
                <thead>
                    <tr>
                        <th
                            scope="col"
                            className="text-left text-xs font-medium text-(--color-ink-dim) pb-2 pr-4 sticky left-0 bg-(--color-paper) z-10"
                        >
                            Category
                        </th>
                        {industries.map((i) => (
                            <th
                                key={i.slug}
                                scope="col"
                                className="text-xs font-medium text-(--color-ink-dim) pb-2 px-1 whitespace-nowrap [writing-mode:vertical-rl] rotate-180 align-bottom"
                            >
                                {i.name}{' '}
                                <span className="text-(--color-ink-dim)/60">({i.project_count})</span>
                            </th>
                        ))}
                        <th
                            scope="col"
                            className="text-xs font-medium text-(--color-ink-dim) pb-2 pl-3 align-bottom"
                        >
                            Total
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {categories.map((cat) => (
                        <tr key={cat.name} className="hover:bg-(--color-paper-dim)/40">
                            <th
                                scope="row"
                                className="text-left font-medium text-(--color-ink) pr-4 py-2 whitespace-nowrap sticky left-0 bg-(--color-paper) z-10"
                            >
                                {cat.name}{' '}
                                <span className="text-xs font-normal text-(--color-ink-dim)">
                                    ({cat.capability_count})
                                </span>
                            </th>
                            {industries.map((i) => {
                                const cell = lookup.get(`${cat.name}|${i.slug}`);
                                const projectCount = cell?.project_count ?? 0;
                                const capabilityCount = cell?.capability_count ?? 0;
                                return (
                                    <td
                                        key={i.slug}
                                        className={
                                            'text-center align-middle px-1 py-1 ' +
                                            cellClass(projectCount)
                                        }
                                        title={
                                            projectCount === 0
                                                ? `${cat.name} × ${i.name}: no projects`
                                                : `${cat.name} × ${i.name}: ${projectCount} project${projectCount === 1 ? '' : 's'}, ${capabilityCount} capabilit${capabilityCount === 1 ? 'y' : 'ies'}`
                                        }
                                    >
                                        {projectCount === 0 ? (
                                            <span className="text-(--color-paper-dim)">·</span>
                                        ) : (
                                            projectCount
                                        )}
                                    </td>
                                );
                            })}
                            <td className="pl-3 py-2 text-right text-xs text-(--color-ink-dim) whitespace-nowrap">
                                {cat.project_count} proj
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <p className="mt-4 text-xs text-(--color-ink-dim) flex items-center gap-3 flex-wrap">
                <span>Intensity:</span>
                <Swatch label="0" className="text-(--color-paper-dim)" />
                <Swatch label="1-2" className="bg-(--color-accent-soft) text-(--color-accent)" />
                <Swatch label="3-5" className="bg-(--color-accent)/40 text-(--color-ink)" />
                <Swatch label="6+" className="bg-(--color-accent) text-white" />
                <span className="ml-auto">
                    Row label count = canonical capabilities in that category. Column label count
                    = projects in that industry. Cell = projects covered by capabilities in this
                    category × industry pair.
                </span>
            </p>
        </div>
    );
}

function cellClass(projectCount: number): string {
    if (projectCount === 0) return '';
    if (projectCount <= 2) return 'bg-(--color-accent-soft) text-(--color-accent)';
    if (projectCount <= 5) return 'bg-(--color-accent)/40 text-(--color-ink) font-medium';
    return 'bg-(--color-accent) text-white font-semibold';
}

function Swatch({ label, className }: { label: string; className: string }) {
    return (
        <span className="inline-flex items-center gap-1.5">
            <span
                aria-hidden="true"
                className={`inline-block w-5 h-4 rounded-sm text-center text-[10px] leading-4 ${className}`}
            >
                {label === '0' ? '·' : ''}
            </span>
            <span>{label}</span>
        </span>
    );
}
