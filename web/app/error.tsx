'use client';

import { useEffect } from 'react';
import Link from 'next/link';

export default function GlobalError({ error, reset }: { error: Error & { digest?: string }; reset: () => void }) {
    useEffect(() => {
        // Phase 8 wires Sentry here. For now, console.error keeps the
        // stack trace in dev logs without leaking it client-side.
        if (process.env.NODE_ENV !== 'production') {
            // eslint-disable-next-line no-console
            console.error(error);
        }
    }, [error]);

    return (
        <div className="py-20 text-center">
            <h1 className="text-3xl font-bold tracking-tight text-(--color-ink)">Something broke</h1>
            <p className="mt-3 text-(--color-ink-dim) max-w-md mx-auto">
                The dashboard couldn&apos;t render this page. The error has been logged.
            </p>
            <div className="mt-6 flex justify-center gap-4 text-sm">
                <button
                    onClick={() => reset()}
                    className="rounded bg-(--color-ink) px-4 py-2 text-(--color-paper)"
                >
                    Try again
                </button>
                <Link
                    href="/"
                    className="rounded border border-(--color-paper-dim) px-4 py-2 text-(--color-ink) no-underline"
                >
                    Home
                </Link>
            </div>
        </div>
    );
}
