<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

/**
 * Dev-only. Walks ~/projects/packages/<language>/<package>/ and writes
 * a per-package fixture row into database/fixtures/packages.json.
 *
 * Why a fixture: CI cannot read ~/projects/packages/. The PackagesSeeder
 * loads from the committed fixture so production runs without the
 * source tree.
 *
 * Per-package classification:
 *  - description: package.json/composer.json/Cargo.toml/etc when present;
 *    fall back to the first non-heading paragraph of README.md;
 *    fall back to the humanized package name
 *  - language: top-level directory name (typescript/php/python/…)
 *  - registry: derived from language (npm/packagist/pypi/…)
 *  - capability_slugs: name-pattern heuristic across the 45-row
 *    capability vocabulary
 *  - repo_url: github.com/philiprehberger/<slug-name>
 */
class IngestPackagesCommand extends Command
{
    protected $signature = 'codex:ingest-packages
        {--source=~/projects/packages : root of the packages collection}
        {--output=database/fixtures/packages.json : where to write the fixture}';

    protected $description = 'Walks ~/projects/packages and writes a per-package fixture for the PackagesSeeder.';

    /** @var array<string, string> language → registry */
    private const REGISTRY_BY_LANG = [
        'typescript' => 'npm',
        'php' => 'packagist',
        'python' => 'pypi',
        'ruby' => 'rubygems',
        'go' => 'go',
        'rust' => 'cargo',
        'dotnet' => 'nuget',
        'kotlin' => 'maven',
        'swift' => 'swiftpm',
        'dart' => 'pub',
        'elixir' => 'hex',
        // card/ is the digital-business-card project, NOT a package
        // collection — kept out so its node_modules-style subdirs don't
        // get pulled in as fake packages.
    ];

