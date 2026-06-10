# Codex API conventions

Reference for anyone integrating against `api.codex.philiprehberger.com/api/v1/*`. Read together with `docs/architecture.md` (the why-mod_php and why-no-PITR notes) and `docs/invariants.md` (the cross-cutting model-layer invariants).

## ULIDs as identifiers; slugs as URL identifiers

Every dashboard-rendered table uses `CHAR(26)` ULID primary keys, stored under `utf8mb4_bin` collation so Crockford-cased ULIDs and accented slugs compare as distinct values. The two are different things and conflating them is a known SDK pitfall:

- **`id` (ULID)** is the internal, stable client-facing handle. It's the row's identity for cross-payload correlation and for the heatmap `cells[]` arrays. ULIDs are lexicographically time-ordered + unique — use them when you need a stable reference that survives a rename.
- **`slug`** is the URL identifier. SEO + memorability live here. `GET /api/v1/projects/{slug}` and `GET /api/v1/capabilities/{slug}` accept the slug.

ULIDs are **not** secrets. They appear in payloads, can be enumerated by the time of creation, and protect nothing. Access control is via the signed-URL HMAC on `/api/v1/assets/{ulid}` — the ULID itself is just the handle.

## RFC 7807 problem details

Every error response uses `application/problem+json`:

```json
{
  "type": "about:blank",
  "title": "Invalid request",
  "status": 422,
  "detail": "Unknown filter parameter(s): foo, bar",
  "instance": "/api/v1/projects"
}
```

Status codes preserved from the originating exception via `ProblemResponse::statusFor()`:

| Code | Exception |
|------|-----------|
| 401  | `AuthenticationException` |
| 403  | `AuthorizationException`, `abort(403)` |
| 404  | `ModelNotFoundException`, `NotFoundHttpException`, `abort(404)` |
| 405  | `MethodNotAllowedHttpException` |
| 410  | `abort(410)` (unversioned `/api/*`) |
| 422  | `ValidationException` (unknown filter key, invalid slug, etc) |
| 429  | `ThrottleRequestsException` |
| 5xx  | `HttpException` (uses its own status) — anything else collapses to 500 |

## Cursor pagination — ordered by `id`, never `created_at`

`GET /api/v1/projects` uses `Builder::cursorPaginate()` ordered by **ULID `id`**. Reason: mass-seeded rows can collide on `created_at` (same-second timestamps), which causes `cursorPaginate` to skip or duplicate. ULIDs are unique and time-ordered, so `orderBy('id')` is both stable and chronological.

Response shape:

```json
{
  "data": [ … ],
  "meta": {
    "next_cursor": "eyJpZCI6IjAxSC4uLn0=",
    "prev_cursor": null,
    "per_page": 25
  }
}
```

Forward through `?cursor={next_cursor}`. Page-based pagination is not exposed in v1 — easier to add later than to retract.

## Filter allow-list — 422 on unknown query keys (Phase 1)

`GET /api/v1/projects` rejects any query key not in the allow-list:

```
capability, industry, type, architecture, year, cursor, per_page
```

Stance is **strict-by-default** in Phase 1 (`config('codex.api.strict_query_keys') = true`). The lone caller is the Next.js dashboard; typos surface immediately as a 422 rather than silently filtering nothing.

**Phase 2 flip planned**: industry-standard SDKs (Stripe, GitHub, AWS) silently ignore unknown query keys so older clients can roll forward against newer APIs. Once Codex ships a public SDK story, this flips via `config('codex.api.strict_query_keys') = false` and unknown keys become no-ops. Documented here so the change isn't a surprise.

Filter values are constrained to ULID / slug shape (`alpha_dash` + length limits) — no dynamic column names ever reach the query builder. `?column=DROP TABLE` and `?capability=DROP TABLE` both 422.

## Sparse heatmap payload

`GET /api/v1/capabilities/heatmap` returns:

```json
{
  "data": {
    "capabilities": [{"id": "ulid", "slug": "auth", "name": "Authentication", "category": "UserMgmt", "count": 28}],
    "projects": [{"id": "ulid", "slug": "pennant", "name": "Pennant", "type": "demo"}],
    "cells": [{"capability_id": "ulid", "project_id": "ulid", "is_primary": true}]
  }
}
```

**Sparse, not dense.** At 45 caps × 71 projects × ~6 cells per project, that's ~250 cells (~30KB gzipped) vs ~50KB for a dense 3,200-cell matrix. The Next.js renderer materialises the dense view client-side.

Cells reference capabilities by their **canonical id** — aliased capabilities (`canonical_id IS NOT NULL`) don't appear as heatmap rows; their pivot rows roll up into the canonical via `COALESCE(canonical_id, id)` in the aggregation join. This is the load-bearing "Read-side resolution is always one hop" rule.

## Signed-URL assets — separate signing key

`GET /api/v1/assets/{ulid}?sig=…&exp=…` serves redacted / private project assets. The signature is HMAC-SHA256 over the canonical `"{ulid}|{exp}"` string with **`CODEX_ASSET_SIGNING_KEYS`**, base64url-encoded.

**Key separation matters**: `APP_KEY` encrypts sessions + every `encrypted:` Eloquent cast. Rotating `APP_KEY` invalidates all sessions and breaks encrypted-at-rest columns. `CODEX_ASSET_SIGNING_KEYS` is independent — leaked-signature rotation has no blast radius beyond the asset surface.

TTL defaults to 2 hours (configurable via `CODEX_ASSET_SIGNING_TTL`) — deliberately longer than the Next.js page cache `revalidate: 3600` so a page cached at `T = TTL - epsilon` still has signed-URL headroom.

Rotation is a no-flap two-step (`current,new` on both hosts → swap to `new,old` on writer → drop `old` from verifier). See `infra/RECOVERY.md` once Phase 8 ships.

## CORS — browser-convention rejection

`config/cors.php` allow-lists `https://codex.philiprehberger.com` plus the local-dev origins. **For non-allow-listed origins we omit the `Access-Control-Allow-Origin` header** (browser-spec rejection) rather than returning 403.

A 403 surfaces as an opaque CORS error in DevTools and trips a Phase 2 SDK consumer probing a wrong subdomain. Omitting ACAO lets the browser block the response with the proper "this origin isn't allowed" signal.

## Rate limiting

| Endpoint | Limiter | Limit |
|----------|---------|-------|
| `/api/v1/projects`, `/api/v1/capabilities`, `/api/v1/assets/*` | `codex.api` | 60 / min / IP |
| `/api/v1/capabilities/heatmap`, `/api/v1/reports/*`, `/api/v1/search/index` | `codex.api-heavy` | 20 / min / IP |
| `/up`, `/up/diagnostics`, `/up/queue` | none | unlimited |
| `/admin/login` | `codex.admin-login` | 5 / min / IP + 10 / min / IP+email |

`429` responses include `Retry-After` headers and an RFC 7807 `application/problem+json` body.

## Versioning

`/api/v1/*` baked in from Day 1. Unversioned `/api/*` returns 410 Gone with a problem-detail body pointing at the versioned URL. When `/api/v2/*` lands (not currently planned), v1 stays live indefinitely — there's no implicit "always serve the latest" route.

The controller namespace is `App\Http\Controllers\Api\V1\*` so v2 lives in a sibling `V2\*` directory without conflict.
