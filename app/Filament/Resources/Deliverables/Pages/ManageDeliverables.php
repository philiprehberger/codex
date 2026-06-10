<?php

namespace App\Filament\Resources\Deliverables\Pages;

use App\Filament\Resources\Deliverables\DeliverableResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDeliverables extends ManageRecords
{
    protected static string $resource = DeliverableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
