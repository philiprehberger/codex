<?php

namespace App\Filament\Resources\DesignStyles\Pages;

use App\Filament\Resources\DesignStyles\DesignStyleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDesignStyles extends ManageRecords
{
    protected static string $resource = DesignStyleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
