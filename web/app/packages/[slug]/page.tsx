import { notFound } from 'next/navigation';
import Link from 'next/link';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { CodexApiError, getPackage } from '@/lib/codex-api';

export const revalidate = 3600;

type RouteParams = { slug: string };

const LANGUAGE_LABEL: Record<string, string> = {
    typescript: 'TypeScript',
    php: 'PHP',
    python: 'Python',
    ruby: 'Ruby',
    go: 'Go',
    rust: 'Rust',
    dotnet: '.NET',
    kotlin: 'Kotlin',
    swift: 'Swift',
    dart: 'Dart',
    elixir: 'Elixir',
};

const REGISTRY_LABEL: Record<string, string> = {
    npm: 'npm',
    packagist: 'Packagist',
    pypi: 'PyPI',
    rubygems: 'RubyGems',
    cargo: 'crates.io',
    go: 'Go modules',
    nuget: 'NuGet',
    maven: 'Maven Central',
    pub: 'pub.dev',
    hex: 'hex.pm',
    swiftpm: 'Swift PM',
};

export async function generateMetadata({ params }: { params: Promise<RouteParams> }) {
    const { slug } = await params;
    try {
        const { data } = await getPackage(slug);
        return {
            title: `${data.name} — Codex`,
            description: data.short_description,
            openGraph: {
                title: data.name,
                description: data.short_description,
                type: 'article',
            },
            twitter: {
                card: 'summary_large_image',
                title: data.name,
                description: data.short_description,
            },
        };
    } catch {
        return { title: 'Package not found — Codex' };
    }
}

export default async function PackageDetailPage({ params }: { params: Promise<RouteParams> }) {
    const { slug } = await params;
    let pkg;
    try {
        const { data } = await getPackage(slug);
        pkg = data;
    } catch (e) {
        if (e instanceof CodexApiError && e.status === 404) notFound();
        throw e;
    }

    return (
        <article className="space-y-10">
            <header>
                <div className="text-xs text-(--color-ink-dim)">
                    <Link href="/packages" className="no-underline">Packages</Link>
                    <span className="mx-2">/</span>
                    <span>{LANGUAGE_LABEL[pkg.language] ?? pkg.language}</span>
                </div>
                <h1 className="mt-2 text-3xl font-bold tracking-tight text-(--color-ink) font-mono">
                    {pkg.name}
                </h1>
                <p className="mt-3 max-w-2xl text-(--color-ink-dim)">{pkg.short_description}</p>

                <div className="mt-4 flex flex-wrap gap-2 text-xs">
                    <span className="rounded bg-(--color-paper-dim) px-2 py-1 text-(--color-ink-dim)">
                        {LANGUAGE_LABEL[pkg.language] ?? pkg.language}
                    </span>
                    <span className="rounded bg-(--color-paper-dim) px-2 py-1 text-(--color-ink-dim)">
                        {REGISTRY_LABEL[pkg.registry] ?? pkg.registry}
                    </span>
                    {pkg.status === 'archived' && (
                        <span className="rounded bg-(--color-paper-dim) px-2 py-1 text-(--color-warn)">archived</span>
                    )}
                </div>

                <div className="mt-4 flex flex-wrap gap-2">
                    {pkg.repo_url && (
                        <a
                            href={pkg.repo_url}
                            className="rounded bg-(--color-ink) px-3 py-1.5 text-xs text-(--color-paper) no-underline"
                        >
                            GitHub
                        </a>
                    )}
                    {pkg.registry_url && (
                        <a
                            href={pkg.registry_url}
                            className="rounded border border-(--color-paper-dim) px-3 py-1.5 text-xs text-(--color-ink) no-underline"
                        >
                            {REGISTRY_LABEL[pkg.registry] ?? pkg.registry}
                        </a>
                    )}
                    {pkg.docs_url && (
                        <a
                            href={pkg.docs_url}
                            className="rounded border border-(--color-paper-dim) px-3 py-1.5 text-xs text-(--color-ink) no-underline"
                        >
                            Docs
                        </a>
                    )}
                </div>
            </header>

            {pkg.capabilities.length > 0 && (
                <section>
                    <h2 className="text-lg font-semibold text-(--color-ink) mb-3">Capabilities</h2>
                    <ul className="flex flex-wrap gap-2 text-sm">
                        {pkg.capabilities.map((c) => (
                            <li key={c.slug}>
                                <Link
                                    href={`/capabilities/${c.canonical_slug}`}
                                    className={
                                        'rounded px-2 py-1 no-underline ' +
                                        (c.is_primary
                                            ? 'bg-(--color-accent) text-white'
                                            : 'bg-(--color-accent-soft) text-(--color-accent)')
                                    }
                                >
                                    {c.name}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {pkg.long_description && (
                <section>
                    <h2 className="text-lg font-semibold text-(--color-ink) mb-3">Details</h2>
                    <div className="prose">
                        {pkg.long_description.split('\n\n').map((para, i) => (
                            <p key={i}>{para}</p>
                        ))}
                    </div>
                </section>
            )}

            {pkg.readme_markdown && (
                <section>
                    <h2 className="text-lg font-semibold text-(--color-ink) mb-3">README</h2>
                    <div className="prose prose-readme">
                        <ReactMarkdown remarkPlugins={[remarkGfm]}>
                            {pkg.readme_markdown}
                        </ReactMarkdown>
                    </div>
                </section>
            )}
        </article>
    );
}
