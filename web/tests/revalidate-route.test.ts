import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createHmac } from 'node:crypto';

// Mock next/cache so revalidateTag becomes a spy.
const revalidateTagMock = vi.fn();
vi.mock('next/cache', () => ({
    revalidateTag: (...args: unknown[]) => revalidateTagMock(...args),
}));

import { POST, GET } from '@/../app/api/revalidate/route';

const SECRET = 'unit-test-revalidate-secret';

function makeBody(tag: string, ts: number) {
    // BYTE-EXACT to match the Laravel-side json_encode with the same flags.
    return JSON.stringify({ tag, ts });
}

function sign(body: string, secret: string = SECRET) {
    return 'sha256=' + createHmac('sha256', secret).update(body).digest('hex');
}

function makeRequest(method: 'GET' | 'POST', body?: string, signature?: string): Request {
    const headers: Record<string, string> = {};
    if (body) {
        headers['Content-Type'] = 'application/json';
        headers['Content-Length'] = String(body.length);
    }
    if (signature) headers['X-Codex-Signature'] = signature;
    return new Request('http://localhost/api/revalidate', {
        method,
        headers,
        body: body ?? null,
    });
}

describe('/api/revalidate', () => {
    beforeEach(() => {
        process.env.CODEX_REVALIDATE_SECRETS = SECRET;
        revalidateTagMock.mockReset();
    });

    it('GET returns 405', async () => {
        const res = await GET();
        expect(res.status).toBe(405);
    });

    it('POST with no signature returns 401', async () => {
        const body = makeBody('codex:heatmap', Math.floor(Date.now() / 1000));
        const res = await POST(makeRequest('POST', body) as Parameters<typeof POST>[0]);
        expect(res.status).toBe(401);
    });

    it('POST with bad signature returns 401', async () => {
        const body = makeBody('codex:heatmap', Math.floor(Date.now() / 1000));
        const res = await POST(makeRequest('POST', body, 'sha256=deadbeef') as Parameters<typeof POST>[0]);
        expect(res.status).toBe(401);
    });

    it('POST with stale timestamp returns 400', async () => {
        const body = makeBody('codex:heatmap', 1_000_000); // far in the past
        const sig = sign(body);
        const res = await POST(makeRequest('POST', body, sig) as Parameters<typeof POST>[0]);
        expect(res.status).toBe(400);
    });

    it('POST with valid signature + fresh ts returns 204 + calls revalidateTag', async () => {
        const body = makeBody('codex:heatmap', Math.floor(Date.now() / 1000));
        const sig = sign(body);
        const res = await POST(makeRequest('POST', body, sig) as Parameters<typeof POST>[0]);
        expect(res.status).toBe(204);
        expect(revalidateTagMock).toHaveBeenCalledWith('codex:heatmap', {});
    });

    it('POST over 1 KB body cap returns 413', async () => {
        const padding = 'x'.repeat(1100);
        const body = JSON.stringify({ tag: 'codex:heatmap', ts: Math.floor(Date.now() / 1000), padding });
        const sig = sign(body);
        const res = await POST(makeRequest('POST', body, sig) as Parameters<typeof POST>[0]);
        expect(res.status).toBe(413);
    });

    it('no-flap rotation: accepts a signature from the SECOND secret in the list', async () => {
        const ts = Math.floor(Date.now() / 1000);
        const body = makeBody('codex:heatmap', ts);
        const oldSig = sign(body, 'old-secret');
        process.env.CODEX_REVALIDATE_SECRETS = 'new-secret,old-secret';

        const res = await POST(makeRequest('POST', body, oldSig) as Parameters<typeof POST>[0]);
        expect(res.status).toBe(204);
    });
});
