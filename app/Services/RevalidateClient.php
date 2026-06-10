<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Posts a single revalidation tag to the Next.js dashboard's
 * /api/revalidate endpoint.
 *
 * The HTTP body is the BYTE-EXACT string
 *   json_encode(['tag' => $tag, 'ts' => $ts], JSON_UNESCAPED_SLASHES |
 *               JSON_UNESCAPED_UNICODE)
 * and is sent via Http::withBody($body, 'application/json') — never
 * Http::post(['tag' => …]), which re-serialises the array and breaks
 * signature verification non-obviously. The Next.js verifier reads
 * req.text() before JSON.parse for the same reason.
 *
 * Signature: hex(hmac_sha256(secret, raw_body)). Header:
 *   X-Codex-Signature: sha256=<hex>
 *
 * Rotation: CODEX_REVALIDATE_SECRETS is a comma-separated list. The
 * FIRST entry is the active write key. All entries are accepted on
 * verification (the Next.js side). Two-step rotation:
 *   1. Append the new secret as the SECOND entry on both hosts
 *      → verifier accepts both, writer still uses old
 *   2. Swap to new,old on writer first → drop old from verifier next
 *      deploy
 * No window where in-flight POSTs fail.
 *
 * Failure isolation: wrapped in a try/catch so a downed Next.js host
 * never blocks an admin write. On failure we log + accept the
 * fallback (3600s revalidate TTL on the dashboard).
 */
class RevalidateClient
{
    public function post(string $tag): bool
    {
        $secrets = $this->secrets();
        if ($secrets === []) {
            Log::warning('codex.revalidate.no_secret', ['tag' => $tag]);
            return false;
        }

        $url = $this->nextRevalidateUrl();
        if ($url === null) {
            return false;
        }

        $body = json_encode(
            ['tag' => $tag, 'ts' => time()],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
        $signature = 'sha256='.hash_hmac('sha256', $body, $secrets[0]);

        try {
            $response = Http::timeout(2)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Codex-Signature' => $signature,
                ])
                ->withBody($body, 'application/json')
                ->post($url);

            if (! $response->successful()) {
                Log::warning('codex.revalidate.unsuccessful', [
                    'tag' => $tag,
                    'status' => $response->status(),
                ]);
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::warning('codex.revalidate.exception', [
                'tag' => $tag,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /** @return array<int, string> */
    private function secrets(): array
    {
        $raw = (string) (Config::get('codex.revalidate.secrets')
            ?? env('CODEX_REVALIDATE_SECRETS', ''));

        return array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn (string $s) => $s !== '',
        ));
    }

    private function nextRevalidateUrl(): ?string
    {
        $base = Config::get('codex.next_revalidate_url')
            ?? env('CODEX_NEXT_REVALIDATE_URL');

        if (! $base) {
            return null;
        }
        return rtrim($base, '/').'/api/revalidate';
    }
}
