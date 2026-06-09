<?php

namespace App\Models;

use Database\Factories\ProjectMetricFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMetric extends Model
{
    /** @use HasFactory<ProjectMetricFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'project_id', 'recorded_at',
        'duration_days', 'api_integrations', 'database_tables', 'test_count',
        'lighthouse_perf', 'lighthouse_a11y', 'lighthouse_best', 'lighthouse_seo',
        'loc_total', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'date',
            'duration_days' => 'integer',
            'api_integrations' => 'integer',
            'database_tables' => 'integer',
            'test_count' => 'integer',
            'lighthouse_perf' => 'integer',
            'lighthouse_a11y' => 'integer',
            'lighthouse_best' => 'integer',
            'lighthouse_seo' => 'integer',
            'loc_total' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
