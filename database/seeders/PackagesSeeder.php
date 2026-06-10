<?php

namespace Database\Seeders;

use App\Models\Architecture;
use App\Models\Capability;
use App\Models\Deliverable;
use App\Models\DesignStyle;
use App\Models\Industry;
use App\Models\Project;
use App\Models\Technology;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Per plan §4.3: packages are NOT one row each — the portfolio's 630
 * packages would drown the heatmap. Instead, one row per
 *   capability cluster × language.
 *
 * Each row is tagged with the language (technology) + the capability
 * cluster + an industry of "developer-tools". The /about page on the
 * Next.js dashboard (Phase 6) calls out the reduction with a link to
 * https://philiprehberger.com/open-source-packages for the full
 * per-package list.
 */
class PackagesSeeder extends BaseSeeder
{
    public function run(): void
    {
        foreach ($this->rows() as $row) {
            $this->seedOne($row);
        }
    }

    /** @param array<string, mixed> $row */
    private function seedOne(array $row): void
    {
        DB::transaction(function () use ($row) {
            $project = Project::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'project_type' => 'package',
                    'status' => 'shipped',
                    'visibility' => 'public',
                    'short_description' => $row['short_description'],
                    'long_description' => $row['long_description'] ?? null,
                    'long_description_reviewed' => true,
                    'shipped_date' => $row['shipped_date'] ?? '2025-12-01',
                    'hours_estimated' => $row['hours'] ?? 8,
                    'hours_actual' => $row['hours'] ?? 8,
                    'team_size' => 1,
                ],
            );

            $project->capabilities()->sync(Capability::whereIn('slug', $row['capability_slugs'])->pluck('id')->all());
            $project->technologies()->sync(Technology::whereIn('slug', $row['technology_slugs'])->pluck('id')->all());
            $project->industries()->sync(Industry::whereIn('slug', ['developer-tools'])->pluck('id')->all());
            $project->architectures()->sync(Architecture::whereIn('slug', ['monolith'])->pluck('id')->all());
            $project->deliverables()->sync(Deliverable::whereIn('slug', [$row['deliverable_slug']])->pluck('id')->all());
            $project->designStyles()->sync(DesignStyle::whereIn('slug', ['developer-focused'])->pluck('id')->all());

            DB::table('project_technologies')
                ->where('project_id', $project->id)
                ->where('technology_id', Technology::where('slug', $row['language_slug'])->value('id'))
                ->update(['is_primary' => true]);
            DB::table('project_capabilities')
                ->where('project_id', $project->id)
                ->where('capability_id', Capability::where('slug', $row['capability_slugs'][0])->value('id'))
                ->update(['is_primary' => true]);
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(): array
    {
        return [
            // ── PHP / Laravel
            $this->cluster('php-laravel-feature-flags', 'PHP / Laravel — Feature Flags', 'laravel-feature-flags + php-rule-engine — typed feature flags with rollout percentages and environment-aware rule evaluation.', 'php', ['php', 'laravel'], ['rule-engine', 'authorization'], 'composer-package'),
            $this->cluster('php-laravel-caching', 'PHP / Laravel — Caching', 'laravel-cache-helpers + tagged-cache-bridge — driver-agnostic cache helpers with explicit-key invalidation.', 'php', ['php', 'laravel'], ['caching'], 'composer-package'),
            $this->cluster('php-laravel-rate-limit', 'PHP / Laravel — Rate Limiting', 'Per-IP and per-user sliding-window rate limiter with RFC 7807 throttle responses.', 'php', ['php', 'laravel'], ['rate-limiting'], 'composer-package'),
            $this->cluster('php-laravel-audit', 'PHP / Laravel — Audit Logging', 'Eloquent audit logger with diff capture, retention sweep, and Filament integration.', 'php', ['php', 'laravel'], ['observability'], 'composer-package'),
            $this->cluster('php-laravel-webhooks', 'PHP / Laravel — Webhook Tooling', 'HMAC signing + verification helpers, idempotency-key middleware, retry-policy wrapper.', 'php', ['php', 'laravel'], ['webhook-delivery', 'webhook-receiving'], 'composer-package'),

            // ── PHP standalone
            $this->cluster('php-rules-engine', 'PHP — Rule Engine', 'Standalone rule engine for IF/THEN evaluation against arbitrary records.', 'php', ['php'], ['rule-engine'], 'composer-package'),
            $this->cluster('php-spam-signals', 'PHP — Spam Scoring Signals', 'Modular spam-scoring signals (honeypot, timing, IP reputation, content patterns).', 'php', ['php'], ['content-moderation'], 'composer-package'),

            // ── TypeScript / Node
            $this->cluster('ts-cache-kit', 'TypeScript — Caching', 'cache-kit + memo-ts + ts-memo-map — typed in-memory caches with TTL and LRU strategies.', 'typescript', ['typescript', 'javascript'], ['caching'], 'npm-package'),
            $this->cluster('ts-fetch-utils', 'TypeScript — Fetch Utilities', 'Retry policies, exponential backoff, circuit breakers, request deduplication for fetch().', 'typescript', ['typescript'], ['rest-api'], 'npm-package'),
            $this->cluster('ts-hmac', 'TypeScript — HMAC Signing', 'Webhook signature signing + verification helpers for browser + Node.', 'typescript', ['typescript'], ['webhook-delivery', 'webhook-receiving'], 'npm-package'),
            $this->cluster('ts-rate-limit-edge', 'TypeScript — Rate Limit (Edge)', 'Memory + Redis sliding-window rate limiters for Edge runtimes and Node servers.', 'typescript', ['typescript'], ['rate-limiting'], 'npm-package'),
            $this->cluster('ts-zod-helpers', 'TypeScript — Zod Helpers', 'Form-validation helpers, common shape factories, RFC 7807 problem-detail responses from Zod errors.', 'typescript', ['typescript', 'zod'], ['rest-api'], 'npm-package'),
            $this->cluster('ts-event-emitter', 'TypeScript — Typed Event Emitter', 'Strongly-typed event emitter with on/off/once and emit-with-payload narrowing.', 'typescript', ['typescript'], ['real-time-sync'], 'npm-package'),
            $this->cluster('ts-feature-flags', 'TypeScript — Feature Flag SDK', 'Client + server SDK for feature-flag evaluation with SSE updates.', 'typescript', ['typescript', 'react'], ['workflow-engine'], 'npm-package'),

            // ── Python
            $this->cluster('py-cache', 'Python — Caching', 'Functools-based cache with TTL, LRU, and disk-backed variants.', 'python', ['python'], ['caching'], 'composer-package'),
            $this->cluster('py-retry', 'Python — Retry / Backoff', 'Decorator-based retry policies, exponential backoff, jitter, circuit breakers.', 'python', ['python'], ['observability'], 'composer-package'),
            $this->cluster('py-feature-flags', 'Python — Feature Flag Client', 'Pennant SDK + reference implementation; SSE-based real-time updates.', 'python', ['python'], ['real-time-sync', 'rule-engine'], 'composer-package'),
            $this->cluster('py-webhooks', 'Python — Webhook Tooling', 'HMAC verification middleware for FastAPI / Flask; webhook-delivery helpers.', 'python', ['python', 'fastapi'], ['webhook-delivery', 'webhook-receiving'], 'composer-package'),

            // ── Go
            $this->cluster('go-circuitbreaker', 'Go — Circuit Breaker', 'Resilient circuit-breaker pattern with state transitions + Prometheus metrics.', 'go', ['go'], ['observability'], 'composer-package'),
            $this->cluster('go-retry-kit', 'Go — Retry / Backoff', 'Generic retry-kit with policy composition + jitter.', 'go', ['go'], ['observability'], 'composer-package'),
            $this->cluster('go-ratelimit', 'Go — Rate Limit', 'Token-bucket and sliding-window rate limiters for Go services.', 'go', ['go'], ['rate-limiting'], 'composer-package'),

            // ── WordPress
            $this->cluster('wp-form-helpers', 'WordPress — Form Helpers', 'Lightweight form-submission + lead-capture plugins.', 'php', ['php', 'wordpress'], ['lead-capture'], 'wordpress-plugin'),
            $this->cluster('wp-perf', 'WordPress — Performance Helpers', 'Page-cache invalidation, asset deferral, lazy-loading utilities.', 'php', ['php', 'wordpress'], ['caching', 'seo-optimisation'], 'wordpress-plugin'),
            $this->cluster('wp-seo-helpers', 'WordPress — SEO Helpers', 'Sitemap generation, OpenGraph tagging, structured-data injection.', 'php', ['php', 'wordpress'], ['seo-optimisation'], 'wordpress-plugin'),
            $this->cluster('wp-acf-helpers', 'WordPress — ACF Helpers', 'Advanced Custom Fields field-group helpers + Gutenberg block adapters.', 'php', ['php', 'wordpress', 'acf'], ['cms'], 'wordpress-plugin'),
        ];
    }

    /** @return array<string, mixed> */
    private function cluster(string $slug, string $name, string $description, string $languageSlug, array $technologySlugs, array $capabilitySlugs, string $deliverableSlug): array
    {
        return [
            'slug' => $slug,
            'name' => $name,
            'short_description' => $description,
            'language_slug' => $languageSlug,
            'technology_slugs' => $technologySlugs,
            'capability_slugs' => $capabilitySlugs,
            'deliverable_slug' => $deliverableSlug,
            'hours' => 12,
        ];
    }
}
