<?php

namespace Database\Seeders;

use App\Models\Architecture;
use App\Models\Capability;
use App\Models\Deliverable;
use App\Models\DesignStyle;
use App\Models\Industry;
use App\Models\Project;
use App\Models\ProjectMetric;
use App\Models\Technology;
use Illuminate\Support\Facades\DB;

/**
 * Dogfood the real portfolio. One row per income-ops bundle, manually
 * tagged. updateOrCreate by slug + sync() on every pivot — re-running
 * converges to the exact set defined here, dropping admin-added tags
 * (that's exactly why BaseSeeder + SeederGuardServiceProvider exist;
 * production never re-seeds).
 *
 * Source: ~/projects/income-ops/projects/<slug>/ via the
 * codex:sync-bundle-fixtures command (Phase 4 §7). The CI fixture path
 * lives at database/fixtures/portfolio-bundles/<slug>.md but for the
 * dev seed we use the in-array descriptions below — fixtures take over
 * when seeding from CI without the income-ops directory.
 */
class DemoProjectsSeeder extends BaseSeeder
{
    public function run(): void
    {
        foreach ($this->projects() as $row) {
            $this->seedOne($row);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function seedOne(array $row): void
    {
        DB::transaction(function () use ($row) {
            $project = Project::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'project_type' => $row['project_type'],
                    'status' => $row['status'],
                    'visibility' => $row['visibility'],
                    'repo_url' => $row['repo_url'] ?? null,
                    'live_url' => $row['live_url'] ?? null,
                    'docs_url' => $row['docs_url'] ?? null,
                    'short_description' => $row['short_description'],
                    'long_description' => $row['long_description'] ?? null,
                    'long_description_reviewed' => true,
                    'shipped_date' => $row['shipped_date'] ?? null,
                    'hours_estimated' => $row['hours_estimated'] ?? null,
                    'hours_actual' => $row['hours_actual'] ?? null,
                    'team_size' => 1,
                ],
            );

            // Pivots — sync() ensures byte-identical state across re-runs.
            $project->capabilities()->sync($this->ids(Capability::class, $row['capability_slugs'] ?? []));
            $this->setPrimary($project, 'capabilities', $row['primary_capability'] ?? null);

            $project->technologies()->sync($this->ids(Technology::class, $row['technology_slugs'] ?? []));
            $this->setPrimary($project, 'technologies', $row['primary_technology'] ?? null);

            $project->industries()->sync($this->ids(Industry::class, $row['industry_slugs'] ?? []));
            $project->architectures()->sync($this->ids(Architecture::class, $row['architecture_slugs'] ?? []));
            $project->deliverables()->sync($this->ids(Deliverable::class, $row['deliverable_slugs'] ?? []));
            $project->designStyles()->sync($this->ids(DesignStyle::class, $row['design_style_slugs'] ?? []));

            // One metric snapshot per project — Phase 4 Day-0 baseline.
            if (isset($row['metrics'])) {
                ProjectMetric::updateOrCreate(
                    [
                        'project_id' => $project->id,
                        'recorded_at' => $row['metrics']['recorded_at'] ?? now()->format('Y-m-d'),
                    ],
                    array_merge(['notes' => null], $row['metrics']),
                );
            }
        });
    }

    /**
     * @param  class-string  $model
     * @param  array<int, string>  $slugs
     * @return array<int, string>
     */
    private function ids(string $model, array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        return $model::whereIn('slug', $slugs)->pluck('id')->all();
    }

    private function setPrimary(Project $project, string $relation, ?string $slug): void
    {
        if (! $slug) {
            return;
        }
        $tagId = match ($relation) {
            'capabilities' => Capability::where('slug', $slug)->value('id'),
            'technologies' => Technology::where('slug', $slug)->value('id'),
            default => null,
        };
        if (! $tagId) {
            return;
        }
        $table = match ($relation) {
            'capabilities' => 'project_capabilities',
            'technologies' => 'project_technologies',
        };
        $fk = match ($relation) {
            'capabilities' => 'capability_id',
            'technologies' => 'technology_id',
        };
        DB::table($table)->where('project_id', $project->id)->update(['is_primary' => false]);
        DB::table($table)->where('project_id', $project->id)->where($fk, $tagId)->update(['is_primary' => true]);
    }