    /** @var array<string, array<int, string>> capability slug → name patterns */
    private const CAPABILITY_PATTERNS = [
        'caching' => ['cache', 'memo', 'memoize', 'lru', 'ttl-store', 'local-storage'],
        'rate-limiting' => ['rate-limit', 'rate-limiter', 'ratelimit', 'throttle', 'token-bucket', 'sliding-window', 'debounce'],
        'webhook-delivery' => ['webhook', 'signing-helper'],
        'webhook-receiving' => ['webhook-receiver', 'hmac-verify'],
        'rest-api' => ['api-client', 'api-kit', 'api-builder', 'api-error', 'api-versioning', 'api-response', 'fetch', 'http-retry', 'http-client', 'http-helper', 'http-status', 'rest', 'jsonrpc', 'graphql-client', 'http-mock', 'http-debug', 'http-handler', 'url-builder', 'url-clean', 'url-encode', 'middleware', 'response-macros', 'laravel-api-versioning', 'net-kit', 'typed-router', 'path-pattern', 'glob-match', 'glob-matcher', 'glob-kit'],
        'oauth-connectors' => ['oauth', 'auth-kit', 'token-kit'],
        'authentication' => ['auth', 'jwt', 'session-kit', 'password', 'totp', 'csrf', 'secure-store', 'credential', 'keychain'],
        'authorization' => ['rbac', 'policy', 'gate-kit', 'permission'],
        'observability' => ['log', 'audit', 'metric', 'tracing', 'sentry', 'observability', 'correlation', 'healthcheck', 'health-check', 'health-check-kit', 'service-health', 'circuit', 'retry', 'clock', 'change-tracker', 'tracker', 'inspector', 'profiler', 'timer', 'ip-range', 'ip-addr', 'ip-kit', 'ip-utils', 'security-headers', 'exception-reporter', 'stopwatch', 'dev-console', 'git-analyzer', 'server-monitor', 'net-scanner', 'portcheck', 'port-check', 'req-check', 'errstack', 'rate-counter', 'rate-window', 'bench-utils'],
        'background-queue' => ['queue', 'worker', 'job-kit', 'async-batcher', 'async-queue', 'batch-processor', 'batch', 'background-jobs', 'outbox'],
        'scheduled-jobs' => ['cron', 'scheduler', 'schedule-kit'],
        'workflow-engine' => ['workflow', 'state-machine', 'fsm', 'sync-engine', 'task-graph', 'task-runner', 'task-dependency', 'pipeline', 'state-kit', 'ts-pipe', 'rb-pipe', 'kt-pipe'],
        'rule-engine' => ['rule', 'feature-flag', 'featureflag', 'flag-kit', 'validator', 'validation', 'guard-clause', 'precondition'],
        'document-generation' => ['pdf', 'docx', 'render', 'template', 'markdown-render', 'csv-render'],
        'cms' => ['cms-helper', 'block-kit', 'page-builder'],
        'media-library' => ['cloudinary', 'image', 'media', 'thumbnail', 'photo'],
        'markdown-editor' => ['markdown-edit', 'mdx', 'prose-mirror'],
        'search' => ['search', 'fuzzy', 'index-kit'],
        'event-tracking' => ['analytics', 'event-tracker', 'plausible'],
        'dashboards' => ['dashboard-kit', 'widget-kit', 'chart-kit', 'sparkline'],
        'reporting' => ['csv', 'export', 'reporting', 'xlsx', 'parquet', 'math-kit'],
        'crm-sync' => ['hubspot', 'salesforce', 'crm-sync', 'pipedrive'],
        'notifications' => ['notification', 'slack', 'discord', 'pushover', 'twilio', 'webhook-notify'],
        'email-campaigns' => ['mail', 'email', 'mailgun', 'postmark', 'mailchimp', 'sendgrid', 'resend'],
        'lead-capture' => ['form', 'lead', 'submission', 'phone', 'locale-kit'],
        'payments' => ['stripe', 'paddle', 'payment', 'credit-card', 'iban'],
        'subscriptions' => ['subscription', 'billing-cycle'],
        'invoicing' => ['invoice'],
        'cart-checkout' => ['cart', 'checkout'],
        'gdpr-compliance' => ['gdpr', 'pii', 'data-subject', 'erasure', 'mask', 'redact'],
        'data-export' => ['export-kit', 'sql-dump', 'backup-kit', 'dump', 'tar', 'zip-archive'],
        'real-time-sync' => ['sse', 'websocket', 'realtime', 'pubsub', 'event-emitter', 'event-bus', 'signal-kit', 'observable', 'reactive', 'mqtt'],
        'file-upload-pipeline' => ['upload', 'multipart', 'file-kit', 'file-size', 'filesize'],
        'virus-scanning' => ['clamav', 'virus'],
        'atomic-deploys' => ['deploy', 'release-kit', 'rolling-update'],
        'multi-tenant' => ['tenant', 'workspace-kit'],
        'user-onboarding' => ['onboarding', 'welcome'],
        'profile-management' => ['profile-kit', 'avatar-kit'],
        'llm-integration' => ['llm', 'openai', 'anthropic', 'claude-kit', 'gemini', 'gpt'],
        'content-moderation' => ['moderation', 'spam', 'profanity', 'sanitize', 'sanitizer'],
        'semantic-search' => ['embedding', 'vector', 'pgvector', 'hnsw'],
        'ab-testing' => ['ab-test', 'experiment', 'split-test'],
        'landing-pages' => ['hero-kit', 'lp-kit'],
        'seo-optimisation' => ['seo', 'meta-kit', 'sitemap', 'og-image', 'json-ld', 'robots-kit'],
        'third-party-sync' => ['sync-kit', 'integration-kit'],

        // ──────────────────────────────────────────────── Phase 8.4 primitives
        'error-handling' => ['result-type', 'result', 'either', 'option-type', 'maybe', 'safe-json', 'safe-parse', 'safe-timeout', 'safe-regex', 'safe-file-writer', 'try-kit', 'rb-try', 'fallback', 'guard', 'exception-handler', 'operation-result', 'func-timeout', 'timeout-kit', 'deprecate', 'stacktrace-fmt'],
        'id-generation' => ['ulid', 'nanoid', 'snowflake', 'uuid', 'cuid', 'slug', 'id-gen', 'id-generator', 'prefixed-id', 'compact-id', 'ts-uid'],
        'money-currency' => ['money', 'currency', 'iso-4217', 'allocator', 'monetary'],
        'date-time-utilities' => ['date', 'duration', 'time-zone', 'timezone', 'business-day', 'relative-time', 'cron-expression', 'db-expressions', 'timeutil', 'time-util'],
        'cli-tools' => ['cli-', 'docstring-cli', 'rb-cli', 'terminal', 'prompt-kit', 'prompt-builder', 'spinner', 'progress-bar', 'progress', 'ansi', 'tty', 'install-doctor', 'doctor', 'dev-docs-kit', 'sql-print', 'web-scraper', 'make-service', 'clipboard'],
        'configuration-loading' => ['config', 'env-file', 'env-validator', 'env-expand', 'env-loader', 'dotenv', 'dart-env', 'config-diff', 'config-kit', 'config-loader', 'config-validator', 'settings', 'expression-evaluator'],
        'json-processing' => ['json-merge', 'json-patch', 'json-flatten', 'flatten-json', 'json-path', 'jsonpath', 'json-schema', 'json-kit', 'ini-parser', 'toml-kit', 'toml-parse'],
        'string-manipulation' => ['string-ext', 'string-similarity', 'string-kit', 'case-convert', 'case-kit', 'levenshtein', 'jaro', 'humanize', 'truncate', 'titlecase', 'regex-kit', 'regex-lib', 'regex-builder'],
        'pagination' => ['pagination', 'cursor-paginate', 'paged-result'],
        'color-utilities' => ['color', 'palette', 'wcag', 'contrast', 'design-token'],

        // ──────────────────────────────────────────────── Phase 8.5 primitives
        'cryptography' => ['hash', 'hmac', 'sha-256', 'sha-512', 'md5', 'crc32', 'base-encoding', 'base-convert', 'mime-detect', 'mime-type', 'magic-bytes', 'safe-yaml', 'safe-exec', 'safe-shutdown', 'encrypt', 'decrypt', 'crypt', 'argon2', 'base64-url', 'rb-hex', 'obfuscator', 'checksum', 'signed-payload'],
        'version-management' => ['semver', 'version-compare', 'version-bump', 'semantic-version'],
        'testing-utilities' => ['test-factory', 'test-data', 'test-utils', 'snapshot-test', 'snapshot-kit', 'snapshot', 'data-faker', 'data-factory', 'fake-data', 'fixture-kit', 'fixture', 'http-test', 'mock-server', 'rs-bench'],

        // ──────────────────────────────────────────────── Phase 8.7 primitives
        'object-collection-ops' => ['deep-clone', 'deep-merge', 'deep-freeze', 'deep-equal', 'deep-diff', 'flatten', 'unflatten', 'pick', 'omit', 'group-by', 'chunk-list', 'zip-array', 'partition', 'collection-ext', 'array-query', 'array-utils', 'map-utils', 'dict-merge', 'dotpath', 'dot-access', 'safeget', 'sliceutil', 'maputil', 'list-chunk', 'data-mapper'],
        'filesystem-utilities' => ['file-watcher', 'file-organizer', 'file-walker', 'path-kit', 'fs-utils', 'file-tree', 'directory-walker', 'safe-file-writer', 'safe-write', 'fswatch', 'fs-watch', 'pathname-kit', 'temp-file', 'duplicate-finder', 'temp-env', 'embedded-resource'],
        'diff-tracking' => ['diff-strings', 'diff-kit', 'diff-lib', 'model-diff', 'change-set', 'json-diff', 'php-diff', 'text-diff', 'object-diff', 'struct-diff', 'structdiff', 'differ', 'env-diff', 'schema-diff'],

        // ──────────────────────────────────────────────── Phase 8.8 primitives
        'type-enum-utilities' => ['enum-kit', 'enum-tools', 'enum-toolkit', 'enum-utils', 'enum-ext', 'rich-enum', 'ts-enum', 'rb-enum', 'type-parse', 'type-check', 'type-conv', 'typeconv', 'typeguard', 'value-of', 'value-object', 'invariant', 'predicate', 'specification', 'pattern-match', 'match-ts', 'micro-schema', 'schema-infer', 'zod-defaults', 'approx', 'mapper', 'dto-mapper'],
        'concurrency-primitives' => ['async-lock', 'semaphore', 'mutex', 'promise-pool', 'disposable-pool', 'object-pool', 'parallel-each', 'run-parallel', 'progress-map', 'parallel-map', 'parallel', 'abort-kit', 'abort-controller', 'graceful', 'graceful-shutdown', 'disposer', 'safechan', 'safe-channel', 'once', 'singleton', 'di-container', 'py-di', 'state-bag', 'polling', 'poll-until', 'lock-run', 'lock-kit', 'rs-pool', 'rb-pool', 'job-meter'],
        'data-structures' => ['tree-structure', 'tree-kit', 'graph', 'dependency-graph', 'ring-buffer', 'bloom-filter', 'bit-flags', 'bit-field', 'sorted-array', 'interval', 'counter', 'expiring-map', 'timeout-map', 'tiny-store', 'event-store', 'secret-store', 'struct-kit', 'rb-tree', 'dn-tree-structure'],
        'http-middleware' => ['cors', 'etag', 'header-kit', 'typed-headers', 'cookie-ts', 'cookie-kit', 'http-error', 'http-errors', 'httputil', 'respond', 'query-string-ts', 'uri-kit', 'web-vitals', 'next-layout', 'cookie'],
        'string-text-formatting' => ['str-case', 'case-conv', 'ts-case', 'inflector', 'pluralize', 'pluralizer', 'natural-sort', 'word-wrap', 'human-size', 'byte-fmt', 'bytes-ts', 'text-table', 'table-fmt', 'rb-table', 'timeago', 'time-ago', 'str-utils', 'core-utils', 'unit-converter', 'badge-generator', 'random-readable-string', 'randstr', 'random-data', 'html-builder', 'xml-builder', 'encoding-kit', 'hex-dump', 'word-stem', 'sql-print', 'stacktrace'],
        'ui-component-primitives' => ['react-hooks', 'react-ui-kit', 'react-ui-primitives', 'react-theme-provider', 'react-motion-components', 'framer-motion-presets', 'next-layout-components', 'flutter-animation-kit', 'design-system', 'ui-kit', 'theme-provider', 'animation-kit', 'motion'],
        'geospatial' => ['geo', 'geolocation', 'geo-point', 'geohash', 'haversine', 'lat-lng'],
    ];

