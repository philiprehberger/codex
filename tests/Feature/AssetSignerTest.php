<?php

namespace Tests\Feature;

use App\Services\AssetSigner;
use Tests\TestCase;

/**
 * Phase 7 — AssetSigner key separation + verify rules.
 *
 * The asset signing key is DELIBERATELY separate from APP_KEY so that
 * a leaked signature can be rotated without invalidating sessions or
 * breaking encrypted-at-rest Eloquent columns. This test pins the
 * separation by signing with the codex key and demonstrating that an
 * APP_KEY-signed payload doesn't verify.
 */
class AssetSignerTest extends TestCase
{
    public function test_signs_and_verifies_with_current_key(): void
    {
        config(['codex.asset_signing.keys' => 'current-key-XXXXXXXXXXXXXXXXXXXXXXXX']);

        $signer = new AssetSigner;
        $signed = $signer->sign('01HASSETULID0000000000000Z');

        $this->assertTrue($signer->verify('01HASSETULID0000000000000Z', $signed['exp'], $signed['sig']));
    }

    public function test_rejects_wrong_signature(): void
    {
        config(['codex.asset_signing.keys' => 'current-key-XXXXXXXXXXXXXXXXXXXXXXXX']);

        $signer = new AssetSigner;
        $this->assertFalse($signer->verify('01HASSETULID0000000000000Z', time() + 3600, 'not-a-valid-sig'));
    }

    public function test_rejects_expired_timestamp(): void
    {
        config(['codex.asset_signing.keys' => 'current-key-XXXXXXXXXXXXXXXXXXXXXXXX']);

        $signer = new AssetSigner;
        $expired = time() - 3600;
        $signed = $signer->sign('01HASSETULID0000000000000Z', $expired);

        $this->assertFalse($signer->verify('01HASSETULID0000000000000Z', $expired, $signed['sig']));
    }

    public function test_signature_does_not_match_app_key_signing(): void
    {
        // Pin the codex key + APP_KEY to demonstrably distinct values.
        config(['codex.asset_signing.keys' => 'codex-key-YYYYYYYYYYYYYYYYYYYYYYYY']);
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        $signer = new AssetSigner;
        $signed = $signer->sign('01HASSETULID0000000000000Z');

        // Compute what the signature WOULD be if APP_KEY was used.
        $appKey = config('app.key');
        $canonical = '01HASSETULID0000000000000Z|'.$signed['exp'];
        $appKeyBinary = hash_hmac('sha256', $canonical, $appKey, true);
        $appKeySig = rtrim(strtr(base64_encode($appKeyBinary), '+/', '-_'), '=');

        $this->assertNotSame($appKeySig, $signed['sig'], 'codex key must produce a different signature than APP_KEY');
        $this->assertFalse(
            $signer->verify('01HASSETULID0000000000000Z', $signed['exp'], $appKeySig),
            'APP_KEY-signed payload must NOT verify against the codex key',
        );
    }

    public function test_no_flap_rotation_accepts_both_current_and_previous_keys(): void
    {
        // Start with a single secret.
        config(['codex.asset_signing.keys' => 'old-key-AAAAAAAAAAAAAAAAAAAAAAAAAA']);
        $signer = new AssetSigner;
        $signed = $signer->sign('01HASSETULID0000000000000Z');

        // Rotation step 1: append new key.
        config(['codex.asset_signing.keys' => 'new-key-BBBBBBBBBBBBBBBBBBBBBBBBBB,old-key-AAAAAAAAAAAAAAAAAAAAAAAAAA']);
        $signer = new AssetSigner;
        $this->assertTrue(
            $signer->verify('01HASSETULID0000000000000Z', $signed['exp'], $signed['sig']),
            'In-flight signatures from the old key must verify during rotation',
        );
    }
}
