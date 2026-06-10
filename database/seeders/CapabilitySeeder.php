<?php

namespace Database\Seeders;

use App\Models\Capability;
use Illuminate\Support\Facades\Config;

/**
 * The load-bearing seeder. Capability vocabulary is the heatmap row
 * count — every row is a "I need X" buyer search. Discipline:
 *   - Buyer-shaped (what prospects say, not how we built it)
 *   - Distinct enough that no two collapse on "should we merge these?"
 *   - Honest — every capability has a project that actually demonstrates it
 *
 * Descriptions are proposal-ready: 50-100 words, no marketing puffery,
 * concrete-numbers shape matching Philip's voice ([[user-skills-*]]).
 * They land as description_reviewed=true because they're hand-written.
 *
 * Vocabulary cap enforced before insert: warn at 60 (config), hard cap
 * at 80 (config). Drop below the cap by merging via the Filament
 * action — never by deleting.
 */
class CapabilitySeeder extends BaseSeeder
{
    public function run(): void
    {
        $this->assertWithinCap(count($this->capabilities()));

        foreach ($this->capabilities() as $cap) {
            Capability::updateOrCreate(
                ['slug' => $cap['slug']],
                [
                    'name' => $cap['name'],
                    'category' => $cap['category'],
                    'description' => $cap['description'],
                    'description_reviewed' => true,
                    'icon' => $cap['icon'] ?? null,
                ],
            );
        }
    }

    private function assertWithinCap(int $count): void
    {
        $cap = (int) Config::get('codex.vocabulary.capabilities.cap', 80);
        if ($count > $cap) {
            throw new \RuntimeException(sprintf(
                'CapabilitySeeder exceeds hard cap: %d > %d. Merge before adding.',
                $count,
                $cap,
            ));
        }
    }

