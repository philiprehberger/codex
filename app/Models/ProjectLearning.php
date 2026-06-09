<?php

namespace App\Models;

use Database\Factories\ProjectLearningFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectLearning extends Model
{
    /** @use HasFactory<ProjectLearningFactory> */
    use HasFactory, HasUlids;

    protected $fillable = ['project_id', 'title', 'description', 'visibility'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
