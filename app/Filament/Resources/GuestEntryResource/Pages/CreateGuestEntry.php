<?php

declare(strict_types=1);

namespace App\Filament\Resources\GuestEntryResource\Pages;

use App\Filament\Resources\GuestEntryResource;
use App\Models\GuestEntry;
use Filament\Resources\Pages\CreateRecord;

class CreateGuestEntry extends CreateRecord
{
    protected static string $resource = GuestEntryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Normalisasi tanggal ke awal minggu (Senin) & catat penginput.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $start = GuestEntry::normalizeWeekStart($data['week_start']);

        $data['week_start'] = $start->toDateString();
        $data['week_end'] = $start->addDays(6)->toDateString();
        $data['entered_by'] = auth()->id();

        return $data;
    }
}
