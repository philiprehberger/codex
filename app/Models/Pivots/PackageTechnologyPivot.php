<?php

namespace App\Models\Pivots;

class PackageTechnologyPivot extends CodexPivot
{
    protected $table = 'package_technologies';

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
