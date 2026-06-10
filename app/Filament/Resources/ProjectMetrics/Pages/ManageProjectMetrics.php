<?php

namespace App\Filament\Resources\ProjectMetrics\Pages;

use App\Filament\Resources\ProjectMetrics\ProjectMetricResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProjectMetrics extends ManageRecords
{
    protected static string $resource = ProjectMetricResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
