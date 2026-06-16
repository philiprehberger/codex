import './globals.css';
import Link from 'next/link';
import type { ReactNode } from 'react';
import SiteHeader from '@/components/SiteHeader';
import GoogleAnalytics from '@/components/analytics/GoogleAnalytics';

export const metadata = {
    title: 'Codex — portfolio intelligence dashboard',
    description:
        'Every project Philip Rehberger has built — catalogued, tagged by capability + industry + architecture, rendered as a heatmap and a gap report. The codex of every project, capability, and gap.',
    metadataBase: new URL('https://codex.philiprehberger.com'),
    openGraph: {
        title: 'Codex — portfolio intelligence dashboard',
        description: 'The codex of every project, capability, and gap.',
        url: 'https://codex.philiprehberger.com',
        siteName: 'Codex',
        type: 'website',
    },
    twitter: {
        card: 'summary_large_image',
        title: 'Codex — portfolio intelligence dashboard',
        description: 'The codex of every project, capability, and gap.',
    },
};

export default function RootLayout({ children }: { children: ReactNode }) {
    return (
        <html lang="en">
            <body>
                <SiteHeader />
                <main className="mx-auto max-w-6xl px-6 py-10">{children}</main>
                <footer className="border-t border-(--color-paper-dim) mt-20">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-8 text-sm text-(--color-ink-dim)">
                        <div>
                            Codex is a portfolio piece by{' '}
                            <a href="https://philiprehberger.com">Philip Rehberger</a>.
                        </div>
                        <div className="flex gap-6">
                            <a href="https://github.com/philiprehberger/codex">github</a>
                            <Link href="/about">methodology</Link>
                        </div>
                    </div>
                </footer>
                <GoogleAnalytics />
            </body>
        </html>
    );
}
