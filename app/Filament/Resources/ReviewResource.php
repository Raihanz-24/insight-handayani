<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Place;
use App\Models\Review;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'Data';

    protected static ?string $navigationLabel = 'Review (detail)';

    protected static ?string $modelLabel = 'Review';

    protected static ?string $pluralModelLabel = 'Review (detail)';

    protected static ?int $navigationSort = 30;

    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('place.name')
                    ->label('Tempat')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('rating')
                    ->label('Bintang')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->color(fn (int $state): string => match (true) {
                        $state >= 4 => 'success',
                        $state === 3 => 'warning',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('review_date')
                    ->label('Tanggal review')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('author_name')
                    ->label('Pengulas')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('snippet')
                    ->label('Ulasan')
                    ->limit(60)
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('likes')
                    ->label('Suka')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('review_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('place_id')
                    ->label('Tempat')
                    ->options(fn () => Place::query()->pluck('name', 'id')->all()),
                Tables\Filters\SelectFilter::make('rating')
                    ->label('Bintang')
                    ->options([5 => '5 ★', 4 => '4 ★', 3 => '3 ★', 2 => '2 ★', 1 => '1 ★']),
                Tables\Filters\Filter::make('periode')
                    ->label('Rentang tanggal review')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Dari')->native(false),
                        Forms\Components\DatePicker::make('to')->label('Sampai')->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $d) => $q->whereDate('review_date', '>=', $d))
                            ->when($data['to'], fn ($q, $d) => $q->whereDate('review_date', '<=', $d));
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
        ];
    }
}
