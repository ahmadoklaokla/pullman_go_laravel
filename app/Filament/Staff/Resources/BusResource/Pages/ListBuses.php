<?php

namespace App\Filament\Staff\Resources\BusResource\Pages;

use App\Filament\Staff\Resources\BusResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBuses extends ListRecords
{
    protected static string $resource = BusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
