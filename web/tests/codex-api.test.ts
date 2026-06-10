import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { codexFetch, CodexApiError } from '@/lib/codex-api';

const ORIGINAL_FETCH = globalThis.fetch;

describe('codex-api fetch wrapper', () => {
    beforeEach(() => {
        process.env.CODEX_API_INTERNAL_URL = 'http://internal.test';
        process.env.NEXT_PUBLIC_CODEX_API_HOST = 'api.codex.philiprehberger.com';
    });

    afterEach(() => {
        globalThis.fetch = ORIGINAL_FETCH;
        vi.restoreAllMocks();
    });

    it('targets the internal URL and injects the public Host header', async () => {
        let capturedUrl: string | undefined;
        let capturedHeaders: Record<string, string> | undefined;

        globalThis.fetch = vi.fn(async (url, init) => {
            capturedUrl = url as string;
            capturedHeaders = init?.headers as Record<string, string>;
            return new Response(JSON.stringify({ data: 'ok' }), { status: 200 });
        }) as typeof fetch;

        await codexFetch<{ data: string }>('/api/v1/projects');

        expect(capturedUrl).toBe('http://internal.test/api/v1/projects');
        expect(capturedHeaders?.Host).toBe('api.codex.philiprehberger.com');
        expect(capturedHeaders?.Accept).toBe('application/json');
    });

    it('attaches revalidate + tags to the request', async () => {
        let capturedInit: RequestInit | undefined;
        globalThis.fetch = vi.fn(async (_url, init) => {
            capturedInit = init;
            return new Response(JSON.stringify({}), { status: 200 });
        }) as typeof fetch;

        await codexFetch('/api/v1/heatmap', { revalidate: 60, tags: ['codex:heatmap'] });

        // Next.js extends RequestInit with a `next` property at runtime.
        const next = (capturedInit as { next?: { revalidate?: number; tags?: string[] } }).next;
        expect(next?.revalidate).toBe(60);
        expect(next?.tags).toEqual(['codex:heatmap']);
    });

    it('throws CodexApiError on non-2xx', async () => {
        globalThis.fetch = vi.fn(async () =>
            new Response('Not found', { status: 404 })
        ) as typeof fetch;

        await expect(codexFetch('/api/v1/projects/does-not-exist')).rejects.toThrow(CodexApiError);
        await expect(codexFetch('/api/v1/projects/does-not-exist')).rejects.toMatchObject({ status: 404 });
    });
});
