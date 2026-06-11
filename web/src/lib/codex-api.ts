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

// Read env vars per-call (not at module load) so a test can swap them in
// beforeEach without bouncing the test runner. Both have safe defaults
// for production where the env is set at deploy time.
function getInternalApiUrl(): string {
    return process.env.CODEX_API_INTERNAL_URL ?? 'http://127.0.0.1';
}

function getPublicApiHost(): string {
    return process.env.NEXT_PUBLIC_CODEX_API_HOST ?? 'api.codex.philiprehberger.com';
}

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
    const url = `${getInternalApiUrl()}${path.startsWith('/') ? path : `/${path}`}`;

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
            Host: getPublicApiHost(),
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

export type CapabilityCategoryMatrix = {
    categories: Array<{ name: string; capability_count: number; project_count: number }>;
    industries: Array<{ slug: string; name: string; project_count: number }>;
    cells: Array<{ category: string; industry_slug: string; project_count: number; capability_count: number }>;
};

export type DrillDownScope =
    | { type: 'capability' | 'industry' | 'architecture'; slug: string; label: string }
    | { type: 'category' | 'cell'; label: string };

export type DrillDownProjectCard = {
    slug: string;
    name: string;
    short_description: string;
    project_type: string;
    status: string;
    visibility: string;
    shipped_date: string | null;
    client_industry: string | null;
};

export type DrillDownPackageCard = {
    slug: string;
    name: string;
    short_description: string;
    language: string;
    registry: string;
    status: string;
    repo_url: string | null;
    shipped_date: string | null;
};

export type DrillDownResult = {
    scope: DrillDownScope;
    title: string;
    subtitle: string;
    projects: DrillDownProjectCard[];
    packages: DrillDownPackageCard[];
};

export type DrillDownQuery =
    | { capability: string }
    | { industry: string }
    | { architecture: string }
    | { category: string }
    | { category: string; industry: string };

export type CapabilityListItem = {
    id: string;
    slug: string;
    name: string;
    category: string;
    description: string;
    icon: string | null;
    project_count: number;
    package_count: number;
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
    packages: Array<{
        slug: string;
        name: string;
        language: string;
        registry: string;
        short_description: string;
        repo_url: string | null;
        is_primary: boolean;
    }>;
    package_count: number;
};

export type GapReport = {
    capability_gaps: Array<{
        slug: string;
        name: string;
        category: string;
        count: number;
        project_count: number;
        package_count: number;
    }>;
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

export type PackageSummary = {
    id: string;
    slug: string;
    name: string;
    language: string;
    registry: string;
    status: 'active' | 'archived';
    short_description: string;
    repo_url: string | null;
    registry_url: string | null;
    docs_url: string | null;
    shipped_date: string | null;
    capabilities: Array<{ slug: string; name: string; is_primary: boolean }>;
};

export type PackageDetail = Omit<PackageSummary, 'capabilities'> & {
    long_description: string | null;
    capabilities: Array<{
        slug: string;
        name: string;
        category: string;
        canonical_slug: string;
        is_primary: boolean;
    }>;
};

const STANDARD_TAGS = ['codex:heatmap', 'codex:capability-matrix', 'codex:reports:gaps', 'codex:reports:bullets', 'codex:search:index'];

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

export async function getCapabilityCategoryMatrix() {
    return codexFetch<{ data: CapabilityCategoryMatrix }>(
        '/api/v1/capabilities/category-matrix',
        { tags: STANDARD_TAGS },
    );
}

// Client-side drill-down fetcher. The modal that consumes this is a
// client component, so the request runs in the browser — codexFetch's
// loopback URL + Host-header trick doesn't apply here. CORS is already
// allow-listed for codex.philiprehberger.com (config/cors.php on the
// Laravel side), so a direct fetch to the public API works.
export async function getDrillDown(query: DrillDownQuery): Promise<DrillDownResult> {
    const host = process.env.NEXT_PUBLIC_CODEX_API_HOST ?? 'api.codex.philiprehberger.com';
    const search = new URLSearchParams(query as Record<string, string>).toString();
    const url = `https://${host}/api/v1/drill-down?${search}`;
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!response.ok) {
        const detail = await response.text().catch(() => '');
        throw new CodexApiError(response.status, url, `${response.status} from ${url}: ${detail.slice(0, 200)}`);
    }
    const body = (await response.json()) as { data: DrillDownResult };
    return body.data;
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

export async function listPackages(query: Record<string, string> = {}) {
    const search = new URLSearchParams(query).toString();
    const path = `/api/v1/packages${search ? `?${search}` : ''}`;
    return codexFetch<Paginated<PackageSummary>>(path, { tags: STANDARD_TAGS });
}

export async function getPackage(slug: string) {
    return codexFetch<{ data: PackageDetail }>(
        `/api/v1/packages/${slug}`,
        { tags: STANDARD_TAGS },
    );
}
