'use client';

import { useState } from 'react';
import type { CapabilityCategoryMatrix, DrillDownQuery } from '@/lib/codex-api';
import { DrillDownModal } from './DrillDownModal';

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
 * Every label and every non-zero cell is a click target — opens the
 * DrillDownModal scoped to that selection. The matrix payload is
 * server-rendered into props; the modal fetches its own data on demand.
 */
export function CapabilityMatrix({ matrix }: Props) {
    const { categories, industries, cells } = matrix;
    const [activeQuery, setActiveQuery] = useState<DrillDownQuery | null>(null);
    const [activeTitle, setActiveTitle] = useState<string>('');

    const lookup = new Map<string, { project_count: number; capability_count: number }>();
    for (const cell of cells) {
        lookup.set(`${cell.category}|${cell.industry_slug}`, {
            project_count: cell.project_count,
            capability_count: cell.capability_count,
        });
    }

    function openCategory(name: string) {
        setActiveQuery({ category: name });
        setActiveTitle(name);
    }
    function openIndustry(slug: string, name: string) {
        setActiveQuery({ industry: slug });
        setActiveTitle(name);
    }
    function openCell(category: string, industrySlug: string, industryName: string) {
        setActiveQuery({ category, industry: industrySlug });
        setActiveTitle(`${category} × ${industryName}`);
    }

    return (
        <>
            <div className="overflow-x-auto -mx-6 px-6">
                <table className="border-separate border-spacing-[3px] text-sm">
                    <caption className="sr-only">
                        Capability category × industry matrix. Rows are capability
                        categories, columns are industries, cells show project counts.
                        Click any label or non-empty cell to see the projects and
                        packages it represents.
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
                                    className="pb-2 px-1 align-bottom whitespace-nowrap min-w-[2.75rem]"
                                >
                                    <button
                                        type="button"
                                        onClick={() => openIndustry(i.slug, i.name)}
                                        className="text-xs font-medium text-(--color-ink-dim) hover:text-(--color-accent) [writing-mode:vertical-rl] rotate-180 cursor-pointer bg-transparent border-0 p-0"
                                        title={`${i.name}: ${i.project_count} project${i.project_count === 1 ? '' : 's'}`}
                                    >
                                        {i.name}{' '}
                                        <span className="text-(--color-ink-dim)/60">({i.project_count})</span>
                                    </button>
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
                            <tr key={cat.name}>
                                <th
                                    scope="row"
                                    className="text-left font-medium text-(--color-ink) pr-4 py-1 whitespace-nowrap sticky left-0 bg-(--color-paper) z-10"
                                >
                                    <button
                                        type="button"
                                        onClick={() => openCategory(cat.name)}
                                        className="text-(--color-ink) hover:text-(--color-accent) cursor-pointer bg-transparent border-0 p-0 font-medium text-left"
                                    >
                                        {cat.name}{' '}
                                        <span className="text-xs font-normal text-(--color-ink-dim)">
                                            ({cat.capability_count})
                                        </span>
                                    </button>
                                </th>
                                {industries.map((i) => {
                                    const cell = lookup.get(`${cat.name}|${i.slug}`);
                                    const projectCount = cell?.project_count ?? 0;
                                    const capabilityCount = cell?.capability_count ?? 0;
                                    const title =
                                        projectCount === 0
                                            ? `${cat.name} × ${i.name}: no projects`
                                            : `${cat.name} × ${i.name}: ${projectCount} project${projectCount === 1 ? '' : 's'}, ${capabilityCount} capabilit${capabilityCount === 1 ? 'y' : 'ies'} — click for cards`;
                                    return (
                                        <td
                                            key={i.slug}
                                            className="p-0 text-center align-middle"
                                        >
                                            {projectCount === 0 ? (
                                                <div
                                                    className="flex items-center justify-center w-10 h-10 text-(--color-paper-dim) rounded-sm"
                                                    title={title}
                                                >
                                                    ·
                                                </div>
                                            ) : (
                                                <button
                                                    type="button"
                                                    onClick={() => openCell(cat.name, i.slug, i.name)}
                                                    title={title}
                                                    className={
                                                        'flex items-center justify-center w-10 h-10 rounded-sm cursor-pointer transition-colors hover:ring-2 hover:ring-(--color-accent) ' +
                                                        cellClass(projectCount)
                                                    }
                                                >
                                                    {projectCount}
                                                </button>
                                            )}
                                        </td>
                                    );
                                })}
                                <td className="pl-3 py-1 text-right text-xs text-(--color-ink-dim) whitespace-nowrap">
                                    {cat.project_count} proj
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <div className="mt-4 flex items-center gap-4 flex-wrap text-xs text-(--color-ink-dim)">
                    <span>Intensity:</span>
                    <Swatch label="1-2" className="bg-(--color-accent-soft) text-(--color-accent)" />
                    <Swatch label="3-5" className="bg-(--color-accent)/40 text-(--color-ink)" />
                    <Swatch label="6+" className="bg-(--color-accent) text-white" />
                    <span className="ml-auto max-w-md text-right">
                        Click a category, industry, or cell to see the projects and packages behind it.
                    </span>
                </div>
            </div>

            <DrillDownModal
                query={activeQuery}
                fallbackTitle={activeTitle}
                onClose={() => setActiveQuery(null)}
            />
        </>
    );
}

function cellClass(projectCount: number): string {
    if (projectCount <= 2) return 'bg-(--color-accent-soft) text-(--color-accent)';
    if (projectCount <= 5) return 'bg-(--color-accent)/40 text-(--color-ink) font-medium';
    return 'bg-(--color-accent) text-white font-semibold';
}

function Swatch({ label, className }: { label: string; className: string }) {
    return (
        <span className="inline-flex items-center gap-1.5">
            <span aria-hidden="true" className={`inline-block w-5 h-5 rounded-sm ${className}`} />
            <span>{label}</span>
        </span>
    );
}
