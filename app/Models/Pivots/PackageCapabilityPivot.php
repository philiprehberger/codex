<?php

namespace App\Models\Pivots;

class PackageCapabilityPivot extends CodexPivot
{
    protected $table = 'package_capabilities';

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
