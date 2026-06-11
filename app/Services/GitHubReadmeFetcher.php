<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pulls the README content for a GitHub repo via the v3 API endpoint
 * `/repos/{owner}/{repo}/readme`. That endpoint returns the default-
 * branch README regardless of filename (README.md, README.rst,
 * README.txt, …) and base64-encodes the content so binary edge cases
 * don't break the JSON.
 *
 * Reads CODEX_GITHUB_TOKEN if present — unauthenticated GitHub allows
 * 60 requests/hr/IP, authenticated allows 5000. The token only needs
 * `public_repo` read scope (or no scope for fine-grained tokens
 * restricted to public repos).
 *
 * The fetch returns null on 404 (deleted repo, private with no token,
 * or no README in the repo). Errors are logged but never throw — a
 * batch fetch over 600+ packages can't be derailed by one bad URL.
 */
class GitHubReadmeFetcher
{
    public function __construct(private readonly ?string $token = null) {}

    public static function fromConfig(): self
    {
        $token = config('services.github.token');

        return new self(is_string($token) && $token !== '' ? $token : null);
    }

    /**
     * Parse owner+repo from a github.com URL. Accepts any of:
     *   https://github.com/owner/repo
     *   https://github.com/owner/repo.git
     *   https://github.com/owner/repo/tree/main
     *   git@github.com:owner/repo.git
     *
     * Returns [owner, repo] or null if the URL doesn't look like GitHub.
     *
     * @return array{0: string, 1: string}|null
     */
    public function parseRepoUrl(string $repoUrl): ?array
    {
        $patterns = [
            '#^https?://github\.com/([^/]+)/([^/.]+)(?:\.git|/.*)?$#i',
            '#^git@github\.com:([^/]+)/([^/.]+)(?:\.git)?$#i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $repoUrl, $m) === 1) {
                return [$m[1], $m[2]];
            }
        }

        return null;
    }

    /**
     * Returns the raw markdown README for the repo, or null if the
     * repo / README doesn't exist or the API returned an error.
     */
    public function fetch(string $repoUrl): ?string
    {
        $parts = $this->parseRepoUrl($repoUrl);
        if ($parts === null) {
            return null;
        }
        [$owner, $repo] = $parts;

        $headers = [
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'codex-readme-fetcher',
        ];
        if ($this->token !== null) {
            $headers['Authorization'] = 'Bearer '.$this->token;
        }

        $response = Http::withHeaders($headers)
            ->timeout(15)
            ->get("https://api.github.com/repos/{$owner}/{$repo}/readme");

        if ($response->status() === 404) {
            return null;
        }
        if (! $response->ok()) {
            Log::warning('GitHub README fetch failed', [
                'owner' => $owner,
                'repo' => $repo,
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 200),
            ]);

            return null;
        }

        $body = $response->json();
        if (! is_array($body) || ! isset($body['content']) || ! is_string($body['content'])) {
            return null;
        }
        $encoding = isset($body['encoding']) && is_string($body['encoding']) ? $body['encoding'] : 'base64';
        if ($encoding !== 'base64') {
            Log::warning('GitHub README unexpected encoding', ['encoding' => $encoding]);

            return null;
        }
        $decoded = base64_decode(str_replace("\n", '', $body['content']), strict: true);
        if ($decoded === false) {
            return null;
        }

        return $decoded;
    }
}
