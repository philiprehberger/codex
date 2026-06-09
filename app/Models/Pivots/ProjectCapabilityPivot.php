<?php

namespace App\Models\Pivots;

class ProjectCapabilityPivot extends CodexPivot
{
    protected $table = 'project_capabilities';

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
