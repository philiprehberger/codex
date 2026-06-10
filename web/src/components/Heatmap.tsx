import type { Heatmap as HeatmapData } from '@/lib/codex-api';
import Link from 'next/link';

type Props = {
    heatmap: HeatmapData;
    /** When true, render a mobile-friendly stacked layout instead of the grid. */
    layout?: 'grid' | 'stacked';
};

/**
 * Capability heatmap.
 *
 * Desktop: CSS Grid — capability rows × project columns. Cells filled
 * where a project carries the capability; primary cells get an accent
 * outline.
 *
 * Mobile: stacked per-capability cards (the plan's mobile wireframe).
 * The 2D matrix is desktop-only; mobile is 1D.
 *
 * No D3, no chart library — Tailwind + CSS Grid + the data from the
 * sparse heatmap payload at /api/v1/capabilities/heatmap.
 */
export function Heatmap({ heatmap, layout = 'grid' }: Props) {
    const { capabilities, projects, cells } = heatmap;
    const cellByPair = new Map<string, { is_primary: boolean }>();
    for (const cell of cells) {
        cellByPair.set(`${cell.capability_id}|${cell.project_id}`, { is_primary: cell.is_primary });
    }

    if (layout === 'stacked') {
        return <StackedHeatmap heatmap={heatmap} />;
    }

    // Order capabilities by descending count so the most-used rows lead.
    const orderedCapabilities = [...capabilities].sort((a, b) => b.count - a.count);

    return (
        <div className="overflow-x-auto -mx-6 px-6">
            <table role="table" className="border-collapse text-sm min-w-full">
                <caption className="sr-only">
                    Capability × project heatmap. Rows are capabilities, columns are
                    projects, filled cells indicate the project uses the capability.
                </caption>
                <thead>
                    <tr>
                        <th scope="col" className="text-left text-xs font-medium text-(--color-ink-dim) pb-2 pr-4">
                            Capability
                        </th>
                        {projects.map((p) => (
                            <th
                                key={p.id}
                                scope="col"
                                className="text-xs font-medium text-(--color-ink-dim) pb-2 px-1 [writing-mode:vertical-rl] rotate-180 align-bottom whitespace-nowrap min-w-[1.75rem]"
                            >
                                <Link href={`/projects/${p.slug}`} className="text-(--color-ink-dim) no-underline hover:text-(--color-accent)">
                                    {p.name}
                                </Link>
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {orderedCapabilities.map((cap) => (
                        <tr key={cap.id} className="hover:bg-(--color-paper-dim)/50">
                            <th
                                scope="row"
                                className="text-left font-normal text-(--color-ink) pr-4 py-1.5 whitespace-nowrap"
                            >
                                <Link
                                    href={`/capabilities/${cap.slug}`}
                                    className="text-(--color-ink) no-underline hover:text-(--color-accent)"
                                >
                                    {cap.name}
                                </Link>{' '}
                                <span className="text-xs text-(--color-ink-dim)">({cap.count})</span>
                            </th>
                            {projects.map((p) => {
                                const cell = cellByPair.get(`${cap.id}|${p.id}`);
                                return (
                                    <td
                                        key={p.id}
                                        className={
                                            'p-0.5 text-center align-middle ' +
                                            (cell
                                                ? cell.is_primary
                                                    ? 'bg-(--color-accent) text-white'
                                                    : 'bg-(--color-accent-soft) text-(--color-accent)'
                                                : '')
                                        }
                                        aria-label={cell ? `${p.name} uses ${cap.name}` : undefined}
                                    >
                                        {cell ? '▣' : ''}
                                    </td>
                                );
                            })}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function StackedHeatmap({ heatmap }: { heatmap: HeatmapData }) {
    const { capabilities, projects, cells } = heatmap;
    const projectsById = new Map(projects.map((p) => [p.id, p]));

    // Group cells by capability_id.
    const projectsByCapability = new Map<string, typeof projects>();
    for (const cell of cells) {
        const list = projectsByCapability.get(cell.capability_id) ?? [];
        const project = projectsById.get(cell.project_id);
        if (project) list.push(project);
        projectsByCapability.set(cell.capability_id, list);
    }

    const ordered = [...capabilities].sort((a, b) => b.count - a.count);
    return (
        <div className="space-y-3">
            {ordered.map((cap) => {
                const projectList = projectsByCapability.get(cap.id) ?? [];
                const visible = projectList.slice(0, 5);
                const more = projectList.length - visible.length;
                return (
                    <div key={cap.id} className="rounded-lg border border-(--color-paper-dim) bg-(--color-paper) p-4">
                        <div className="flex items-baseline justify-between">
                            <Link
                                href={`/capabilities/${cap.slug}`}
                                className="font-medium text-(--color-ink) no-underline"
                            >
                                {cap.name}
                            </Link>
                            <span className="text-xs text-(--color-ink-dim)">({cap.count})</span>
                        </div>
                        <ul className="mt-2 flex flex-wrap gap-2 text-xs">
                            {visible.map((p) => (
                                <li key={p.id}>
                                    <Link
                                        href={`/projects/${p.slug}`}
                                        className="rounded bg-(--color-accent-soft) px-2 py-0.5 text-(--color-accent) no-underline"
                                    >
                                        ▣ {p.name}
                                    </Link>
                                </li>
                            ))}
                            {more > 0 && (
                                <li className="rounded px-2 py-0.5 text-(--color-ink-dim)">+ {more} more →</li>
                            )}
                        </ul>
                    </div>
                );
            })}
        </div>
    );
}
