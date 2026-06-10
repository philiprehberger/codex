<?php

namespace App\Filament\Resources\Architectures\Pages;

use App\Filament\Resources\Architectures\ArchitectureResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageArchitectures extends ManageRecords
{
    protected static string $resource = ArchitectureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
