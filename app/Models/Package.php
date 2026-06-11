<?php

namespace App\Models;

use App\Models\Pivots\PackageCapabilityPivot;
use App\Models\Pivots\PackageTechnologyPivot;
use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sister-table to Project. ~635 rows, one per package across 12
 * registries. Capabilities are shared with projects through the
 * capabilities table; the package_capabilities pivot is the join.
 *
 * `language` is a soft reference to a Technology slug — packages can
 * outlive a renamed Technology row, so no FK. The Filament resource
 * (and the API) resolve the language tech via lookup at read time.
 */
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'slug', 'name', 'language', 'registry', 'status',
        'short_description', 'long_description', 'long_description_reviewed',
        'repo_url', 'registry_url', 'docs_url', 'shipped_date',
        'readme_markdown', 'readme_fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'shipped_date' => 'date',
            'long_description_reviewed' => 'boolean',
            'readme_fetched_at' => 'datetime',
        ];
    }

    /** @return BelongsToMany<Capability, $this, PackageCapabilityPivot> */
    public function capabilities(): BelongsToMany
    {
        return $this->belongsToMany(Capability::class, 'package_capabilities')
            ->using(PackageCapabilityPivot::class)
            ->withPivot('id', 'is_primary')
            ->withTimestamps();
    }

    /**
     * Technologies a package uses. Every package is implicitly tagged with
     * its `language` tech, plus the CI / packaging tools used at release
     * time (github-actions universally, plus npm / composer where the
     * registry implies it). Added 2026-06-11 so the gap report's tech ×
     * industry matrix reflects actual usage, not just demo-project usage.
     *
     * @return BelongsToMany<Technology, $this, PackageTechnologyPivot>
     */
    public function technologies(): BelongsToMany
    {
        return $this->belongsToMany(Technology::class, 'package_technologies')
            ->using(PackageTechnologyPivot::class)
            ->withPivot('id', 'is_primary')
            ->withTimestamps();
    }

    /**
     * Soft relation — Technology row matching this package's language
     * slug, if present. Useful for Filament + API to surface the tech's
     * name/category.
     *
     * @return BelongsTo<Technology, $this>
     */
    public function languageTechnology(): BelongsTo
    {
        return $this->belongsTo(Technology::class, 'language', 'slug');
    }
}
