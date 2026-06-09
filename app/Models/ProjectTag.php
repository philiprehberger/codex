<?php

namespace App\Models;

use App\Models\Pivots\ProjectTagMapPivot;
use Database\Factories\ProjectTagFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Lookup table — free-form tags for things that don't fit the structured
 * dimensions. The matching pivot is `project_tags_map` (the `_map` suffix
 * avoids the table-name collision; load-bearing, not drift).
 */
class ProjectTag extends Model
{
    /** @use HasFactory<ProjectTagFactory> */
    use HasFactory, HasUlids;

    protected $table = 'project_tags';

    protected $fillable = ['name', 'slug'];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_tags_map')
            ->using(ProjectTagMapPivot::class)
            ->withPivot('id')
            ->withTimestamps();
    }
}
