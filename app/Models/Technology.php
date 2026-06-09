<?php

namespace App\Models;

use App\Models\Pivots\ProjectTechnologyPivot;
use Database\Factories\TechnologyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Technology extends Model
{
    /** @use HasFactory<TechnologyFactory> */
    use HasFactory, HasUlids;

    protected $fillable = ['name', 'slug', 'category', 'icon_url', 'vendor_url'];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_technologies')
            ->using(ProjectTechnologyPivot::class)
            ->withPivot('id', 'is_primary')
            ->withTimestamps();
    }
}
