<?php

namespace App\Models;

use App\Models\Pivots\ProjectIndustryPivot;
use Database\Factories\IndustryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Industry extends Model
{
    /** @use HasFactory<IndustryFactory> */
    use HasFactory, HasUlids;

    protected $fillable = ['name', 'slug'];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_industries')
            ->using(ProjectIndustryPivot::class)
            ->withPivot('id')
            ->withTimestamps();
    }
}