    public function handle(): int
    {
        if (! App::isLocal()) {
            $this->error('codex:ingest-packages is dev-only.');

            return self::FAILURE;
        }

        $source = $this->expandHome((string) $this->option('source'));
        if (! File::isDirectory($source)) {
            $this->error("source directory not found: {$source}");

            return self::FAILURE;
        }

        $output = base_path((string) $this->option('output'));
        File::ensureDirectoryExists(dirname($output));

        $rows = [];
        foreach (Finder::create()->directories()->in($source)->depth(0)->sortByName() as $langDir) {
            $language = $langDir->getFilename();
            if (! isset(self::REGISTRY_BY_LANG[$language])) {
                $this->warn("skipping non-language dir: {$language}");

                continue;
            }
            $registry = self::REGISTRY_BY_LANG[$language];

            foreach (Finder::create()->directories()->in($langDir->getRealPath())->depth(0)->sortByName() as $pkgDir) {
                $name = $pkgDir->getFilename();
                if (str_starts_with($name, '.') || $name === 'venv' || $name === 'node_modules') {
                    continue;
                }

                $slug = $this->slugFor($language, $name);
                $description = $this->extractDescription($pkgDir->getRealPath(), $language, $name);
                $capabilities = $this->classifyCapabilities($name, $description);

                $rows[] = [
                    'slug' => $slug,
                    'name' => '@philiprehberger/'.$name,
                    'language' => $language,
                    'registry' => $registry,
                    'status' => 'active',
                    'short_description' => $description,
                    'repo_url' => 'https://github.com/philiprehberger/'.$this->repoNameFor($language, $name),
                    'registry_url' => $this->registryUrlFor($language, $name),
                    'capability_slugs' => $capabilities,
                    'primary_capability' => $capabilities[0] ?? null,
                ];
            }
        }

        File::put(
            $output,
            json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );

        $this->info(sprintf('wrote %d package rows to %s', count($rows), $output));

        return self::SUCCESS;
    }

