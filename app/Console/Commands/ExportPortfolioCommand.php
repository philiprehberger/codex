<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Scopes\RedactedScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Emits a single JSON file with the full catalogue (projects + every
 * pivot + capabilities + metrics). Two use-cases:
 *   (a) Codex outage — the resume-bullet pipeline reads the most-recent
 *       export instead of hitting the dead API
 *   (b) eventual Codex retirement — the data outlives the app
 *
 * Without --include-redacted, RedactedScope is applied (client_name +
 * internal_notes stripped). With the flag, the full row is emitted —
 * intended for the internal backup path only.
 *
 * Default output: storage/app/private/exports/codex-YYYY-MM-DD.json
 * (the local disk's root is already storage/app/private in Laravel 11+).
 * Nightly cron writes one and the same shell that does the mysqldump
 * S3-syncs it alongside (Phase 8 §9).
 */
class ExportPortfolioCommand extends Command
{
    protected $signature = 'codex:export-portfolio
        {--include-redacted : emit client_name + internal_notes on redacted projects}
        {--path= : explicit output path under storage/}';

    protected $description = 'Emits the full Codex catalogue as a single JSON file.';

    public function handle(): int
    {
        $includeRedacted = (bool) $this->option('include-redacted');
        $disk = Storage::disk('local');

        $path = $this->option('path')
            ?: 'exports/codex-'.now()->format('Y-m-d').'.json';

        $query = Project::query()
            ->with([
                'technologies', 'capabilities', 'industries',
                'architectures', 'deliverables', 'designStyles', 'tags',
                'assets', 'metrics', 'learnings',
            ])
            ->orderBy('id');

        if ($includeRedacted) {
            $query->withoutGlobalScope(RedactedScope::class);
        }

        $projects = $query->get()->map(fn (Project $p) => $this->serialise($p));

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'schema_version' => '1.0',
            'include_redacted' => $includeRedacted,
            'count' => $projects->count(),
            'projects' => $projects,
        ];

        $disk->put($path, json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT,
        ));

        $this->info(sprintf(
            'codex:export-portfolio — wrote %d projects to %s',
            $projects->count(),
            $disk->path($path),
        ));

        return self::SUCCESS;
    }

    private function serialise(Project $project): array
    {
        return [
            'id' => $project->id,
            'slug' => $project->slug,
            'name' => $project->name,
            'project_type' => $project->project_type,
            'status' => $project->status,
            'visibility' => $project->visibility,
            'visibility_changed_at' => $project->visibility_changed_at?->toIso8601String(),
            'repo_url' => $project->repo_url,
            'live_url' => $project->live_url,
            'docs_url' => $project->docs_url,
            'short_description' => $project->short_description,
            'long_description' => $project->long_description,
            'long_description_reviewed' => $project->long_description_reviewed,
            'client_name' => $project->client_name,
            'client_industry' => $project->client_industry,
            'shipped_date' => $project->shipped_date?->toDateString(),
            'hours_estimated' => $project->hours_estimated,
            'hours_actual' => $project->hours_actual,
            'team_size' => $project->team_size,
            // internal_notes deliberately omitted — even --include-redacted
            // doesn't lift this; the column is for in-Codex use only.
            'created_at' => $project->created_at?->toIso8601String(),
            'updated_at' => $project->updated_at?->toIso8601String(),
            'technologies' => $project->technologies->map(fn ($t) => [
                'slug' => $t->slug,
                'name' => $t->name,
                'is_primary' => (bool) $t->pivot->is_primary,
            ])->all(),
            'capabilities' => $project->capabilities->map(fn ($c) => [
                'slug' => $c->slug,
                'name' => $c->name,
                'canonical_slug' => $c->resolveCanonical()->slug,
                'is_primary' => (bool) $c->pivot->is_primary,
            ])->all(),
            'industries' => $project->industries->pluck('slug')->all(),
            'architectures' => $project->architectures->pluck('slug')->all(),
            'deliverables' => $project->deliverables->pluck('slug')->all(),
            'design_styles' => $project->designStyles->pluck('slug')->all(),
            'tags' => $project->tags->pluck('slug')->all(),
            'assets' => $project->assets->map(fn ($a) => [
                'type' => $a->asset_type,
                'path' => $a->path,
                'caption' => $a->caption,
                'order' => $a->display_order,
            ])->all(),
            'metrics' => $project->metrics->map(fn ($m) => [
                'recorded_at' => $m->recorded_at?->toDateString(),
                'duration_days' => $m->duration_days,
                'api_integrations' => $m->api_integrations,
                'database_tables' => $m->database_tables,
                'test_count' => $m->test_count,
                'lighthouse' => [
                    'perf' => $m->lighthouse_perf,
                    'a11y' => $m->lighthouse_a11y,
                    'best' => $m->lighthouse_best,
                    'seo' => $m->lighthouse_seo,
                ],
                'loc_total' => $m->loc_total,
            ])->all(),
            'learnings' => $project->learnings
                ->where('visibility', 'public')
                ->map(fn ($l) => ['title' => $l->title, 'description' => $l->description])
                ->values()
                ->all(),
        ];
    }
}
