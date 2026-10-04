<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\PlaceResource;
use App\Models\Place;
use App\Services\Analytics\RatingSyncService;
use App\Services\SerpApi\QuotaGuard;
use App\Services\SerpApi\SerpApiClient;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Halaman Pengaturan Analisis (khusus developer).
 *
 * Menampilkan status kuota SerpApi, status tiap tempat, dan kontrol cepat
 * (ubah mode analisis, ambil sekarang) agar mudah menghemat limit.
 */
class AnalysisSettings extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Developer';

    protected static ?string $navigationLabel = 'Pengaturan Analisis';

    protected static ?string $title = 'Pengaturan Analisis';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.analysis-settings';

    public static function canAccess(): bool
    {
        return auth()->user()?->isDeveloper() ?? false;
    }

    public function getViewData(): array
    {
        $quota = app(QuotaGuard::class);
        $client = app(SerpApiClient::class);

        return [
            'apiConfigured' => $client->isConfigured(),
            'quotaUsed' => $quota->usedToday(),
            'quotaLimit' => $quota->dailyLimit(),
            'quotaRemaining' => $quota->remaining(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Place::query())
            ->columns([
                TextColumn::make('name')->label('Tempat'),
                TextColumn::make('analysis_mode')
                    ->label('Mode')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Place::MODE_OFF => 'Nonaktif',
                        Place::MODE_SCHEDULED => 'Terjadwal',
                        default => 'Manual',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        Place::MODE_OFF => 'gray',
                        Place::MODE_SCHEDULED => 'success',
                        default => 'warning',
                    }),
                TextColumn::make('schedule_interval_days')
                    ->label('Interval')
                    ->formatStateUsing(fn ($state): string => $state ? $state.' hari' : '-'),
                TextColumn::make('reviews_synced')->label('Review')->numeric(),
                TextColumn::make('last_synced_at')->label('Terakhir')->dateTime('d M Y H:i'),
            ])
            ->actions([
                Tables\Actions\Action::make('off')
                    ->label('Nonaktifkan')
                    ->icon('heroicon-m-pause-circle')
                    ->color('gray')
                    ->visible(fn (Place $record): bool => $record->analysis_mode !== Place::MODE_OFF)
                    ->action(function (Place $record): void {
                        $record->update(['analysis_mode' => Place::MODE_OFF]);
                        Notification::make()->title("{$record->name} dinonaktifkan.")->success()->send();
                    }),
                Tables\Actions\Action::make('manual')
                    ->label('Manual')
                    ->icon('heroicon-m-hand-raised')
                    ->color('warning')
                    ->visible(fn (Place $record): bool => $record->analysis_mode !== Place::MODE_MANUAL)
                    ->action(function (Place $record): void {
                        $record->update(['analysis_mode' => Place::MODE_MANUAL]);
                        Notification::make()->title("{$record->name} diatur ke manual.")->success()->send();
                    }),
                Tables\Actions\Action::make('fetch')
                    ->label('Ambil Sekarang')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Memakai kuota SerpApi (ringkasan + beberapa halaman review).')
                    ->action(function (Place $record): void {
                        $snapshot = app(RatingSyncService::class)
                            ->syncPlace($record, force: true);

                        if ($snapshot->status === 'ok') {
                            Notification::make()
                                ->title("Berhasil: {$record->name}")
                                ->body("{$snapshot->rating}★ • {$snapshot->reviews_count} ulasan • {$record->fresh()->reviews_synced} review tersimpan.")
                                ->success()->send();
                        } else {
                            Notification::make()
                                ->title("Gagal: {$record->name}")
                                ->body((string) $snapshot->error_message)
                                ->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('edit')
                    ->label('Atur Jadwal')
                    ->icon('heroicon-m-pencil-square')
                    ->url(fn (Place $record): string => PlaceResource::getUrl('edit', ['record' => $record])),
            ])
            ->paginated(false);
    }
}
