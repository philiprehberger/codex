<?php

namespace App\Filament\Resources\ProjectAssets\Pages;

use App\Filament\Resources\ProjectAssets\ProjectAssetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProjectAssets extends ManageRecords
{
    protected static string $resource = ProjectAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
