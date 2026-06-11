'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import type {
    DrillDownPackageCard,
    DrillDownProjectCard,
    DrillDownQuery,
    DrillDownResult,
} from '@/lib/codex-api';
import { getDrillDown } from '@/lib/codex-api';

type Props = {
    query: DrillDownQuery | null;
    fallbackTitle?: string;
    onClose: () => void;
};

/**
 * Center-overlay modal for drill-down results.
 *
 * Activated by setting `query` to a non-null DrillDownQuery; cleared by
 * calling onClose(). The modal owns the fetch lifecycle: on every new
 * query it fires getDrillDown(), shows a loading state, then renders
 * the project + package cards.
 *
 * Esc + backdrop click both close. Focus is parked on the close button
 * for screen-reader navigation.
 */
export function DrillDownModal({ query, fallbackTitle, onClose }: Props) {
    const [data, setData] = useState<DrillDownResult | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (query === null) {
            setData(null);
            setError(null);
            return;
        }
        let cancelled = false;
        setLoading(true);
        setError(null);
        setData(null);
        getDrillDown(query)
            .then((result) => {
                if (!cancelled) setData(result);
            })
            .catch((e: unknown) => {
                if (!cancelled) setError(e instanceof Error ? e.message : 'Failed to load');
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });
        return () => {
            cancelled = true;
        };
    }, [query]);

    useEffect(() => {
        if (query === null) return;
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [query, onClose]);

    if (query === null) return null;

    const title = data?.title ?? fallbackTitle ?? 'Loading…';

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label={title}
            className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:p-8"
        >
            <button
                type="button"
                aria-label="Close"
                onClick={onClose}
                className="fixed inset-0 cursor-default bg-(--color-ink)/40 backdrop-blur-sm"
            />
            <div className="relative z-10 w-full max-w-3xl rounded-lg border border-(--color-paper-dim) bg-(--color-paper) shadow-xl">
                <div className="flex items-start justify-between gap-4 border-b border-(--color-paper-dim) px-5 py-4">
                    <div>
                        <h2 className="text-lg font-semibold text-(--color-ink)">{title}</h2>
                        {data ? (
                            <p className="mt-0.5 text-xs text-(--color-ink-dim)">{data.subtitle}</p>
                        ) : null}
                    </div>
                    <button
                        type="button"
                        autoFocus
                        onClick={onClose}
                        className="rounded p-1 text-(--color-ink-dim) hover:bg-(--color-paper-dim) hover:text-(--color-ink)"
                        aria-label="Close"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <path d="M18 6 6 18M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div className="px-5 py-4">
                    {loading ? <Skeleton /> : null}
                    {error ? (
                        <p className="text-sm text-(--color-bad)">Error loading: {error}</p>
                    ) : null}
                    {data ? (
                        <div className="space-y-6">
                            {data.projects.length > 0 ? (
                                <CardSection
                                    heading={`Projects (${data.projects.length})`}
                                    items={data.projects.map((p) => (
                                        <ProjectCard key={p.slug} project={p} />
                                    ))}
                                />
                            ) : null}
                            {data.packages.length > 0 ? (
                                <CardSection
                                    heading={`Packages (${data.packages.length})`}
                                    items={data.packages.map((p) => (
                                        <PackageCard key={p.slug} pkg={p} />
                                    ))}
                                />
                            ) : null}
                            {data.projects.length === 0 && data.packages.length === 0 ? (
                                <p className="text-sm text-(--color-ink-dim)">
                                    No projects or packages match this selection.
                                </p>
                            ) : null}
                        </div>
                    ) : null}
                </div>
            </div>
        </div>
    );
}

function CardSection({ heading, items }: { heading: string; items: React.ReactNode[] }) {
    return (
        <section>
            <h3 className="mb-2 text-sm font-medium text-(--color-ink-dim)">{heading}</h3>
            <ul className="grid grid-cols-1 gap-2 sm:grid-cols-2">{items}</ul>
        </section>
    );
}

function ProjectCard({ project }: { project: DrillDownProjectCard }) {
    return (
        <li>
            <Link
                href={`/projects/${project.slug}`}
                className="block rounded border border-(--color-paper-dim) bg-(--color-paper) p-3 no-underline hover:border-(--color-accent)"
            >
                <div className="flex items-baseline justify-between gap-2">
                    <div className="font-medium text-(--color-ink)">{project.name}</div>
                    <span className="shrink-0 text-xs text-(--color-ink-dim)">
                        {project.shipped_date ?? project.status}
                    </span>
                </div>
                <p className="mt-1 text-sm text-(--color-ink-dim)">{project.short_description}</p>
                {project.client_industry ? (
                    <div className="mt-1 text-xs text-(--color-ink-dim)">
                        Industry: {project.client_industry}
                    </div>
                ) : null}
            </Link>
        </li>
    );
}

function PackageCard({ pkg }: { pkg: DrillDownPackageCard }) {
    return (
        <li>
            <Link
                href={`/packages/${pkg.slug}`}
                className="block rounded border border-(--color-paper-dim) bg-(--color-paper) p-3 no-underline hover:border-(--color-accent)"
            >
                <div className="flex items-baseline justify-between gap-2">
                    <div className="font-medium text-(--color-ink)">{pkg.name}</div>
                    <span className="shrink-0 text-xs text-(--color-ink-dim)">{pkg.language}</span>
                </div>
                <p className="mt-1 text-sm text-(--color-ink-dim)">{pkg.short_description}</p>
            </Link>
        </li>
    );
}

function Skeleton() {
    return (
        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
            {[0, 1, 2, 3].map((i) => (
                <div
                    key={i}
                    className="h-20 animate-pulse rounded border border-(--color-paper-dim) bg-(--color-paper-dim)/40"
                />
            ))}
        </div>
    );
}
