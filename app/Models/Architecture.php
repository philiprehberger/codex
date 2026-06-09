<?php

namespace App\Models;

use App\Models\Pivots\ProjectArchitecturePivot;
use Database\Factories\ArchitectureFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Architecture extends Model
{
    /** @use HasFactory<ArchitectureFactory> */
    use HasFactory, HasUlids;

    protected $fillable = ['name', 'slug', 'description'];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_architectures')
            ->using(ProjectArchitecturePivot::class)
            ->withPivot('id')
            ->withTimestamps();
    }
}
