// HMAC-validated cache-buster called by the Laravel revalidation
// observer (Filament write → terminating() flush). Defences in order:
//   1. method check — 405 on non-POST
//   2. content-length cap — 413 over 1 KB
//   3. per-IP rate limit (in-memory LRU)
//   4. signature validation — reads req.text() BEFORE JSON.parse so the
//      byte-stable canonical body lines up with Laravel's
//      json_encode(payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
//   5. timestamp window — |now - ts| < 60s
//
// Secrets rotation is no-flap via the CODEX_REVALIDATE_SECRETS comma
// list. The verifier accepts ANY secret in the list; the writer uses the
// first. See infra/RECOVERY.md (Phase 8) for the rotation procedure.

import { NextRequest, NextResponse } from 'next/server';
import { revalidateTag } from 'next/cache';

export const dynamic = 'force-dynamic';
export const runtime = 'nodejs'; // crypto.timingSafeEqual + raw body access

const MAX_BODY_BYTES = 1024;
const TIMESTAMP_SKEW_SECONDS = 60;
const RATE_LIMIT_PER_MINUTE = 60;

// In-memory per-IP counter. Per-process — `instances: 1` pinned on
// codex-web in PM2 ecosystem.config.js (cluster mode would silently
// multiply the effective limit by core count).
const rateLimitBuckets = new Map<string, { count: number; resetAt: number }>();

function ipFor(req: NextRequest): string {
    return req.headers.get('x-forwarded-for')?.split(',')[0]?.trim() ?? 'unknown';
}

function withinRateLimit(ip: string): boolean {
    const now = Date.now();
    const bucket = rateLimitBuckets.get(ip);
    if (!bucket || bucket.resetAt < now) {
        rateLimitBuckets.set(ip, { count: 1, resetAt: now + 60_000 });
        return true;
    }
    if (bucket.count >= RATE_LIMIT_PER_MINUTE) return false;
    bucket.count++;
    return true;
}

function getSecrets(): string[] {
    const raw = process.env.CODEX_REVALIDATE_SECRETS ?? '';
    return raw
        .split(',')
        .map((s) => s.trim())
        .filter((s) => s.length > 0);
}

async function verifySignature(rawBody: string, signatureHeader: string | null): Promise<boolean> {
    if (!signatureHeader) return false;
    const match = signatureHeader.match(/^sha256=([0-9a-f]+)$/i);
    if (!match) return false;
    const providedHex = match[1].toLowerCase();

    const secrets = getSecrets();
    if (secrets.length === 0) return false;

    const { createHmac, timingSafeEqual } = await import('crypto');
    const providedBuf = Buffer.from(providedHex, 'hex');
    if (providedBuf.length !== 32) return false;

    for (const secret of secrets) {
        const expected = createHmac('sha256', secret).update(rawBody, 'utf8').digest();
        try {
            if (timingSafeEqual(providedBuf, expected)) return true;
        } catch {
            // length mismatch — fall through
        }
    }
    return false;
}

export async function POST(req: NextRequest) {
    // 2. content-length cap
    const declaredLength = parseInt(req.headers.get('content-length') ?? '0', 10);
    if (declaredLength > MAX_BODY_BYTES) {
        return NextResponse.json({ error: 'Body too large' }, { status: 413 });
    }

    // 3. per-IP rate limit
    if (!withinRateLimit(ipFor(req))) {
        return NextResponse.json({ error: 'Rate limited' }, { status: 429 });
    }

    // Read raw body BEFORE JSON.parse — load-bearing for HMAC verification.
    const rawBody = await req.text();
    if (rawBody.length > MAX_BODY_BYTES) {
        return NextResponse.json({ error: 'Body too large' }, { status: 413 });
    }

    // 4. signature validation
    const signature = req.headers.get('x-codex-signature');
    if (!(await verifySignature(rawBody, signature))) {
        return NextResponse.json({ error: 'Invalid signature' }, { status: 401 });
    }

    // 5. timestamp window
    let payload: { tag?: unknown; ts?: unknown };
    try {
        payload = JSON.parse(rawBody);
    } catch {
        return NextResponse.json({ error: 'Invalid JSON' }, { status: 400 });
    }
    const tag = typeof payload.tag === 'string' ? payload.tag : null;
    const ts = typeof payload.ts === 'number' ? payload.ts : null;
    if (!tag || ts === null) {
        return NextResponse.json({ error: 'Missing tag or ts' }, { status: 400 });
    }
    const nowSec = Math.floor(Date.now() / 1000);
    if (Math.abs(nowSec - ts) > TIMESTAMP_SKEW_SECONDS) {
        return NextResponse.json({ error: 'Timestamp out of window' }, { status: 400 });
    }

    // Next.js 16 requires a profile/cache-life arg. We pass an empty
    // CacheLifeConfig — the tag is what carries the semantics; the
    // profile is for cache-life tuning that we don't need here.
    revalidateTag(tag, {});
    return new NextResponse(null, { status: 204 });
}

export async function GET() {
    return NextResponse.json({ error: 'Method not allowed' }, { status: 405 });
}

// All other verbs land in 405 via Next.js default.
