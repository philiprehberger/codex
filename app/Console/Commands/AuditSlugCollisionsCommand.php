<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Rules\SlugRule;
use Illuminate\Console\Command;

/**
 * SlugRule rejects reserved-word slugs at write time by reading
 * Route::getRoutes(). But a route added in a later phase (e.g. Phase 6
 * adds /search after a seed that already shipped a project with
 * slug=search) can shadow an already-valid slug. This command diffs
 * every projects.slug against the live route table and reports drift.
 *
 * Runs nightly via the schedule (routes/console.php). Exits non-zero
 * on collision so cron silence is observable.
 */
class AuditSlugCollisionsCommand extends Command
{
    protected $signature = 'codex:audit-slug-collisions';
    protected $description = 'Diffs projects.slug against the live route table; flags slug ↔ route-name collisions.';

    public function handle(): int
    {
        $reserved = array_flip(SlugRule::reservedFirstSegments());
        $collisions = [];

        Project::withTrashed()->withoutGlobalScope(\App\Models\Scopes\RedactedScope::class)
            ->select('id', 'slug', 'visibility')
            ->orderBy('slug')
            ->chunk(200, function ($projects) use ($reserved, &$collisions) {
                foreach ($projects as $project) {
                    if (isset($reserved[$project->slug])) {
                        $collisions[] = [
                            'project_id' => $project->id,
                            'slug' => $project->slug,
                            'visibility' => $project->visibility,
                        ];
                    }
                }
            });

        if ($collisions === []) {
            $this->info('codex:audit-slug-collisions — clean.');
            return self::SUCCESS;
        }

        foreach ($collisions as $c) {
            $this->error(sprintf(
                'collision: project %s (%s, visibility=%s) shadows a reserved/routed name',
                $c['project_id'],
                $c['slug'],
                $c['visibility'],
            ));

            if (app()->bound('sentry')) {
                app('sentry')->captureMessage(
                    sprintf('codex slug-collision: %s (project %s)', $c['slug'], $c['project_id']),
                    \Sentry\Severity::warning(),
                );
            }
        }

        $this->error(sprintf('codex:audit-slug-collisions — %d collision(s).', count($collisions)));
        return self::FAILURE;
    }
}
