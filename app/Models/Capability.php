<?php

namespace App\Models;

use App\Models\Pivots\ProjectCapabilityPivot;
use Database\Factories\CapabilityFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Capability extends Model
{
    /** @use HasFactory<CapabilityFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'name', 'slug', 'category', 'description', 'description_reviewed',
        'icon', 'canonical_id',
    ];

    protected function casts(): array
    {
        return [
            'description_reviewed' => 'boolean',
        ];
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_capabilities')
            ->using(ProjectCapabilityPivot::class)
            ->withPivot('id', 'is_primary')
            ->withTimestamps();
    }

    /**
     * The capability this one was merged into (an alias points to its
     * terminal canonical). Null on a canonical row.
     */
    public function canonical(): BelongsTo
    {
        return $this->belongsTo(self::class, 'canonical_id');
    }

    /**
     * Rows that have been merged into this one.
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(self::class, 'canonical_id');
    }

    /**
     * Returns the terminal canonical for this row — i.e. itself if a
     * canonical, or the row pointed to by canonical_id if an alias.
     *
     * The merge rule guarantees one-hop resolution: when admin merges
     * C into B and B is already an alias of A, the action rewrites
     * C.canonical_id = A (terminal canonical), not B. So this method
     * never has to chain — but defends against drift with a short loop.
     */
    public function resolveCanonical(): self
    {
        $current = $this;
        $seen = [];
        while ($current->canonical_id !== null) {
            if (isset($seen[$current->id])) {
                // Cycle — should be impossible via MergeCapability action,
                // but if it happened (e.g. raw SQL bypass), return self
                // rather than loop forever.
                return $this;
            }
            $seen[$current->id] = true;
            $current = $current->canonical;
            if ($current === null) {
                return $this;
            }
        }

        return $current;
    }

    /**
     * Whether $this can be merged into $target. Returns a result object
     * with a reason on rejection so the Filament action can surface it.
     *
     * Note: if $target is itself an alias, the merge IS allowed — the
     * action resolves the target to its terminal canonical before
     * writing canonical_id, per the plan's "Always rewrite to the
     * terminal canonical" rule. The UI hides aliased rows from the
     * picker as a UX cue; the action's transitivity rewrite is the
     * load-bearing semantic.
     */
    public function canBeMergedInto(self $target): MergeEligibility
    {
        if ($target->id === $this->id) {
            return MergeEligibility::rejected('Cannot merge a capability into itself.');
        }

        // Cycle prevention: $target's canonical chain must not terminate
        // at $this. resolveCanonical handles both direct-target and
        // alias-of-alias forms.
        if ($target->resolveCanonical()->id === $this->id) {
            return MergeEligibility::rejected('Merge would create a cycle — the target already resolves to this row.');
        }

        return MergeEligibility::allowed();
    }
}
