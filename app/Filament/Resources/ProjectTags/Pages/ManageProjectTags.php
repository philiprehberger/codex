<?php

namespace App\Filament\Resources\ProjectTags\Pages;

use App\Filament\Resources\ProjectTags\ProjectTagResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProjectTags extends ManageRecords
{
    protected static string $resource = ProjectTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
