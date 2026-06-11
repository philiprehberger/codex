'use client';

import { useState } from 'react';
import type { DrillDownQuery } from '@/lib/codex-api';
import { DrillDownModal } from './DrillDownModal';

export type ResumeBulletItem = {
    key: string;
    label: string;
    count: number;
    bullet: string;
};

type ScopeType = 'capability' | 'industry' | 'architecture';

type SectionConfig = {
    title: string;
    scopeType: ScopeType;
    items: ResumeBulletItem[];
};

type Props = {
    sections: SectionConfig[];
};

/**
 * Client wrapper for the /resume-bullets page. Renders each section as
 * clickable bullets; a click on a bullet opens the DrillDownModal scoped
 * to that bullet's capability / industry / architecture so the operator
 * can verify the projects + packages behind each headline number.
 */
export function ResumeBulletList({ sections }: Props) {
    const [activeQuery, setActiveQuery] = useState<DrillDownQuery | null>(null);
    const [activeTitle, setActiveTitle] = useState<string>('');

    function open(scopeType: ScopeType, slug: string, label: string) {
        const query: DrillDownQuery =
            scopeType === 'capability'
                ? { capability: slug }
                : scopeType === 'industry'
                  ? { industry: slug }
                  : { architecture: slug };
        setActiveQuery(query);
        setActiveTitle(label);
    }

    return (
        <>
            <div className="space-y-12">
                {sections.map((section) => (
                    <section key={section.title}>
                        <h2 className="text-xl font-semibold text-(--color-ink) mb-3">
                            {section.title}
                        </h2>
                        <ul className="space-y-2 text-sm">
                            {section.items.map((it) => (
                                <li key={it.key}>
                                    <button
                                        type="button"
                                        onClick={() => open(section.scopeType, it.key, it.label)}
                                        className="block w-full text-left rounded border border-(--color-paper-dim) bg-(--color-paper) p-3 hover:border-(--color-accent) cursor-pointer"
                                        title={`Click to see the ${it.count} project${it.count === 1 ? '' : 's'} or packages behind this bullet`}
                                    >
                                        <div className="text-xs text-(--color-ink-dim) mb-1 flex items-center justify-between">
                                            <span>
                                                {it.label} ({it.count})
                                            </span>
                                            <span className="text-(--color-ink-dim)/60">
                                                view cards →
                                            </span>
                                        </div>
                                        <div className="text-(--color-ink)">{it.bullet}</div>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}
            </div>

            <DrillDownModal
                query={activeQuery}
                fallbackTitle={activeTitle}
                onClose={() => setActiveQuery(null)}
            />
        </>
    );
}