    /**
     * Slugs are language-prefixed so two packages with the same name in
     * different languages don't collide. E.g. cache-kit (TS) → ts-cache-kit;
     * the existing repos under philiprehberger/ already follow the same
     * convention for non-namespaced languages (py-, rb-, go-).
     */
    private function slugFor(string $language, string $name): string
    {
        $prefix = match ($language) {
            'typescript' => 'ts-',
            'php' => 'php-',
            'python' => 'py-',
            'ruby' => 'rb-',
            'go' => 'go-',
            'rust' => 'rs-',
            'dotnet' => 'dn-',
            'kotlin' => 'kt-',
            'swift' => 'sw-',
            'dart' => 'dart-',
            'elixir' => 'ex-',
            'card' => 'card-',
            default => '',
        };

        // If the package name already starts with the language prefix
        // (py-, rb-, go-, etc), don't double-prefix.
        if (str_starts_with($name, rtrim($prefix, '-'))) {
            return $name;
        }

        return $prefix.$name;
    }

    /**
     * GitHub repo name. Many of Philip's packages follow patterns:
     *   typescript/cache-kit       → ts-cache-kit
     *   php/laravel-feature-flags  → laravel-feature-flags (already prefixed)
     *   python/py-cache-kit         → py-cache-kit (already prefixed)
     */
    private function repoNameFor(string $language, string $name): string
    {
        return $this->slugFor($language, $name);
    }

