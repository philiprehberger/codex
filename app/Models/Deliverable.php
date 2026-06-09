<?php

namespace App\Models;

use App\Models\Pivots\ProjectDeliverablePivot;
use Database\Factories\DeliverableFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Deliverable extends Model
{
    /** @use HasFactory<DeliverableFactory> */
    use HasFactory, HasUlids;

    protected $fillable = ['name', 'slug'];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_deliverables')
            ->using(ProjectDeliverablePivot::class)
            ->withPivot('id')
            ->withTimestamps();
    }
}
