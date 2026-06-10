<?php

namespace App\Filament\Resources\Industries\Pages;

use App\Filament\Resources\Industries\IndustryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageIndustries extends ManageRecords
{
    protected static string $resource = IndustryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
