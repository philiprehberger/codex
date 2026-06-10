import Link from 'next/link';

export const metadata = { title: 'Not found — Codex' };

export default function NotFound() {
    return (
        <div className="py-20 text-center">
            <h1 className="text-3xl font-bold tracking-tight text-(--color-ink)">Not found</h1>
            <p className="mt-3 text-(--color-ink-dim) max-w-md mx-auto">
                The slug doesn&apos;t match a project or capability in the catalogue.
                Browse{' '}
                <Link href="/projects" className="text-(--color-accent)">all projects</Link> or{' '}
                <Link href="/capabilities" className="text-(--color-accent)">the capability list</Link>.
            </p>
        </div>
    );
}
