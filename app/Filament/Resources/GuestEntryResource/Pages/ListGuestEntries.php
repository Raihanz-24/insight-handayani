<?php

namespace App\Filament\Resources\GuestEntryResource\Pages;

use App\Filament\Resources\GuestEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGuestEntries extends ListRecords
{
    protected static string $resource = GuestEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
