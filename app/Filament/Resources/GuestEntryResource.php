<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\GuestEntryResource\Pages;
use App\Models\GuestEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GuestEntryResource extends Resource
{
    protected static ?string $model = GuestEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Data';

    protected static ?string $navigationLabel = 'Kendaraan Masuk';

    protected static ?string $modelLabel = 'Data Kendaraan';

    protected static ?string $pluralModelLabel = 'Kendaraan Masuk';

    protected static ?int $navigationSort = 20;

    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('week_start')
                    ->label('Pilih minggu (tanggal mana saja)')
                    ->required()
                    ->native(false)
                    ->displayFormat('d M Y')
                    ->helperText('Sistem otomatis menormalkan ke hari Senin sebagai awal minggu.')
                    ->formatStateUsing(fn (?GuestEntry $record) => $record?->week_start)
                    ->afterStateHydrated(function (Forms\Components\DatePicker $component, ?GuestEntry $record): void {
                        if ($record !== null) {
                            $component->state($record->week_start?->toDateString());
                        }
                    })
                    ->dehydrated(false)
                    ->live(onBlur: true),
                Forms\Components\TextInput::make('vehicles')
                    ->label('Jumlah kendaraan masuk')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                Forms\Components\Placeholder::make('week_range')
                    ->label('Rentang minggu')
                    ->content(function (Forms\Get $get): string {
                        $date = $get('week_start');

                        if (! $date) {
                            return 'Pilih tanggal dulu.';
                        }

                        $start = GuestEntry::normalizeWeekStart($date);

                        return $start->translatedFormat('d M Y').' s/d '.$start->addDays(6)->translatedFormat('d M Y');
                    }),
                Forms\Components\Textarea::make('note')
                    ->label('Catatan')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('week_start')
                    ->label('Minggu')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('week_end')
                    ->label('s/d')
                    ->date('d M Y')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('vehicles')
                    ->label('Kendaraan')
                    ->numeric()
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Total')),
                Tables\Columns\TextColumn::make('enteredBy.name')
                    ->label('Diinput oleh')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('note')
                    ->label('Catatan')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('week_start', 'desc')
            ->filters([
                Tables\Filters\Filter::make('periode')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Dari')->native(false),
                        Forms\Components\DatePicker::make('to')->label('Sampai')->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $d) => $q->whereDate('week_start', '>=', $d))
                            ->when($data['to'], fn ($q, $d) => $q->whereDate('week_start', '<=', $d));
                    }),
                Tables\Filters\Filter::make('bulan_ini')
                    ->label('Bulan ini')
                    ->query(fn ($query) => $query->whereBetween('week_start', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => auth()->user()?->isDeveloper() ?? false),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => auth()->user()?->isDeveloper() ?? false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->isDeveloper() ?? false),
                ])->visible(fn () => auth()->user()?->isDeveloper() ?? false),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGuestEntries::route('/'),
            'create' => Pages\CreateGuestEntry::route('/create'),
            'edit' => Pages\EditGuestEntry::route('/{record}/edit'),
        ];
    }
}
