<?php

namespace App\Models;

use App\Models\Pivots\ProjectArchitecturePivot;
use App\Models\Pivots\ProjectCapabilityPivot;
use App\Models\Pivots\ProjectDeliverablePivot;
use App\Models\Pivots\ProjectDesignStylePivot;
use App\Models\Pivots\ProjectIndustryPivot;
use App\Models\Pivots\ProjectTagMapPivot;
use App\Models\Pivots\ProjectTechnologyPivot;
use App\Models\Scopes\RedactedScope;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'slug', 'name', 'project_type', 'status', 'visibility',
        'visibility_changed_at',
        'repo_url', 'live_url', 'docs_url',
        'short_description', 'long_description', 'long_description_reviewed',
        'client_name', 'client_industry',
        'shipped_date', 'hours_estimated', 'hours_actual', 'team_size',
        'internal_notes',
    ];

    /**
     * internal_notes is the serialiser-level defence — even an unscoped
     * toArray() / API resource transformer doesn't accidentally emit it.
     * client_name relies on RedactedScope at the query layer (so unscoped
     * Filament access still sees it).
     */
    protected $hidden = ['internal_notes'];

    protected function casts(): array
    {
        return [
            'visibility_changed_at' => 'datetime',
            'shipped_date' => 'date',
            'long_description_reviewed' => 'boolean',
            'hours_estimated' => 'integer',
            'hours_actual' => 'integer',
            'team_size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new RedactedScope());

        // shipped requires shipped_date + hours_actual. Enforced from the
        // observer so direct Project::create([...]) writes from seeders /
        // CLI / Tinker fail loud — not just Filament. The Filament form
        // validator duplicates the rule for UX.
        static::saving(function (self $project) {
            if ($project->status === 'shipped') {
                $missing = [];
                if (empty($project->shipped_date)) {
                    $missing['shipped_date'] = ['shipped_date is required when status is shipped'];
                }
                if ($project->hours_actual === null) {
                    $missing['hours_actual'] = ['hours_actual is required when status is shipped'];
                }
                if ($missing !== []) {
                    throw ValidationException::withMessages($missing);
                }
            }

            // Stamp visibility_changed_at on any flip away from public so
            // the 14-day 410-Gone window has a start time. Only stamp when
            // the visibility column itself changed — not on every save.
            if ($project->isDirty('visibility')) {
                $project->visibility_changed_at = now();
            }
        });
    }

    public function technologies(): BelongsToMany
    {
        return $this->belongsToMany(Technology::class, 'project_technologies')
            ->using(ProjectTechnologyPivot::class)
            ->withPivot('id', 'is_primary')
            ->withTimestamps();
    }

    public function capabilities(): BelongsToMany
    {
        return $this->belongsToMany(Capability::class, 'project_capabilities')
            ->using(ProjectCapabilityPivot::class)
            ->withPivot('id', 'is_primary')
            ->withTimestamps();
    }

    public function industries(): BelongsToMany
    {
        return $this->belongsToMany(Industry::class, 'project_industries')
            ->using(ProjectIndustryPivot::class)
            ->withPivot('id')
            ->withTimestamps();
    }

    public function architectures(): BelongsToMany
    {
        return $this->belongsToMany(Architecture::class, 'project_architectures')
            ->using(ProjectArchitecturePivot::class)
            ->withPivot('id')
            ->withTimestamps();
    }

    public function deliverables(): BelongsToMany
    {
        return $this->belongsToMany(Deliverable::class, 'project_deliverables')
            ->using(ProjectDeliverablePivot::class)
            ->withPivot('id')
            ->withTimestamps();
    }

    public function designStyles(): BelongsToMany
    {
        return $this->belongsToMany(DesignStyle::class, 'project_design_styles')
            ->using(ProjectDesignStylePivot::class)
            ->withPivot('id')
            ->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ProjectTag::class, 'project_tags_map')
            ->using(ProjectTagMapPivot::class)
            ->withPivot('id')
            ->withTimestamps();
    }

    public function assets(): HasMany
    {
        return $this->hasMany(ProjectAsset::class)->orderBy('display_order');
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(ProjectMetric::class);
    }

    /**
     * Most recent metrics snapshot — resume bullets and aggregations cite
     * the latest row, never an average. Temporal by design: Lighthouse
     * scores drift, test counts grow.
     */
    public function latestMetrics()
    {
        return $this->hasOne(ProjectMetric::class)
            ->latestOfMany('recorded_at');
    }

    public function learnings(): HasMany
    {
        return $this->hasMany(ProjectLearning::class);
    }
}