    /** @return array<int, array<string, mixed>> */
    private function projects(): array
    {
        $stack = [
            'capability_slugs' => [],
            'industry_slugs' => ['developer-tools'],
            'architecture_slugs' => ['api-as-product', 'monolith'],
            'deliverable_slugs' => ['api', 'documentation', 'web-app'],
            'design_style_slugs' => ['developer-focused'],
            'project_type' => 'demo',
            'status' => 'shipped',
            'visibility' => 'public',
        ];

        return [
            // ── Featured APIs ───────────────────────────────────────────
            array_merge($stack, [
                'slug' => 'webhook-relay',
                'name' => 'Webhook Relay',
                'short_description' => 'Production-shaped webhook delivery API with HMAC signing, exponential backoff retries, dead-letter queue, and 4 language SDKs.',
                'long_description' => "Buyer-facing question: can this same person ship the API, the SDKs, the docs, and the deploy story?\n\nWebhook Relay is the answer. Subscriptions, events, deliveries, retries, sandbox keys, and an SSE live-echo for debugging — all behind a Filament admin and a Scalar try-it documentation site.",
                'repo_url' => 'https://github.com/philiprehberger/webhook-relay',
                'live_url' => 'https://webhook-relay.dcsuniverse.com',
                'docs_url' => 'https://webhook-relay.dcsuniverse.com/docs',
                'shipped_date' => '2026-06-07',
                'hours_estimated' => 80, 'hours_actual' => 92,
                'capability_slugs' => ['webhook-delivery', 'webhook-receiving', 'rest-api', 'background-queue', 'authentication', 'rate-limiting', 'observability', 'real-time-sync'],
                'primary_capability' => 'webhook-delivery',
                'technology_slugs' => ['php', 'laravel', 'filament', 'nextjs', 'typescript', 'mysql', 'apache', 'pm2', 'scalar'],
                'primary_technology' => 'laravel',
                'metrics' => ['test_count' => 92, 'lighthouse_perf' => 100, 'lighthouse_a11y' => 95, 'lighthouse_best' => 100, 'lighthouse_seo' => 100, 'duration_days' => 14],
            ]),
            array_merge($stack, [
                'slug' => 'pennant',
                'name' => 'Pennant — Feature Flags',
                'short_description' => 'Feature-flag API with real-time SSE broadcasts, two SDKs, and a Filament admin where the buyer actually wants to live.',
                'long_description' => 'Pennant is the feature-flag piece of the portfolio — stateful SDKs in PHP + Python, real-time SSE updates so a flag change reaches running apps in < 1s, and a Filament admin with environment + user-targeting rules.',
                'repo_url' => 'https://github.com/philiprehberger/pennant',
                'live_url' => 'https://pennant.philiprehberger.com',
                'docs_url' => 'https://pennant.philiprehberger.com/docs',
                'shipped_date' => '2026-06-08',
                'hours_estimated' => 90, 'hours_actual' => 105,
                'capability_slugs' => ['rest-api', 'authentication', 'rate-limiting', 'observability', 'real-time-sync', 'background-queue', 'workflow-engine'],
                'primary_capability' => 'real-time-sync',
                'technology_slugs' => ['php', 'laravel', 'filament', 'nextjs', 'typescript', 'react', 'python', 'mysql', 'apache'],
                'primary_technology' => 'laravel',
                'metrics' => ['test_count' => 125, 'lighthouse_perf' => 100, 'lighthouse_a11y' => 95, 'lighthouse_best' => 100, 'lighthouse_seo' => 100, 'duration_days' => 16],
            ]),
            array_merge($stack, [
                'slug' => 'inkwell',
                'name' => 'Inkwell — Form Submission API',
                'short_description' => 'HTML form ingestion API with spam scoring, multi-destination fan-out (email/Slack/HubSpot/etc), and a 3 KB JS widget.',
                'long_description' => 'Inkwell is the form-submission product shape. Public POST endpoint, deterministic spam scoring, fan-out to email + webhook + Slack + Discord + Google Sheets + HubSpot + Mailchimp. Works with JS off, in HTML emails, for screen readers.',
                'repo_url' => 'https://github.com/philiprehberger/inkwell',
                'live_url' => 'https://inkwell.philiprehberger.com',
                'docs_url' => 'https://inkwell.philiprehberger.com/docs',
                'shipped_date' => '2026-06-09',
                'hours_estimated' => 75, 'hours_actual' => 88,
                'capability_slugs' => ['lead-capture', 'content-moderation', 'webhook-delivery', 'notifications', 'rest-api', 'oauth-connectors', 'file-upload-pipeline', 'virus-scanning', 'gdpr-compliance', 'rate-limiting'],
                'primary_capability' => 'lead-capture',
                'technology_slugs' => ['php', 'laravel', 'filament', 'nextjs', 'typescript', 'mysql', 'redis', 'apache'],
                'primary_technology' => 'laravel',
                'metrics' => ['test_count' => 68, 'lighthouse_perf' => 100, 'lighthouse_a11y' => 95, 'lighthouse_best' => 100, 'lighthouse_seo' => 100, 'duration_days' => 14],
            ]),
            array_merge($stack, [
                'slug' => 'switchyard',
                'name' => 'Switchyard — ClickUp Lead Intake',
                'short_description' => 'Multi-tenant ClickUp lead-intake integration: normalizes inquiries from contact forms + Upwork + Fiverr + email into enriched ClickUp tasks with bidirectional status mirroring.',
                'long_description' => "Sales-ops teams at dev shops actually live in a PM tool (ClickUp, Asana, Linear), not a CRM — that's where the work is. Off-the-shelf form-to-PM glue is brittle: Zapier per-step billing, no enrichment, no idempotency, no audit trail, no way to mirror PM-side status back without a second Zap.\n\nSwitchyard answers the gap. Per-source parsers (ScopeForged contact form, Upwork notification email, Fiverr DMs, generic webhook via field_mapping, manual paste-in) hand off to a queue pipeline: parse → keyword-tag against the workspace taxonomy → suggest matching portfolio examples → score 0-100 over budget + timeline + contact completeness → write a ClickUp task with custom-field-populated payload. ClickUp webhook callbacks mirror status changes back into the local lead row, so the inbox + audit log stay in lockstep.\n\nProduction-shape primitives: ClickUp v2 OAuth (no PKCE, raw Authorization header), Stripe/Inkwell-style HMAC for inbound webhooks (t=<unix>,v1=<hex> with 5-min replay tolerance), per-request idempotency keys with 24h dedup window, Filament v5 admin with reveal-once secrets, OpenAPI 3.1 spec rendered via Scalar.\n\nFirst real-use tenant: ScopeForged. Built end-to-end in a single session with the schema seams for Phase 2 (client status board), Phase 3 (proposal → project scaffolder), and Phase 4 (time → invoice) deliberately left intact.",
                'repo_url' => 'https://github.com/philiprehberger/switchyard',
                'live_url' => 'https://switchyard.philiprehberger.com',
                'docs_url' => 'https://switchyard.philiprehberger.com/docs',
                'shipped_date' => '2026-06-16',
                'hours_estimated' => 60, 'hours_actual' => 65,
                'capability_slugs' => ['lead-capture', 'third-party-sync', 'workflow-engine', 'oauth-connectors', 'webhook-receiving', 'webhook-delivery', 'rest-api', 'background-queue', 'rate-limiting', 'authentication'],
                'primary_capability' => 'workflow-engine',
                'technology_slugs' => ['php', 'laravel', 'filament', 'nextjs', 'typescript', 'mysql', 'redis', 'apache'],
                'primary_technology' => 'laravel',
                'metrics' => ['test_count' => 90, 'lighthouse_perf' => 100, 'lighthouse_a11y' => 95, 'lighthouse_best' => 100, 'lighthouse_seo' => 100, 'duration_days' => 1],
            ]),
            array_merge($stack, [
                'slug' => 'docgen',
                'name' => 'Docgen — Document Generation API',
                'short_description' => 'PDF/DOCX/HTML generation from versioned templates with format conversion and a try-it docs site.',
                'long_description' => 'Docgen turns templated content into PDF, DOCX, or HTML. Versioned templates with rollback, format conversion via headless Chromium + Pandoc, signed-URL retrieval. The B2B SaaS shape: ingest data, render document, ship to S3.',
                'repo_url' => 'https://github.com/philiprehberger/docgen',
                'live_url' => 'https://docgen.philiprehberger.com',
                'docs_url' => 'https://docgen.philiprehberger.com/docs',
                'shipped_date' => '2026-06-07',
                'hours_estimated' => 70, 'hours_actual' => 80,
                'capability_slugs' => ['document-generation', 'rest-api', 'background-queue', 'authentication', 'file-upload-pipeline', 'caching'],
                'primary_capability' => 'document-generation',
                'technology_slugs' => ['php', 'laravel', 'filament', 'nextjs', 'mysql', 'apache'],
                'primary_technology' => 'laravel',
                'metrics' => ['test_count' => 71, 'lighthouse_perf' => 100, 'lighthouse_a11y' => 95, 'lighthouse_best' => 100, 'lighthouse_seo' => 100, 'duration_days' => 12],
            ]),

            // ── DevOps tooling ──────────────────────────────────────────
            [
                'slug' => 'shipyard',
                'name' => 'Shipyard — Atomic-Release Deploy CLI',
                'short_description' => 'Atomic-release deploy CLI: zero-downtime SSH/rsync deploys with health-gated promotion and automatic rollback. One static Go binary, one YAML config, no agent on the server.',
                'long_description' => "First DevOps tool in the portfolio. Every prior piece (webhook-relay, docgen, pennant, inkwell) sells to product or API teams. Shipyard sells to the person who got woken up at 3am because 'cd app && git pull && pm2 restart' served half-deployed code for 90 seconds and had no rollback.\n\n13-step lifecycle: parse config, run pre_upload hooks locally, SSH connect with known_hosts verification, acquire remote SFTP lockfile with TTL stale-steal, upload artifact, extract into releases/<timestamp>/, symlink shared files into the release dir, run post_extract hooks remotely, atomic symlink flip via `ln -s` + `mv -Tf` (a bare `ln -sfn` is not atomic — the mv is the trick), run post_flip hooks, HTTP health probe with retries, auto-rollback on failure, auto-prune respecting `releases.keep`, lock release.\n\nEats own cooking: the docs site at shipyard.philiprehberger.com is itself deployed by Shipyard. The shipyard.yaml that does it is in the repo root. Exit codes 0..5 are stable + documented so CI scripts can branch deterministically.\n\nDistributed as a single static Go binary via GoReleaser → GitHub Releases on every `v*` tag push: linux/darwin amd64+arm64, windows amd64. Native Go SSH + SFTP (no shell-out to ssh/scp; matters for Windows builds).",
                'repo_url' => 'https://github.com/philiprehberger/shipyard',
                'live_url' => 'https://shipyard.philiprehberger.com',
                'docs_url' => 'https://shipyard.philiprehberger.com/docs/quickstart',
                'project_type' => 'open_source',
                'status' => 'shipped',
                'visibility' => 'public',
                'shipped_date' => '2026-06-10',
                'hours_estimated' => 60,
                'hours_actual' => 48,
                'capability_slugs' => ['atomic-deploys', 'cli-tools', 'configuration-loading', 'observability', 'version-management', 'error-handling'],
                'primary_capability' => 'atomic-deploys',
                'technology_slugs' => ['go', 'bash', 'nextjs', 'react', 'typescript', 'tailwind', 'apache', 'pm2', 'systemd', 'letsencrypt', 'github-actions', 'aws-ec2', 'npm'],
                'primary_technology' => 'go',
                'industry_slugs' => ['developer-tools'],
                'architecture_slugs' => ['static'],
                'deliverable_slugs' => ['cli-tool', 'documentation'],
                'design_style_slugs' => ['developer-focused'],
                'metrics' => ['test_count' => 26, 'duration_days' => 1],
            ],

            // ── Headless WP + Marketing sites ───────────────────────────
            [
                'slug' => 'throughline-headless-wp',
                'name' => 'Throughline — Headless WordPress',
                'short_description' => 'Next.js dashboard reading from a WordPress backend via REST + GraphQL — the headless CMS shape.',
                'long_description' => "Throughline is the headless WordPress pattern: WP as the editorial backbone, Next.js as the public face. ISR for instant publishing, custom REST routes for the bits WP\\'s default API misses.",
                'repo_url' => 'https://github.com/philiprehberger/throughline-headless-wp',
                'live_url' => 'https://throughline.philiprehberger.com',
                'project_type' => 'demo', 'status' => 'active', 'visibility' => 'public',
                'hours_estimated' => 60,
                'capability_slugs' => ['cms', 'rest-api', 'search', 'seo-optimisation', 'landing-pages'],
                'primary_capability' => 'cms',
                'technology_slugs' => ['php', 'wordpress', 'acf', 'nextjs', 'typescript', 'mysql', 'apache'],
                'primary_technology' => 'wordpress',
                'industry_slugs' => ['media'],
                'architecture_slugs' => ['headless', 'isr'],
                'deliverable_slugs' => ['web-app', 'wordpress-plugin'],
                'design_style_slugs' => ['editorial', 'minimalist'],
            ],
            [
                'slug' => 'elite-events',
                'name' => 'Elite Events',
                'short_description' => 'Events-services marketing site — venue tour, booking inquiry, multi-page service catalogue.',
                'repo_url' => 'https://github.com/philiprehberger/elite-events',
                'live_url' => 'https://elite-events.dcsuniverse.com',
                'project_type' => 'demo', 'status' => 'shipped', 'visibility' => 'public',
                'shipped_date' => '2026-04-15', 'hours_actual' => 40,
                'capability_slugs' => ['landing-pages', 'lead-capture', 'cms', 'seo-optimisation', 'email-campaigns'],
                'primary_capability' => 'landing-pages',
                'technology_slugs' => ['php', 'wordpress', 'tailwind', 'mysql', 'apache'],
                'primary_technology' => 'wordpress',
                'industry_slugs' => ['events', 'hospitality'],
                'architecture_slugs' => ['cms-driven', 'mpa'],
                'deliverable_slugs' => ['website'],
                'design_style_slugs' => ['marketing-funnel', 'editorial'],
            ],
            [
                'slug' => 'smart-home-services',
                'name' => 'Smart Home Services',
                'short_description' => 'Service-business marketing site with quote-request flow and structured service descriptions.',
                'live_url' => 'https://smart-home.dcsuniverse.com',
                'project_type' => 'demo', 'status' => 'shipped', 'visibility' => 'public',
                'shipped_date' => '2026-04-20', 'hours_actual' => 32,
                'capability_slugs' => ['landing-pages', 'lead-capture', 'seo-optimisation', 'cms'],
                'technology_slugs' => ['php', 'wordpress', 'tailwind', 'mysql', 'apache'],
                'primary_technology' => 'wordpress',
                'industry_slugs' => ['trades'],
                'architecture_slugs' => ['cms-driven'],
                'deliverable_slugs' => ['website'],
                'design_style_slugs' => ['service-business'],
            ],
            [
                'slug' => 'holiday-lights',
                'name' => 'Holiday Lights',
                'short_description' => 'Seasonal-services marketing site — booking calendar, service tiers, deposit flow.',
                'live_url' => 'https://holiday-lights.dcsuniverse.com',
                'project_type' => 'demo', 'status' => 'shipped', 'visibility' => 'public',
                'shipped_date' => '2026-03-01', 'hours_actual' => 28,
                'capability_slugs' => ['landing-pages', 'lead-capture', 'payments', 'scheduled-jobs'],
                'technology_slugs' => ['php', 'wordpress', 'tailwind', 'mysql', 'apache', 'stripe'],
                'primary_technology' => 'wordpress',
                'industry_slugs' => ['trades', 'hospitality'],
                'architecture_slugs' => ['cms-driven'],
                'deliverable_slugs' => ['website'],
                'design_style_slugs' => ['service-business'],
            ],
            [
                'slug' => 'integridev',
                'name' => 'Integridev',
                'short_description' => 'Dev-shop marketing site — service tiers, case studies, contact flow.',
                'live_url' => 'https://integridev.com',
                'project_type' => 'demo', 'status' => 'shipped', 'visibility' => 'public',
                'shipped_date' => '2026-02-10', 'hours_actual' => 36,
                'capability_slugs' => ['landing-pages', 'lead-capture', 'seo-optimisation', 'cms'],
                'technology_slugs' => ['typescript', 'nextjs', 'react', 'tailwind'],
                'primary_technology' => 'nextjs',
                'industry_slugs' => ['developer-tools', 'professional-services'],
                'architecture_slugs' => ['static', 'mpa'],
                'deliverable_slugs' => ['website'],
                'design_style_slugs' => ['developer-focused', 'minimalist'],
            ],
            [
                'slug' => 'scopeforged',
                'name' => 'ScopeForged',
                'short_description' => 'Philip\'s dev-services marketing site — proposal-shaped service pages with concrete numbers.',
                'live_url' => 'https://scopeforged.com',
                'project_type' => 'personal', 'status' => 'shipped', 'visibility' => 'public',
                'shipped_date' => '2026-01-15', 'hours_actual' => 50,
                'capability_slugs' => ['landing-pages', 'lead-capture', 'seo-optimisation', 'cms'],
                'technology_slugs' => ['php', 'wordpress', 'tailwind', 'mysql', 'apache'],
                'primary_technology' => 'wordpress',
                'industry_slugs' => ['developer-tools', 'agency-marketing'],
                'architecture_slugs' => ['cms-driven'],
                'deliverable_slugs' => ['website'],
                'design_style_slugs' => ['minimalist', 'developer-focused'],
            ],
            [
                'slug' => 'highlands-ranch-champ-nextjs',
                'name' => 'Highlands Ranch Champ',
                'short_description' => 'Service-business Next.js site for a Highlands Ranch local — booking + service tiers.',
                'project_type' => 'demo', 'status' => 'shipped', 'visibility' => 'public',
                'shipped_date' => '2026-01-20', 'hours_actual' => 30,
                'capability_slugs' => ['landing-pages', 'lead-capture', 'seo-optimisation'],
                'technology_slugs' => ['typescript', 'nextjs', 'react', 'tailwind'],
                'primary_technology' => 'nextjs',
                'industry_slugs' => ['trades'],
                'architecture_slugs' => ['static'],
                'deliverable_slugs' => ['website'],
                'design_style_slugs' => ['service-business'],
            ],

            // ── Personal portfolio + ScopeForged ─────────────────────────
            [
                'slug' => 'philiprehberger-nextjs',
                'name' => 'philiprehberger.com',
                'short_description' => 'Philip\'s personal portfolio site — Next.js, the cross-channel landing surface for every demo.',
                'live_url' => 'https://philiprehberger.com',
                'repo_url' => 'https://github.com/philiprehberger/philiprehberger-nextjs',
                'project_type' => 'personal', 'status' => 'shipped', 'visibility' => 'public',
                'shipped_date' => '2026-02-01', 'hours_actual' => 60,
                'capability_slugs' => ['landing-pages', 'seo-optimisation', 'cms', 'event-tracking'],
                'primary_capability' => 'landing-pages',
                'technology_slugs' => ['typescript', 'nextjs', 'react', 'tailwind', 'vercel'],
                'primary_technology' => 'nextjs',
                'industry_slugs' => ['personal-brand', 'developer-tools'],
                'architecture_slugs' => ['static'],
                'deliverable_slugs' => ['website'],
                'design_style_slugs' => ['minimalist', 'developer-focused'],
            ],
            [
                'slug' => 'converter',
                'name' => 'File Converter',
                'short_description' => 'Web-based file converter — DOCX, PDF, HTML, Markdown round-trip via headless browser + Pandoc.',
                'live_url' => 'https://converter.dcsuniverse.com',
                'repo_url' => 'https://github.com/philiprehberger/file-converter-app-nextjs',
                'project_type' => 'personal', 'status' => 'shipped', 'visibility' => 'public',
                'shipped_date' => '2026-03-15', 'hours_actual' => 45,
                'capability_slugs' => ['document-generation', 'file-upload-pipeline'],
                'primary_capability' => 'document-generation',
                'technology_slugs' => ['typescript', 'nextjs', 'react', 'tailwind'],
                'primary_technology' => 'nextjs',
                'industry_slugs' => ['developer-tools'],
                'architecture_slugs' => ['monolith', 'serverless'],
                'deliverable_slugs' => ['web-app'],
                'design_style_slugs' => ['minimalist'],
            ],
            [
                'slug' => 'api-store',
                'name' => 'API Store',
                'short_description' => 'A marketplace landing for Philip\'s API products — Webhook Relay, Pennant, Inkwell, Docgen — with unified pricing + try-it.',
                'project_type' => 'demo', 'status' => 'active', 'visibility' => 'public',
                'capability_slugs' => ['landing-pages', 'subscriptions', 'payments'],
                'technology_slugs' => ['typescript', 'nextjs', 'react', 'tailwind', 'stripe'],
                'primary_technology' => 'nextjs',
                'industry_slugs' => ['developer-tools', 'saas'],
                'architecture_slugs' => ['static', 'serverless'],
                'deliverable_slugs' => ['website'],
                'design_style_slugs' => ['saas', 'developer-focused'],
            ],
            [
                'slug' => 'philiprehberger-classic',
                'name' => 'philiprehberger.com (WP)',
                'short_description' => 'Earlier WordPress version of Philip\'s portfolio — predecessor to the current Next.js build.',
                'project_type' => 'personal', 'status' => 'archived', 'visibility' => 'public',
                'shipped_date' => '2024-06-01', 'hours_actual' => 40,
                'capability_slugs' => ['landing-pages', 'cms'],
                'technology_slugs' => ['php', 'wordpress', 'mysql', 'apache'],
                'primary_technology' => 'wordpress',
                'industry_slugs' => ['personal-brand'],
                'architecture_slugs' => ['cms-driven'],
                'deliverable_slugs' => ['website'],
                'design_style_slugs' => ['minimalist'],
            ],
            [
                'slug' => 'client-portal-saas',
                'name' => 'Client Portal SaaS',
                'short_description' => 'Multi-tenant client portal for service businesses — per-tenant data, invoices, file-sharing, role-based access.',
                'project_type' => 'demo', 'status' => 'shipped', 'visibility' => 'public',
                'shipped_date' => '2025-12-01', 'hours_actual' => 200,
                'capability_slugs' => ['multi-tenant', 'authentication', 'authorization', 'invoicing', 'subscriptions', 'payments', 'file-upload-pipeline', 'notifications', 'dashboards'],
                'primary_capability' => 'multi-tenant',
                'technology_slugs' => ['php', 'laravel', 'filament', 'mysql', 'redis', 'apache', 'stripe'],
                'primary_technology' => 'laravel',
                'industry_slugs' => ['professional-services', 'saas'],
                'architecture_slugs' => ['multi-tenant', 'monolith'],
                'deliverable_slugs' => ['web-app', 'admin-portal'],
                'design_style_slugs' => ['saas', 'data-dense'],
            ],
            [
                'slug' => 'scopeforged-library-wp',
                'name' => 'ScopeForged Library (WordPress)',
                'short_description' => 'WordPress plugins library — shared utilities across Philip\'s WP demos.',
                'repo_url' => 'https://github.com/philiprehberger/scopeforged-library-wp',
                'project_type' => 'open_source', 'status' => 'shipped', 'visibility' => 'public',
                'shipped_date' => '2026-01-10', 'hours_actual' => 25,
                'capability_slugs' => ['cms'],
                'technology_slugs' => ['php', 'wordpress'],
                'primary_technology' => 'wordpress',
                'industry_slugs' => ['developer-tools'],
                'architecture_slugs' => ['cms-driven'],
                'deliverable_slugs' => ['wordpress-plugin'],
                'design_style_slugs' => ['developer-focused'],
            ],

            // ── WordPress vertical demos (14) ────────────────────────────
            ...$this->wordpressDemos(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function wordpressDemos(): array
    {
        $base = [
            'project_type' => 'demo',
            'status' => 'shipped',
            'visibility' => 'public',
            'shipped_date' => '2026-05-01',
            'hours_actual' => 32,
            'capability_slugs' => ['landing-pages', 'lead-capture', 'seo-optimisation', 'cms'],
            'primary_capability' => 'landing-pages',
            'technology_slugs' => ['php', 'wordpress', 'tailwind', 'mysql', 'apache'],
            'primary_technology' => 'wordpress',
            'architecture_slugs' => ['cms-driven'],
            'deliverable_slugs' => ['website'],
            'design_style_slugs' => ['service-business'],
        ];

        $variants = [
            ['slug' => 'wp-atlas',      'name' => 'WP Atlas',      'short_description' => 'WordPress demo — agency-style multi-service site with case-study grid.', 'industry_slugs' => ['agency-marketing']],
            ['slug' => 'wp-bench',      'name' => 'WP Bench',      'short_description' => 'WordPress demo — co-working / shared-workspace marketing site.',           'industry_slugs' => ['hospitality']],
            ['slug' => 'wp-blockset',   'name' => 'WP Blockset',   'short_description' => 'WordPress demo — Gutenberg block library with custom patterns.',          'industry_slugs' => ['developer-tools']],
            ['slug' => 'wp-coach',      'name' => 'WP Coach',      'short_description' => 'WordPress demo — coaching practice site with booking + testimonial flow.', 'industry_slugs' => ['professional-services']],
            ['slug' => 'wp-dental',     'name' => 'WP Dental',     'short_description' => 'WordPress demo — dental practice site with appointment + insurance flow.', 'industry_slugs' => ['healthcare']],
            ['slug' => 'wp-give',       'name' => 'WP Give',       'short_description' => 'WordPress demo — nonprofit fundraising site with donation flow.',          'industry_slugs' => ['nonprofit'], 'capability_slugs' => ['landing-pages', 'lead-capture', 'payments', 'seo-optimisation', 'cms']],
            ['slug' => 'wp-hashi',      'name' => 'WP Hashi',      'short_description' => 'WordPress demo — restaurant marketing site with menu + reservations.',    'industry_slugs' => ['hospitality']],
            ['slug' => 'wp-hillcrest',  'name' => 'WP Hillcrest',  'short_description' => 'WordPress demo — real-estate listings site with property search.',         'industry_slugs' => ['real-estate']],
            ['slug' => 'wp-lawfirm',    'name' => 'WP Lawfirm',    'short_description' => 'WordPress demo — law firm site with practice-area pages + intake.',       'industry_slugs' => ['legal']],
            ['slug' => 'wp-northbound', 'name' => 'WP Northbound', 'short_description' => 'WordPress demo — outdoor-services marketing site.',                       'industry_slugs' => ['trades']],
            ['slug' => 'wp-pulsar',     'name' => 'WP Pulsar',     'short_description' => 'WordPress demo — SaaS-style product marketing site.',                     'industry_slugs' => ['saas']],
            ['slug' => 'wp-roofing',    'name' => 'WP Roofing',    'short_description' => 'WordPress demo — roofing contractor site with quote-request flow.',       'industry_slugs' => ['trades']],
            ['slug' => 'wp-saltcedar',  'name' => 'WP Saltcedar',  'short_description' => 'WordPress demo — wellness practice site with class schedule + booking.',  'industry_slugs' => ['healthcare']],
            ['slug' => 'wp-winebar',    'name' => 'WP Winebar',    'short_description' => 'WordPress demo — winebar site with events + tasting calendar.',           'industry_slugs' => ['hospitality']],
        ];

        return array_map(fn (array $v) => array_merge($base, $v), $variants);
    }
}