    /**
     * @return array<int, array{slug: string, name: string, category: string, description: string, icon?: string}>
     */
    private function capabilities(): array
    {
        return [
            // ──────────────────────────────────────────────────────── UserMgmt
            [
                'slug' => 'authentication',
                'name' => 'Authentication',
                'category' => 'UserMgmt',
                'icon' => 'shield',
                'description' => 'Email/password sign-in with hashed passwords (bcrypt/argon2), session management, password-reset flows, and remember-me tokens. Optional TOTP 2FA via authenticator apps or SMS. Built on Laravel\'s auth contracts or NextAuth; OAuth providers (Google, GitHub) drop in via the same pattern. Includes throttling, rate-limited login, and forensic audit-log capture (IP, user-agent) for compromise response.',
            ],
            [
                'slug' => 'authorization',
                'name' => 'Authorization / RBAC',
                'category' => 'UserMgmt',
                'icon' => 'lock',
                'description' => 'Role-based access control via gates, policies, and middleware. Roles + permissions stored in DB with caching; per-resource policies (Project::view, Order::edit) called from controllers and Blade/JSX views. Scales from a single "admin vs user" toggle up to multi-tenant per-workspace roles with custom permission grants. Audit-logged at the action layer so a compromised account leaves a forensic trail.',
            ],
            [
                'slug' => 'multi-tenant',
                'name' => 'Multi-tenant Isolation',
                'category' => 'UserMgmt',
                'icon' => 'building-office',
                'description' => 'Per-tenant data isolation via a workspace_id foreign key + a global scope on every model, plus middleware that resolves the current workspace from subdomain, header, or session. Background jobs serialize the tenant so a queue worker can\'t leak across tenants. Tested against the "wrong tenant" assertion in feature tests — defence-in-depth against scope drift.',
            ],
            [
                'slug' => 'user-onboarding',
                'name' => 'User Onboarding',
                'category' => 'UserMgmt',
                'icon' => 'user-plus',
                'description' => 'Sign-up flow with email verification, optional invite-only codes, and a guided first-run checklist (set workspace name, invite teammates, connect first integration). Templated welcome emails via Mailgun/Postmark/SES. Progress tracked in onboarding_steps so the dashboard can prompt incomplete users without re-running the full flow.',
            ],
            [
                'slug' => 'profile-management',
                'name' => 'Profile Management',
                'category' => 'UserMgmt',
                'icon' => 'user',
                'description' => 'User-facing settings page: name, email, avatar, password rotation, 2FA enrolment/disable, notification preferences, session list with revoke. Built with the same form components as the rest of the admin so the UX stays consistent. Profile updates emit audit-log events for compromise forensics.',
            ],

            // ──────────────────────────────────────────────────────── Commerce
            [
                'slug' => 'payments',
                'name' => 'Payments',
                'category' => 'Commerce',
                'icon' => 'credit-card',
                'description' => 'Stripe-backed payment flows: Payment Intents for one-off charges, Setup Intents for saving cards, Strong Customer Authentication (3DS) for EU regulation. Webhook handlers idempotent against retried events. Refund + partial-refund UI on the admin side. Built for SaaS metered billing, e-commerce checkout, and donation flows.',
            ],
            [
                'slug' => 'subscriptions',
                'name' => 'Subscriptions & Recurring Billing',
                'category' => 'Commerce',
                'icon' => 'arrow-path',
                'description' => 'Subscription management on Stripe Billing or Paddle: plan upgrades/downgrades with proration, grace periods on failed payments, dunning emails, customer portal for self-serve cancellation. Webhook-driven state machine in the DB so the UI never queries Stripe inline. Includes per-plan feature gates wired into the authorization layer.',
            ],
            [
                'slug' => 'cart-checkout',
                'name' => 'Cart & Checkout',
                'category' => 'Commerce',
                'icon' => 'shopping-cart',
                'description' => 'Session-backed cart for guest + authenticated users, item-level discounts, coupon codes, tax + shipping calculation. Multi-step checkout with address validation. Inventory holds during the payment flow so two visitors can\'t buy the last item simultaneously. Order audit log for support team lookups.',
            ],
            [
                'slug' => 'invoicing',
                'name' => 'Invoice Generation',
                'category' => 'Commerce',
                'icon' => 'document-text',
                'description' => 'PDF invoice generation from a templated HTML source. Line items, tax rules, currency formatting, recipient + supplier addresses pulled from the workspace. Emailable via the same template engine that powers transactional emails. Storage to S3 or local disk with signed-URL retrieval. Used downstream of Subscriptions for monthly billing cycles.',
            ],

            // ──────────────────────────────────────────────────────── Marketing
            [
                'slug' => 'lead-capture',
                'name' => 'Lead Capture',
                'category' => 'Marketing',
                'icon' => 'envelope-open',
                'description' => 'HTML form submissions ingested via a public endpoint with spam scoring (honeypot, timing, content, IP reputation), CAPTCHA fallback, and per-form rate limits. Submissions fan out to email, webhook, Slack, HubSpot, Mailchimp via a registered destinations registry. No-JavaScript fallback works in HTML emails and screen readers.',
            ],
            [
                'slug' => 'email-campaigns',
                'name' => 'Email Campaigns',
                'category' => 'Marketing',
                'icon' => 'envelope',
                'description' => 'Transactional + bulk email via Mailgun, Postmark, SES, or Resend. Templated HTML + plain-text variants, dynamic merge fields, list segmentation, unsubscribe management with one-click links. Bounce + complaint webhook handlers update suppression lists automatically. Open + click tracking optional, off by default for GDPR-conscious clients.',
            ],
            [
                'slug' => 'crm-sync',
                'name' => 'CRM Sync',
                'category' => 'Marketing',
                'icon' => 'users',
                'description' => 'Two-way sync with HubSpot, Salesforce, Pipedrive, or Close. Webhook receivers for upstream changes, scheduled poll-based reconciliation as a fallback, conflict resolution favouring the most-recently-updated record. OAuth-based per-tenant connections so a SaaS app can ingest from each customer\'s CRM independently. Field-mapping configurable per workspace.',
            ],
            [
                'slug' => 'landing-pages',
                'name' => 'Landing Pages',
                'category' => 'Marketing',
                'icon' => 'document',
                'description' => 'Conversion-focused single-page sites with the hero / value-prop / proof / CTA structure. Built in Next.js with statically-generated pages for sub-second TTFB, or in a CMS-backed Laravel app for editor self-service. Lighthouse 95+ across performance / accessibility / SEO out of the gate. Custom analytics events on CTA clicks.',
            ],
            [
                'slug' => 'seo-optimisation',
                'name' => 'SEO Optimisation',
                'category' => 'Marketing',
                'icon' => 'magnifying-glass',
                'description' => 'On-page SEO: semantic HTML, structured data (JSON-LD), OpenGraph + Twitter card metadata, dynamic XML sitemaps, canonical URLs, hreflang for multi-locale sites, robots.txt with explicit disallows for admin paths and signed-URL surfaces. Lighthouse SEO 100 the default target.',
            ],

            // ──────────────────────────────────────────────────────── Content
            [
                'slug' => 'cms',
                'name' => 'Content Management',
                'category' => 'Content',
                'icon' => 'pencil',
                'description' => 'Filament admin or WordPress backend depending on buyer needs. Page/post CRUD, role-based publishing, scheduled posts, draft preview, revision history. Hand-curated taxonomies (categories + tags + custom fields) instead of free-form when buyer wants editorial control.',
            ],
            [
                'slug' => 'markdown-editor',
                'name' => 'Markdown / Rich Text Editor',
                'category' => 'Content',
                'icon' => 'document-text',
                'description' => 'TipTap, ProseMirror, or Trix-backed rich-text editor with markdown round-trip. Image uploads via signed URLs, link auto-completion against the workspace\'s page list, slash-commands for headings/callouts/code blocks. Output stored as markdown or sanitised HTML depending on render target.',
            ],
            [
                'slug' => 'media-library',
                'name' => 'Media Library',
                'category' => 'Content',
                'icon' => 'photo',
                'description' => 'Asset upload + organisation: drag-and-drop, multi-file, automatic resizing/cropping for responsive variants, EXIF stripping, alt-text capture. Storage to local disk, S3, or DigitalOcean Spaces with signed-URL access for private assets. Folder/tag organisation, search by filename + alt text.',
            ],
            [
                'slug' => 'document-generation',
                'name' => 'Document Generation',
                'category' => 'Content',
                'icon' => 'document-duplicate',
                'description' => 'PDF/DOCX/HTML output from versioned templates. Merge data via a typed binding so a template never references a missing field at render time. Format conversion via headless Chromium, Pandoc, or LibreOffice depending on fidelity needs. Versioned templates with rollback so a bad edit can\'t break in-flight document generation.',
            ],
            [
                'slug' => 'search',
                'name' => 'Search',
                'category' => 'Content',
                'icon' => 'magnifying-glass',
                'description' => 'Full-text search via MySQL FULLTEXT, Meilisearch, Typesense, or Algolia depending on scale. Client-side fuzzy index (flexsearch / fuse.js) for documentation sites + product catalogues under ~10k records. Server-side faceted search with filters, typo-tolerance, and synonym dictionaries for larger surfaces.',
            ],

            // ──────────────────────────────────────────────────────── Analytics
            [
                'slug' => 'event-tracking',
                'name' => 'Event Tracking',
                'category' => 'Analytics',
                'icon' => 'chart-bar',
                'description' => 'Custom event capture via self-hosted Plausible, Fathom, or PostHog (no GDPR banner needed when no cookies are set). Events fire on heatmap-cell clicks, capability drilldowns, CTA presses — the actions buyers actually pay to learn about. Server-side fallback for events the client can\'t see (queue completions, scheduled-job runs).',
            ],
            [
                'slug' => 'dashboards',
                'name' => 'Dashboards',
                'category' => 'Analytics',
                'icon' => 'chart-pie',
                'description' => 'Filament dashboards with stat widgets, time-series charts (chart.js / recharts), and tabular drill-downs. Capability heatmaps, gap reports, operational KPIs. Server-rendered with caching layers so dashboards survive 100 concurrent viewers without re-querying. Per-row drill-down to underlying records.',
            ],
            [
                'slug' => 'ab-testing',
                'name' => 'A/B Testing',
                'category' => 'Analytics',
                'icon' => 'beaker',
                'description' => 'Experiment framework with variant assignment via hashed user ID (stable across sessions), exposure events, and statistical significance reporting. Feature-flag-backed so a test can be killed instantly without a deploy. Integrates with the event-tracking layer so conversion metrics flow into the analysis automatically.',
            ],
            [
                'slug' => 'reporting',
                'name' => 'Reporting & Export',
                'category' => 'Analytics',
                'icon' => 'arrow-down-tray',
                'description' => 'CSV/XLSX/PDF report generation, optionally scheduled and emailed. Background-job-driven so a 10MB CSV export doesn\'t block the request. Streaming downloads for large exports. Versioned report definitions so old reports stay reproducible after the underlying data shape changes.',
            ],

            // ──────────────────────────────────────────────────────── Integrations
            [
                'slug' => 'webhook-delivery',
                'name' => 'Webhook Delivery',
                'category' => 'Integrations',
                'icon' => 'arrow-up-tray',
                'description' => 'Outbound webhook fan-out with HMAC signing, exponential-backoff retries, dead-letter queue, and per-endpoint health tracking. Customer-facing deliveries log including the response status + body for debugging. Sandbox keys for trial users, rate limits per workspace, no-PII logging by default.',
            ],
            [
                'slug' => 'webhook-receiving',
                'name' => 'Webhook Receiving',
                'category' => 'Integrations',
                'icon' => 'arrow-down-tray',
                'description' => 'Inbound webhook handlers with HMAC signature verification (Stripe, Mailgun, GitHub patterns), idempotency keys to dedupe retries, and a per-source body cap. Failed verifications return 401 with no body; processed events fan out to a queue so the verifier can return 200 within Stripe\'s 5-second window.',
            ],
            [
                'slug' => 'oauth-connectors',
                'name' => 'OAuth Connectors',
                'category' => 'Integrations',
                'icon' => 'key',
                'description' => 'OAuth 2.0 + 2.1 PKCE flows for Google, Microsoft, HubSpot, Slack, Discord, Stripe, GitHub. Token refresh handled in the background before expiry so end-user requests never wait. Per-tenant token storage encrypted at rest. Disconnect flow revokes upstream tokens, not just local state.',
            ],
            [
                'slug' => 'rest-api',
                'name' => 'REST API Design',
                'category' => 'Integrations',
                'icon' => 'code-bracket',
                'description' => 'OpenAPI 3.1 spec as the source of truth, with RFC 7807 problem-detail error responses, cursor-based pagination, idempotency-key support, per-route rate limiting, and CORS allow-listing. Scalar try-it docs + Postman/Insomnia collection generation. Versioned with /v1/ from day one.',
            ],
            [
                'slug' => 'third-party-sync',
                'name' => 'Third-party Sync',
                'category' => 'Integrations',
                'icon' => 'arrow-path',
                'description' => 'Bidirectional sync with HubSpot, Salesforce, Pipedrive, Mailchimp, Intercom, and similar SaaS systems. Webhook-driven for real-time updates plus a scheduled fallback poll. Conflict resolution: last-writer-wins with an audit-log entry on every divergence so a customer can roll back a bad sync.',
            ],
            [
                'slug' => 'notifications',
                'name' => 'Slack / Discord / Email Notifications',
                'category' => 'Integrations',
                'icon' => 'bell',
                'description' => 'Templated notifications to Slack channels, Discord webhooks, transactional email, or in-app inbox. Per-user notification preferences with quiet hours. Rate-limited so a runaway alert can\'t spam a channel. Threading + attachment support on Slack so an alert can include the offending record screenshot.',
            ],

            // ──────────────────────────────────────────────────────── Automation
            [
                'slug' => 'scheduled-jobs',
                'name' => 'Scheduled Jobs',
                'category' => 'Automation',
                'icon' => 'clock',
                'description' => 'Cron-based scheduled tasks via Laravel\'s schedule or system cron. Heartbeat pings to BetterStack so silent failures alert within the expected interval. Jobs that fan out work onto the queue rather than running inline so a slow task doesn\'t block the cron worker.',
            ],
            [
                'slug' => 'workflow-engine',
                'name' => 'Workflow Engine',
                'category' => 'Automation',
                'icon' => 'arrows-right-left',
                'description' => 'Multi-step automated workflows defined as YAML or in a Filament builder. Triggers (webhook, schedule, form submission), conditions (rule-engine evaluation), actions (send email, call API, update record). Suspendable + resumable, with state stored so an outage doesn\'t lose mid-flight workflows.',
            ],
            [
                'slug' => 'background-queue',
                'name' => 'Background Queue',
                'category' => 'Automation',
                'icon' => 'queue-list',
                'description' => 'Horizon / Laravel queue workers on the database, Redis, or SQS driver. Per-queue throughput tuning, retries with backoff, failed-job table monitoring with alerts. Worker recycling (--max-jobs + --max-time) so memory leaks in any single job don\'t compound.',
            ],
            [
                'slug' => 'rule-engine',
                'name' => 'Rule Engine',
                'category' => 'Automation',
                'icon' => 'adjustments-horizontal',
                'description' => 'Customer-configurable IF-THEN rules expressed as JSON or YAML, evaluated against incoming records. Used in spam scoring, lead routing, alert escalation. Versioned rule sets with rollback so a bad rule can be reverted without code change.',
            ],

            // ──────────────────────────────────────────────────────── AI
            [
                'slug' => 'llm-integration',
                'name' => 'LLM Integration',
                'category' => 'AI',
                'icon' => 'sparkles',
                'description' => 'Claude / OpenAI / Gemini API integration for content generation, classification, summarisation. Prompt caching to control cost on repeated context. Streaming responses to the UI for first-token latency under 500ms. Retry + fallback model patterns for production resilience.',
            ],
            [
                'slug' => 'content-moderation',
                'name' => 'Content Moderation',
                'category' => 'AI',
                'icon' => 'shield-check',
                'description' => 'Spam + toxic-content scoring using a hybrid of static rules (honeypot, timing, IP reputation), regex/keyword filters, and optional LLM-backed classification for the final 5%. Per-tenant threshold tuning. Audit trail for every moderation decision so customer-support can review borderline cases.',
            ],
            [
                'slug' => 'semantic-search',
                'name' => 'Semantic Search',
                'category' => 'AI',
                'icon' => 'magnifying-glass-circle',
                'description' => 'Embedding-based search via OpenAI text-embedding-3-small + pgvector or Pinecone. Hybrid retrieval combining BM25 lexical match with semantic similarity for the right balance of recall + precision. Re-ranking via a cross-encoder for the top-K candidates.',
            ],

            // ──────────────────────────────────────────────────────── Infrastructure
            [
                'slug' => 'atomic-deploys',
                'name' => 'Atomic Deploys',
                'category' => 'Infrastructure',
                'icon' => 'rocket-launch',
                'description' => 'Capistrano-style atomic releases: build the new release in a sibling directory, swap a symlink, restart the worker pool. Failed migrations preserve the previous release so a rollback is one symlink swap. SSH-based deploy from a local build — no in-cluster CI infrastructure required.',
            ],
            [
                'slug' => 'real-time-sync',
                'name' => 'Real-time Sync (SSE / WebSocket)',
                'category' => 'Infrastructure',
                'icon' => 'bolt',
                'description' => 'Server-Sent Events broadcasts for one-way updates (dashboard live numbers, feature-flag changes), with WebSocket fallback for bidirectional needs (collaborative editing, presence). Backed by Laravel Reverb, Soketi, or a stateless Redis pub/sub depending on connection volume.',
            ],
            [
                'slug' => 'observability',
                'name' => 'Observability',
                'category' => 'Infrastructure',
                'icon' => 'eye',
                'description' => 'Structured logs to stdout in JSON, Sentry for exception tracking with release tagging + per-fingerprint rate limits, BetterStack for uptime monitoring of every public route + cron heartbeat. Health endpoints (/up, /up/diagnostics, /up/queue) feed BetterStack and Kubernetes liveness probes alike.',
            ],
            [
                'slug' => 'rate-limiting',
                'name' => 'Rate Limiting',
                'category' => 'Infrastructure',
                'icon' => 'hand-raised',
                'description' => 'Per-IP, per-user, per-API-key rate limits using Laravel\'s RateLimiter or a custom Redis-backed sliding window. Different limits for read vs write endpoints, with 429 + RFC 7807 problem responses including Retry-After headers. Throttled login routes block credential stuffing at the application layer.',
            ],
            [
                'slug' => 'caching',
                'name' => 'Caching',
                'category' => 'Infrastructure',
                'icon' => 'server-stack',
                'description' => 'Database, file, or Redis-backed cache with explicit named keys (no tags fan-out where the driver doesn\'t support it). On-demand revalidation from admin writes via HMAC-signed POSTs to the Next.js dashboard\'s revalidateTag endpoint. Falls back to a TTL so a downed invalidator never pins stale data forever.',
            ],
            [
                'slug' => 'file-upload-pipeline',
                'name' => 'File Upload Pipeline',
                'category' => 'Infrastructure',
                'icon' => 'cloud-arrow-up',
                'description' => 'Direct-to-S3 uploads via signed URLs (no server-side proxying for multi-MB files), client-side type + size validation, server-side EXIF stripping for images, mime-type sniffing, optional ClamAV virus scanning before the file is marked usable. Progress reporting + resumable uploads via tus.io for larger sources.',
            ],
            [
                'slug' => 'virus-scanning',
                'name' => 'Virus / Malware Scanning',
                'category' => 'Infrastructure',
                'icon' => 'bug-ant',
                'description' => 'ClamAV daemon scanning of incoming file uploads before they\'re marked downloadable. Asynchronous via the queue so the user-facing upload returns immediately; the file is quarantined until the scan completes. Per-tenant policy to skip scanning for trusted upload sources to keep latency low.',
            ],
            [
                'slug' => 'data-export',
                'name' => 'Data Export & Portability',
                'category' => 'Infrastructure',
                'icon' => 'archive-box-arrow-down',
                'description' => 'JSON / CSV / SQL exports of customer data for GDPR / CCPA portability, generated via background jobs and delivered via signed S3 URLs with TTL. Per-workspace export windows + audit log so a malicious admin can\'t silently exfiltrate. Re-importable into a fresh workspace for migration scenarios.',
            ],
            [
                'slug' => 'gdpr-compliance',
                'name' => 'GDPR / Data Subject Compliance',
                'category' => 'Infrastructure',
                'icon' => 'clipboard-document-check',
                'description' => 'Data subject access + erasure flows: API + admin endpoints to look up everything Codex/Inkwell/etc. holds about an email, plus a verified erasure path that scrubs PII while retaining the audit log. Throttled to prevent enumeration. Documented retention windows per data type. PII masking helpers for log + API redaction.',
            ],

            // ──────────────────────────────────────────────── Primitives (Phase 8.4)
            [
                'slug' => 'error-handling',
                'name' => 'Error Handling',
                'category' => 'Infrastructure',
                'icon' => 'exclamation-triangle',
                'description' => 'Type-safe error handling via Result / Option / Either patterns instead of throwing exceptions across module boundaries. Guard-clause DSLs for method preconditions. Resilient JSON / config parsing with fallback defaults. Backoff + jitter helpers for retry primitives. The defensive-programming layer that keeps a single bad input from cascading into a 500.',
            ],
            [
                'slug' => 'id-generation',
                'name' => 'ID Generation',
                'category' => 'Infrastructure',
                'icon' => 'finger-print',
                'description' => 'Stable, URL-safe unique IDs at scale: ULID for time-ordered handles, NanoID for compact public IDs, Snowflake for cross-service correlation, slug generators for SEO-friendly URLs. Includes prefixed IDs (`usr_…`) for type discrimination, transliteration for non-ASCII slug input, and collision-handling helpers when slugs land on the same name.',
            ],
            [
                'slug' => 'money-currency',
                'name' => 'Money & Currency',
                'category' => 'Commerce',
                'icon' => 'banknotes',
                'description' => 'Type-safe monetary values with precise arithmetic (integer-cent storage, no float drift), ISO 4217 currency codes, conversion via configurable rate sources, allocation algorithms for splitting amounts across line items, and locale-aware formatting for display. The "I need to handle money correctly" layer underneath any invoicing or payments work.',
            ],
            [
                'slug' => 'date-time-utilities',
                'name' => 'Date & Time Utilities',
                'category' => 'Infrastructure',
                'icon' => 'clock',
                'description' => 'Date parsing + formatting, duration arithmetic, time-zone-safe comparisons, business-day calculations with configurable holidays, relative-time expressions ("next Monday at 9am"), and human-readable duration formatting. The layer that prevents off-by-one bugs across DST boundaries and locale differences.',
            ],
            [
                'slug' => 'cli-tools',
                'name' => 'CLI Tools',
                'category' => 'Infrastructure',
                'icon' => 'command-line',
                'description' => 'Command-line interface primitives: decorator-based argument parsing, terminal spinners and progress bars, formatted tables, interactive prompts, colored output with ANSI fallback, and clipboard helpers. The building blocks for shipping a usable CLI alongside any backend service.',
            ],
            [
                'slug' => 'configuration-loading',
                'name' => 'Configuration Loading',
                'category' => 'Infrastructure',
                'icon' => 'wrench-screwdriver',
                'description' => 'Layered config loading from .env files, JSON/YAML/TOML, environment variables, and remote sources with schema-validated type-safe access. Multi-environment support (dev/staging/production overrides), interpolation, and config-diff tools for catching drift between environments before they trip a deploy.',
            ],
            [
                'slug' => 'json-processing',
                'name' => 'JSON Processing',
                'category' => 'Infrastructure',
                'icon' => 'code-bracket-square',
                'description' => 'Beyond stdlib JSON: resilient parsing with fallback defaults, RFC 6902 JSON Patch operations, deep merge with configurable array strategies, flatten/unflatten between nested and dot-notation, path-based extraction without null-chain hell. The toolkit for working with semi-structured payloads from APIs, configs, and webhook bodies.',
            ],
            [
                'slug' => 'string-manipulation',
                'name' => 'String Manipulation',
                'category' => 'Infrastructure',
                'icon' => 'language',
                'description' => 'Case conversion (camel/snake/kebab/title), Levenshtein and Jaro-Winkler similarity for fuzzy matching, masking helpers for PII (credit cards, emails), Unicode-aware truncation, and template interpolation. The layer underneath search ranking, change-tracking, and user-facing text rendering.',
            ],
            [
                'slug' => 'pagination',
                'name' => 'Pagination',
                'category' => 'Infrastructure',
                'icon' => 'list-bullet',
                'description' => 'Cursor + offset pagination primitives with consistent response shapes (`{data, meta: {next_cursor, prev_cursor, per_page}}`), PagedResult value types, and helpers for the common "give me the next 25" + "page through 10k records without timing out" patterns. The standard shape every Codex-style API endpoint uses.',
            ],
            [
                'slug' => 'color-utilities',
                'name' => 'Color Utilities',
                'category' => 'Content',
                'icon' => 'swatch',
                'description' => 'Color model conversion (RGB / HSL / HSV / Hex / CMYK), WCAG contrast-ratio calculation, color-harmony generation (complementary, triadic, analogous), and design-token formatting for CSS / Tailwind / iOS / Android export. The toolkit for shipping a coherent design system across web + mobile.',
            ],
        ];
    }
}
