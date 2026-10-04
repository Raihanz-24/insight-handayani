<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PlaceResource\Pages;
use App\Models\Place;
use App\Services\Analytics\RatingSyncService;
use App\Services\Maps\MapsLinkResolver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlaceResource extends Resource
{
    protected static ?string $model = Place::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Developer';

    protected static ?string $navigationLabel = 'Tempat & Analisis';

    protected static ?string $modelLabel = 'Tempat';

    protected static ?string $pluralModelLabel = 'Tempat & Analisis';

    protected static ?int $navigationSort = 10;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isDeveloper() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identitas Tempat')
                    ->description('Nama & jenis lokasi yang dianalisis.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama tempat')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('type')
                            ->label('Jenis')
                            ->options([
                                Place::TYPE_RESTAURANT => 'Restoran / Rumah Makan',
                                Place::TYPE_COTTAGE => 'Cottage / Penginapan',
                            ])
                            ->required()
                            ->default(Place::TYPE_RESTAURANT),
                    ])->columns(2),

                Forms\Components\Section::make('Link Google Maps')
                    ->description('Tempel link apa saja (short link maps.app.goo.gl, URL panjang, atau data_id). Klik "Ambil data_id" untuk mengisi otomatis.')
                    ->schema([
                        Forms\Components\TextInput::make('maps_url')
                            ->label('Link Google Maps')
                            ->maxLength(2048)
                            ->placeholder('https://maps.app.goo.gl/...')
                            ->live(onBlur: true)
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('resolveMaps')
                                    ->label('Ambil data_id')
                                    ->icon('heroicon-m-arrow-path')
                                    ->action(function (Forms\Set $set, Get $get): void {
                                        $input = (string) ($get('maps_url') ?: $get('serpapi_data_id') ?: '');

                                        if ($input === '') {
                                            return;
                                        }

                                        $resolved = app(MapsLinkResolver::class)->resolve($input);

                                        if (filled($resolved['data_id'])) {
                                            $set('serpapi_data_id', $resolved['data_id']);
                                        }
                                        if (filled($resolved['place_id'])) {
                                            $set('serpapi_place_id', $resolved['place_id']);
                                        }
                                        if ($resolved['latitude'] !== null) {
                                            $set('latitude', $resolved['latitude']);
                                        }
                                        if ($resolved['longitude'] !== null) {
                                            $set('longitude', $resolved['longitude']);
                                        }
                                    })
                                    ->successNotificationTitle('Data dari link diambil.'),
                            ),
                        Forms\Components\TextInput::make('serpapi_data_id')
                            ->label('data_id Google Maps')
                            ->maxLength(255)
                            ->helperText('Diisi otomatis dari link (format 0x...:0x...). Wajib untuk pengambilan otomatis.'),
                        Forms\Components\TextInput::make('serpapi_place_id')
                            ->label('place_id (opsional)')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('query')
                            ->label('Kata kunci pencarian (cadangan)')
                            ->maxLength(255),
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('latitude')
                                ->label('Latitude')
                                ->numeric(),
                            Forms\Components\TextInput::make('longitude')
                                ->label('Longitude')
                                ->numeric(),
                        ]),
                    ])->columns(2),

                Forms\Components\Section::make('Kontrol Analisis')
                    ->description('Atur apakah & seberapa sering rating direkap. Gunakan "Nonaktif" atau "Manual" untuk menghemat kuota SerpApi.')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Tempat aktif')
                            ->default(true)
                            ->helperText('Bila nonaktif, tempat tidak akan diproses sama sekali.'),
                        Forms\Components\Select::make('analysis_mode')
                            ->label('Mode analisis')
                            ->options(Place::analysisModes())
                            ->default(Place::MODE_MANUAL)
                            ->required()
                            ->live(),
                        Forms\Components\TextInput::make('schedule_interval_days')
                            ->label('Ambil tiap berapa hari')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->visible(fn (Get $get): bool => $get('analysis_mode') === Place::MODE_SCHEDULED)
                            ->required(fn (Get $get): bool => $get('analysis_mode') === Place::MODE_SCHEDULED)
                            ->helperText('1 = harian, 7 = mingguan, dst.'),
                        Forms\Components\Select::make('schedule_hour')
                            ->label('Pada jam (WIB)')
                            ->options(collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => str_pad((string) $h, 2, '0', STR_PAD_LEFT).':00'])->all())
                            ->default(2)
                            ->visible(fn (Get $get): bool => $get('analysis_mode') === Place::MODE_SCHEDULED),
                        Forms\Components\Placeholder::make('last_synced_info')
                            ->label('Terakhir diambil')
                            ->content(fn (?Place $record): string => $record?->last_synced_at
                                ? $record->last_synced_at->translatedFormat('d M Y H:i')
                                : 'Belum pernah'),
                        Forms\Components\Placeholder::make('reviews_info')
                            ->label('Review tersimpan')
                            ->content(fn (?Place $record): string => $record
                                ? sprintf(
                                    '%d review (%s s/d %s)',
                                    $record->reviews_synced,
                                    $record->oldest_review_date?->translatedFormat('d M Y') ?? '-',
                                    $record->newest_review_date?->translatedFormat('d M Y') ?? '-',
                                )
                                : '-'),
                    ])->columns(2),

                Forms\Components\Textarea::make('note')
                    ->label('Catatan')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Tempat')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Place $record): string => $record->type === Place::TYPE_COTTAGE ? 'Cottage' : 'Restoran'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                Tables\Columns\TextColumn::make('analysis_mode')
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
                Tables\Columns\TextColumn::make('schedule_interval_days')
                    ->label('Interval')
                    ->formatStateUsing(fn ($state): string => $state ? $state.' hari' : '-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('reviews_synced')
                    ->label('Review')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('newest_review_date')
                    ->label('Review terbaru')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label('Terakhir ambil')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('fetchNow')
                    ->label('Ambil Sekarang')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Ambil data sekarang?')
                    ->modalDescription('Ini memakai kuota SerpApi (1x ringkasan + beberapa halaman review).')
                    ->action(function (Place $record): void {
                        $snapshot = app(RatingSyncService::class)
                            ->syncPlace($record, force: true);

                        if ($snapshot->status === 'ok') {
                            Notification::make()
                                ->title("Berhasil: {$record->name}")
                                ->body("Rating {$snapshot->rating}★ • {$snapshot->reviews_count} ulasan • {$record->fresh()->reviews_synced} review tersimpan.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title("Gagal: {$record->name}")
                                ->body((string) $snapshot->error_message)
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlaces::route('/'),
            'create' => Pages\CreatePlace::route('/create'),
            'edit' => Pages\EditPlace::route('/{record}/edit'),
        ];
    }
}
