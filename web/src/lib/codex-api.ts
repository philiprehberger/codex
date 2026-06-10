// Codex API client.
//
// Server-rendered Next.js fetches the Laravel API at build / revalidate
// time. Loopback URL on the EC2 box (CODEX_API_INTERNAL_URL=http://127.0.0.1)
// + an injected Host header — without the header, Apache picks the
// default vhost and SSR silently 404s.
//
// All API access MUST route through codexFetch(). ESLint rule
// `no-restricted-imports` blocks raw `fetch()` references in pages so
// the Host-header discipline is enforced at the linter, not at review
// time. See web/eslint.config.* (Phase 7 wire-up).

const INTERNAL_API_URL = process.env.CODEX_API_INTERNAL_URL ?? 'http://127.0.0.1';
const PUBLIC_API_HOST = process.env.NEXT_PUBLIC_CODEX_API_HOST ?? 'api.codex.philiprehberger.com';

export type RevalidateOptions = {
    /** Seconds. Defaults to 3600 (matches Laravel's report-cache TTL). */
    revalidate?: number;
    /** Tags fed to revalidateTag() by the Filament observer. */
    tags?: string[];
};

export class CodexApiError extends Error {
    constructor(
        public readonly status: number,
        public readonly url: string,
        message: string,
    ) {
        super(message);
        this.name = 'CodexApiError';
    }
}

export async function codexFetch<T>(
    path: string,
    opts: RevalidateOptions = {},
): Promise<T> {
    const url = `${INTERNAL_API_URL}${path.startsWith('/') ? path : `/${path}`}`;

    const next: { revalidate?: number; tags?: string[] } = {};
    if (opts.revalidate !== undefined) {
        next.revalidate = opts.revalidate;
    } else {
        next.revalidate = 3600;
    }
    if (opts.tags) {
        next.tags = opts.tags;
    }

    const response = await fetch(url, {
        method: 'GET',
        headers: {
            // Load-bearing — without this, Apache picks the wrong vhost
            // and the loopback fetch silently 404s. Documented at
            // ~/projects/income-ops/.scratch/plans/project_intelligence_
            // codex_portfolio.md §"Internal vs public API URL".
            Host: PUBLIC_API_HOST,
            Accept: 'application/json',
        },
        next,
    });

    if (!response.ok) {
        const detail = await response.text().catch(() => '');
        throw new CodexApiError(
            response.status,
            url,
            `${response.status} from ${url}: ${detail.slice(0, 200)}`,
        );
    }

    return (await response.json()) as T;
}

// ─── Convenience wrappers — one per endpoint ──────────────────────────

export type ProjectSummary = {
    id: string;
    slug: string;
    name: string;
    project_type: 'demo' | 'client' | 'personal' | 'open_source' | 'package';
    status: 'idea' | 'active' | 'shipped' | 'archived';
    visibility: 'public' | 'redacted' | 'private';
    short_description: string;
    client_name: string | null;
    client_industry: string | null;
    shipped_date: string | null;
    repo_url: string | null;
    live_url: string | null;
    docs_url: string | null;
    capabilities: Array<{ slug: string; name: string; is_primary: boolean }>;
    technologies: Array<{ slug: string; name: string; is_primary: boolean }>;
    industries: string[];
};

export type Paginated<T> = {
    data: T[];
    meta: { next_cursor: string | null; prev_cursor: string | null; per_page: number };
};

export type Heatmap = {
    capabilities: Array<{ id: string; slug: string; name: string; category: string; count: number }>;
    projects: Array<{ id: string; slug: string; name: string; type: string }>;
    cells: Array<{ capability_id: string; project_id: string; is_primary: boolean }>;
};

export type CapabilityListItem = {
    id: string;
    slug: string;
    name: string;
    category: string;
    description: string;
    icon: string | null;
    project_count: number;
};

export type CapabilityDetail = {
    id: string;
    slug: string;
    name: string;
    category: string;
    description: string;
    icon: string | null;
    redirected_from: string | null;
    aliases: string[];
    projects: Array<{
        slug: string;
        name: string;
        project_type: string;
        status: string;
        visibility: string;
        short_description: string;
        client_industry: string | null;
        shipped_date: string | null;
    }>;
    project_count: number;
};

export type GapReport = {
    capability_gaps: Array<{ slug: string; name: string; category: string; count: number }>;
    tech_industry_coverage: Array<{
        technology_slug: string;
        technology_name: string;
        industry_slug: string;
        industry_name: string;
        count: number;
    }>;
};

export type ResumeBullets = {
    by_capability: Array<{ capability_slug: string; capability_name: string; count: number; bullet: string }>;
    by_industry: Array<{ industry_slug: string; industry_name: string; count: number; bullet: string }>;
    by_architecture: Array<{ architecture_slug: string; architecture_name: string; count: number; bullet: string }>;
};

const STANDARD_TAGS = ['codex:heatmap', 'codex:reports:gaps', 'codex:reports:bullets', 'codex:search:index'];

export async function listProjects(query: Record<string, string> = {}) {
    const search = new URLSearchParams(query).toString();
    const path = `/api/v1/projects${search ? `?${search}` : ''}`;
    return codexFetch<Paginated<ProjectSummary>>(path, { tags: STANDARD_TAGS });
}

export async function getProject(slug: string) {
    return codexFetch<{ data: Record<string, unknown> }>(
        `/api/v1/projects/${slug}`,
        { tags: STANDARD_TAGS },
    );
}

export async function getHeatmap() {
    return codexFetch<{ data: Heatmap }>('/api/v1/capabilities/heatmap', { tags: STANDARD_TAGS });
}

export async function listCapabilities() {
    return codexFetch<{ data: CapabilityListItem[] }>('/api/v1/capabilities', { tags: STANDARD_TAGS });
}

export async function getCapability(slug: string) {
    return codexFetch<{ data: CapabilityDetail }>(
        `/api/v1/capabilities/${slug}`,
        { tags: STANDARD_TAGS },
    );
}

export async function getGapReport() {
    return codexFetch<{ data: GapReport }>('/api/v1/reports/gaps', { tags: STANDARD_TAGS });
}

export async function getResumeBullets() {
    return codexFetch<{ data: ResumeBullets }>(
        '/api/v1/reports/resume-bullets',
        { tags: STANDARD_TAGS },
    );
}
