<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;

/**
 * Signs and verifies asset URLs for redacted/private project assets.
 *
 * Canonical signing string: "{ulid}|{exp}" — concatenated before HMAC
 * so a malicious caller can't substitute one valid signature into a
 * URL for a different asset. The output is base64url-encoded (URL-safe,
 * unpadded).
 *
 * KEY SEPARATION: Uses CODEX_ASSET_SIGNING_KEYS, NOT APP_KEY. Rationale:
 * APP_KEY encrypts session cookies, the `encrypted:` cast on Eloquent
 * attributes, and signed-route URLs that aren't asset-scoped. Rotating
 * APP_KEY invalidates every session and every encrypted-at-rest column.
 * A leaked asset signature should rotate this key WITHOUT logging users
 * out or breaking encrypted data.
 *
 * ROTATION: Comma-separated list. First entry is the active write key;
 * all entries are accepted on verify. Two-step no-flap rotation:
 *   1. Append new key as second entry on the verifier — old + new both
 *      accepted
 *   2. Swap to new,old on writer first; drop old from verifier on next
 *      deploy
 *
 * TTL: 2 hours by default — deliberately longer than the Next.js page
 * cache's revalidate=3600 so a user loading a cached page at
 * T = (TTL - epsilon) still has signed-URL headroom. Tunable via the
 * codex.asset_signing.ttl config (and env CODEX_ASSET_SIGNING_TTL).
 */
class AssetSigner
{
    public function sign(string $ulid, ?int $expiresAt = null): array
    {
        $expiresAt ??= time() + (int) Config::get('codex.asset_signing.ttl', 7200);
        $key = $this->writeKey();

        return [
            'sig' => $this->hmac($ulid, $expiresAt, $key),
            'exp' => $expiresAt,
        ];
    }

    public function verify(string $ulid, int $expiresAt, string $sig): bool
    {
        if ($expiresAt < time()) {
            return false;
        }

        foreach ($this->keys() as $key) {
            $expected = $this->hmac($ulid, $expiresAt, $key);
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }

    private function hmac(string $ulid, int $expiresAt, string $key): string
    {
        $canonical = $ulid.'|'.$expiresAt;
        $binary = hash_hmac('sha256', $canonical, $key, true);
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    private function writeKey(): string
    {
        $keys = $this->keys();
        if ($keys === []) {
            throw new \RuntimeException('codex.asset_signing.keys is empty — set CODEX_ASSET_SIGNING_KEYS.');
        }
        return $keys[0];
    }

    /** @return array<int, string> */
    private function keys(): array
    {
        $raw = (string) (Config::get('codex.asset_signing.keys')
            ?? env('CODEX_ASSET_SIGNING_KEYS', ''));

        return array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn (string $s) => $s !== '',
        ));
    }
}