    private function registryUrlFor(string $language, string $name): ?string
    {
        return match ($language) {
            'typescript' => 'https://www.npmjs.com/package/@philiprehberger/'.$name,
            'php' => 'https://packagist.org/packages/philiprehberger/'.$name,
            'python' => 'https://pypi.org/project/philiprehberger-'.ltrim($name, 'py-'),
            'ruby' => 'https://rubygems.org/gems/'.$name,
            'rust' => 'https://crates.io/crates/'.$name,
            'go' => null,
            default => null,
        };
    }

    private function extractDescription(string $pkgDir, string $language, string $name): string
    {
        // 1. Try language-native metadata.
        $fromMeta = $this->descriptionFromMetadata($pkgDir, $language);
        if ($fromMeta !== null) {
            return $fromMeta;
        }

        // 2. README first non-heading paragraph.
        $readme = $pkgDir.'/README.md';
        if (File::exists($readme)) {
            $content = File::get($readme);
            // Strip badges + headings.
            $lines = preg_split('/\R/u', $content) ?: [];
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '[!') || str_starts_with($trimmed, '![')) {
                    continue;
                }
                if (str_starts_with($trimmed, '```') || str_starts_with($trimmed, '<')) {
                    continue;
                }

                return mb_substr(trim($trimmed), 0, 280);
            }
        }

        // 3. Humanize the package name.
        return ucfirst(str_replace('-', ' ', $name));
    }

    private function descriptionFromMetadata(string $pkgDir, string $language): ?string
    {
        try {
            return match ($language) {
                'typescript' => $this->jsonField($pkgDir.'/package.json', 'description'),
                'php' => $this->jsonField($pkgDir.'/composer.json', 'description'),
                'rust' => $this->tomlField($pkgDir.'/Cargo.toml', 'description'),
                'python' => $this->pyprojectField($pkgDir.'/pyproject.toml'),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    private function jsonField(string $path, string $field): ?string
    {
        if (! File::exists($path)) {
            return null;
        }
        $data = json_decode(File::get($path), true);

        return is_array($data) && isset($data[$field]) ? mb_substr((string) $data[$field], 0, 280) : null;
    }

    private function tomlField(string $path, string $field): ?string
    {
        if (! File::exists($path)) {
            return null;
        }
        // Crude one-line parser: looks for `field = "..."` at the start of a line.
        $content = File::get($path);
        if (preg_match('/^\s*'.preg_quote($field, '/').'\s*=\s*"([^"]*)"/m', $content, $m)) {
            return mb_substr($m[1], 0, 280);
        }

        return null;
    }

    private function pyprojectField(string $path): ?string
    {
        if (! File::exists($path)) {
            return null;
        }
        $content = File::get($path);
        if (preg_match('/^\s*description\s*=\s*"([^"]*)"/m', $content, $m)) {
            return mb_substr($m[1], 0, 280);
        }

        return null;
    }

    /** @return array<int, string> */
    /**
     * Phrases scanned against the package's short_description that, when
     * present, attach a *secondary* capability beyond what the slug
     * patterns matched. Kept narrow on purpose — only signals that a
     * one-line description reliably contains when the package genuinely
     * serves a second domain. Phrases are case-insensitive substrings.
     */
    private const DESCRIPTION_PATTERNS = [
        'concurrency-primitives' => ['async-aware', 'thread-safe', 'concurren', 'parallel', 'stream transformer', 'await', 'channel-aware', 'condition variable', 'long-poll'],
        'error-handling' => ['retry with', 'exponential backoff', 'circuit breaker', 'fallback support', 'guard claus', 'parameter valid', 'panic-free', 'recovery'],
        'observability' => ['audit log', 'audit trail', 'log every', 'instrument', 'metrics', 'tracing', 'profiling'],
        'cryptography' => ['encryption', 'pbkdf2', 'argon2', 'aes-256', 'hmac', 'hash-based', 'cryptographic'],
        'authentication' => ['jwt token', 'oauth', 'session token', 'csrf', 'credential storage'],
        'http-middleware' => ['middleware', 'asp.net core', 'rack middleware', 'delegatinghandler', 'http header'],
        'diff-tracking' => ['diff for', 'detect added', 'detect changes', 'property changes', 'change track'],
        'filesystem-utilities' => ['file path', 'directory', 'on disk', 'atomic write', 'file system event'],
        'data-structures' => ['ring buffer', 'sliding window', 'topological sort', 'tree traversal', 'bloom filter'],
        'object-collection-ops' => ['deep merge', 'deep clone', 'object-to-object', 'group by', 'configurable strategies'],
        'string-text-formatting' => ['url-safe', 'base32', 'base62', 'base64url', 'human-readable', 'pluraliz', 'natural sort'],
        'ui-component-primitives' => ['react component', 'react hook', 'flutter widget', 'design tokens', 'theme switching', 'debug overlay'],
        'real-time-sync' => ['conflict resol', 'offline-first', 'sync engine', 'event-driven'],
        'background-queue' => ['dead-letter', 'job queue', 'background task'],
        'id-generation' => ['obfuscat', 'sortable id', 'url-safe public id'],
        'rate-limiting' => ['throttle', 'debounce', 'rate-limit', 'leading/trailing'],
    ];

    /**
     * @return list<string>
     */
    private function classifyCapabilities(string $name, ?string $description = null): array
    {
        $matched = [];
        foreach (self::CAPABILITY_PATTERNS as $capabilitySlug => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos($name, $pattern) !== false) {
                    $matched[] = $capabilitySlug;

                    break; // one capability per pattern group
                }
            }
        }

        // Description-level pass — only fires if a one-line summary
        // exists. Adds *additional* capability tags when the description
        // contains a strong dual-purpose signal (e.g. "middleware",
        // "exponential backoff", "JWT token"). Never replaces the
        // slug-derived primary.
        if ($description !== null && $description !== '') {
            foreach (self::DESCRIPTION_PATTERNS as $capabilitySlug => $phrases) {
                if (in_array($capabilitySlug, $matched, true)) {
                    continue;
                }
                foreach ($phrases as $phrase) {
                    if (stripos($description, $phrase) !== false) {
                        $matched[] = $capabilitySlug;

                        break;
                    }
                }
            }
        }

        return array_values(array_unique($matched));
    }

    private function expandHome(string $path): string
    {
        if (str_starts_with($path, '~/')) {
            return ($_SERVER['HOME'] ?? getenv('HOME') ?: '').substr($path, 1);
        }

        return $path;
    }
}
