<?php

namespace App\Models\Pivots;

class ProjectTechnologyPivot extends CodexPivot
{
    protected $table = 'project_technologies';

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
