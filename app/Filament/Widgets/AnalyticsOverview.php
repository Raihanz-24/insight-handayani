<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\GuestEntry;
use App\Models\Place;
use App\Models\Review;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AnalyticsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $reviewsThisMonth = Review::query()
            ->whereBetween('review_date', [$monthStart, $monthEnd])
            ->count();

        $vehiclesThisMonth = (int) GuestEntry::query()
            ->betweenDates($monthStart, $monthEnd)
            ->sum('vehicles');

        $placesActive = Place::query()->where('is_active', true)->count();
        $reviewsStored = (int) Place::query()->sum('reviews_synced');

        return [
            Stat::make('Review bulan ini', number_format($reviewsThisMonth))
                ->description('Dari tanggal review '.now()->translatedFormat('F Y'))
                ->color('primary'),
            Stat::make('Kendaraan bulan ini', number_format($vehiclesThisMonth))
                ->description('Total input mingguan')
                ->color('success'),
            Stat::make('Review tersimpan', number_format($reviewsStored))
                ->description($placesActive.' tempat aktif')
                ->color('warning'),
        ];
    }
}
