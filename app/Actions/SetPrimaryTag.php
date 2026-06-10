<?php

namespace App\Actions;

use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sets the is_primary flag on a single (project, tag) row across one
 * dimension while clearing any other primary on the same dimension.
 *
 * Transactional with `SELECT … FOR UPDATE` on the project row so two
 * concurrent admin clicks resolve to a single primary, not both. Backed
 * by READ COMMITTED isolation (per config/database.php) so the lock
 * doesn't gap-lock unrelated pivot rows.
 *
 * Used by Filament's primary-radio (Phase 3). Direct pivot writes
 * (e.g. $project->capabilities()->updateExistingPivot(...)) bypass this
 * action — the nightly codex:assert-invariants cron is the always-on
 * safety net that finds drift.
 */
class SetPrimaryTag
{
    /**
     * @param  Project  $project  Owning project.
     * @param  string  $relation  Method name on Project — capabilities|technologies.
     * @param  string  $tagId  ULID of the tag to mark primary.
     */
    public function execute(Project $project, string $relation, string $tagId): void
    {
        if (! in_array($relation, ['capabilities', 'technologies'], true)) {
            throw new InvalidArgumentException("Dimension '{$relation}' does not support is_primary.");
        }

        DB::transaction(function () use ($project, $relation, $tagId) {
            // Lock the project row so two concurrent writes can't both
            // promote a different tag to primary.
            Project::where('id', $project->id)->lockForUpdate()->firstOrFail();

            $pivotTable = $relation === 'capabilities' ? 'project_capabilities' : 'project_technologies';
            $tagFk = $relation === 'capabilities' ? 'capability_id' : 'technology_id';

            // Clear any existing primary on this dimension. Done as a raw
            // pivot update because $project->$relation()->updateExistingPivot
            // would only operate on a specific tag id.
            DB::table($pivotTable)
                ->where('project_id', $project->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false, 'updated_at' => now()]);

            // Set the new primary. If the (project, tag) pair isn't
            // attached yet, attach it. updateOrInsert is cleaner than
            // attach() + updateExistingPivot for the upsert shape.
            $exists = DB::table($pivotTable)
                ->where('project_id', $project->id)
                ->where($tagFk, $tagId)
                ->exists();

            if ($exists) {
                DB::table($pivotTable)
                    ->where('project_id', $project->id)
                    ->where($tagFk, $tagId)
                    ->update(['is_primary' => true, 'updated_at' => now()]);
            } else {
                DB::table($pivotTable)->insert([
                    'id' => (string) Str::ulid(),
                    'project_id' => $project->id,
                    $tagFk => $tagId,
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
}
