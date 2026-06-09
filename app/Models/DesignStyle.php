<?php

namespace App\Models;

use App\Models\Pivots\ProjectDesignStylePivot;
use Database\Factories\DesignStyleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DesignStyle extends Model
{
    /** @use HasFactory<DesignStyleFactory> */
    use HasFactory, HasUlids;

    protected $fillable = ['name', 'slug'];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_design_styles')
            ->using(ProjectDesignStylePivot::class)
            ->withPivot('id')
            ->withTimestamps();
    }
}
