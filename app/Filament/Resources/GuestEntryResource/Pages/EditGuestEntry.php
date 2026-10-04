<?php

declare(strict_types=1);

namespace App\Filament\Resources\GuestEntryResource\Pages;

use App\Filament\Resources\GuestEntryResource;
use App\Models\GuestEntry;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGuestEntry extends EditRecord
{
    protected static string $resource = GuestEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $start = GuestEntry::normalizeWeekStart($data['week_start']);

        $data['week_start'] = $start->toDateString();
        $data['week_end'] = $start->addDays(6)->toDateString();

        return $data;
    }
}
