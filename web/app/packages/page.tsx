import Link from 'next/link';
import { listPackages, type PackageSummary } from '@/lib/codex-api';

export const revalidate = 3600;
export const metadata = {
    title: 'Packages — Codex',
    description: 'Open-source packages across PHP, TypeScript, Python, Ruby, Go, Rust, .NET, Kotlin, Dart, Swift, Elixir — all under the @philiprehberger namespace.',
};

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

/**
 * /packages — full catalogue, grouped by language.
 *
 * Fetches up to 1000 per page (we have ~623), so this is a single
 * fetch + client-side grouping. If the catalogue grows beyond 1000,
 * paginate via cursor; until then the simpler shape ships.
 */
export default async function PackagesPage() {
    const { data } = await listPackages({ per_page: '1000' });

    // Group by language; preserve a stable ordering by population.
    const byLanguage = data.reduce<Record<string, PackageSummary[]>>((acc, pkg) => {
        (acc[pkg.language] ??= []).push(pkg);
        return acc;
    }, {});

    const languages = Object.entries(byLanguage)
        .map(([lang, pkgs]) => ({ lang, count: pkgs.length, packages: pkgs }))
        .sort((a, b) => b.count - a.count);

    return (
        <div className="space-y-10">
            <header>
                <h1 className="text-3xl font-bold tracking-tight text-(--color-ink)">Packages</h1>
                <p className="mt-2 max-w-2xl text-(--color-ink-dim)">
                    {data.length} open-source packages under the
                    {' '}<code className="font-mono">@philiprehberger</code> namespace, across {languages.length} registries.
                    Listed here as their own catalogue so the project heatmap stays
                    project-scoped. Each package is its own GitHub repo.
                </p>
                <div className="mt-4 flex flex-wrap gap-2 text-sm">
                    {languages.map(({ lang, count }) => (
                        <a
                            key={lang}
                            href={`#${lang}`}
                            className="rounded border border-(--color-paper-dim) px-3 py-1 text-(--color-ink) no-underline hover:border-(--color-accent)"
                        >
                            {LANGUAGE_LABEL[lang] ?? lang}{' '}
                            <span className="text-xs text-(--color-ink-dim)">({count})</span>
                        </a>
                    ))}
                </div>
            </header>

            {languages.map(({ lang, packages }) => (
                <section key={lang} id={lang}>
                    <h2 className="text-xl font-semibold text-(--color-ink) mb-3">
                        {LANGUAGE_LABEL[lang] ?? lang}
                        <span className="ml-2 text-sm font-normal text-(--color-ink-dim)">({packages.length})</span>
                    </h2>
                    <ul className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        {packages.map((pkg) => (
                            <li key={pkg.slug}>
                                <Link
                                    href={`/packages/${pkg.slug}`}
                                    className="block rounded border border-(--color-paper-dim) bg-(--color-paper) p-3 no-underline hover:border-(--color-accent)"
                                >
                                    <div className="flex items-baseline justify-between gap-2">
                                        <div className="text-(--color-ink) font-medium font-mono text-sm">{pkg.name}</div>
                                        <span className="text-xs text-(--color-ink-dim) shrink-0">
                                            {REGISTRY_LABEL[pkg.registry] ?? pkg.registry}
                                        </span>
                                    </div>
                                    <p className="mt-1 text-sm text-(--color-ink-dim)">{pkg.short_description}</p>
                                    {pkg.capabilities.length > 0 && (
                                        <div className="mt-2 flex flex-wrap gap-1">
                                            {pkg.capabilities.slice(0, 4).map((c) => (
                                                <span
                                                    key={c.slug}
                                                    className={
                                                        'rounded px-1.5 py-0.5 text-xs ' +
                                                        (c.is_primary
                                                            ? 'bg-(--color-accent) text-white'
                                                            : 'bg-(--color-accent-soft) text-(--color-accent)')
                                                    }
                                                >
                                                    {c.name}
                                                </span>
                                            ))}
                                        </div>
                                    )}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            ))}
        </div>
    );
}
