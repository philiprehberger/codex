<?php

namespace App\Filament\Resources\ProjectLearnings\Pages;

use App\Filament\Resources\ProjectLearnings\ProjectLearningResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProjectLearnings extends ManageRecords
{
    protected static string $resource = ProjectLearningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
