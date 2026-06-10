<?php

namespace Tests\Feature;

use App\Services\RevalidateClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 7 — HMAC byte-stability + canonical-body discipline.
 *
 * The Laravel-side body is BYTE-EXACT
 *   json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
 * and is sent via Http::withBody($body, 'application/json') — never
 * Http::post(['tag' => …]), which re-serialises the array and breaks
 * signature verification non-obviously. This test pins the wire format.
 */
class RevalidateClientSignatureTest extends TestCase
{
    public function test_signs_with_first_secret_and_posts_canonical_body(): void
    {
        Config::set('codex.revalidate.secrets', 'current-secret,old-secret');
        Config::set('codex.revalidate.next_revalidate_url', 'https://codex.example.test');

        $captured = [];
        Http::fake(function ($request) use (&$captured) {
            $captured['body'] = $request->body();
            $captured['signature'] = $request->header('X-Codex-Signature')[0] ?? '';

            return Http::response(null, 204);
        });

        $result = (new RevalidateClient)->post('codex:heatmap');

        $this->assertTrue($result, 'post should return true on 2xx response');
        $this->assertArrayHasKey('body', $captured);

        $decoded = json_decode($captured['body'], true);
        $this->assertIsArray($decoded);
        $this->assertSame('codex:heatmap', $decoded['tag']);
        $this->assertIsInt($decoded['ts']);

        // Signature header = sha256=<hex> over the raw body with the FIRST secret.
        $this->assertStringStartsWith('sha256=', $captured['signature']);
        $expected = 'sha256='.hash_hmac('sha256', $captured['body'], 'current-secret');
        $this->assertSame($expected, $captured['signature']);
    }

    public function test_returns_false_when_no_url_configured(): void
    {
        Config::set('codex.revalidate.secrets', 'secret');
        Config::set('codex.revalidate.next_revalidate_url', null);

        Http::fake();

        $this->assertFalse((new RevalidateClient)->post('codex:heatmap'));
    }

    public function test_returns_false_when_no_secret_configured(): void
    {
        Config::set('codex.revalidate.secrets', '');
        Config::set('codex.revalidate.next_revalidate_url', 'https://codex.example.test');

        Http::fake();

        $this->assertFalse((new RevalidateClient)->post('codex:heatmap'));
    }
}
