<?php

namespace App\Models;

use Database\Factories\ProjectAssetFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAsset extends Model
{
    /** @use HasFactory<ProjectAssetFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'project_id', 'asset_type', 'path', 'og_path', 'caption', 'display_order',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
