<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Place;
use App\Services\Analytics\RatingAnalyticsService;
use Filament\Widgets\Widget;

/**
 * Ringkasan distribusi bintang bulan ini untuk semua tempat aktif.
 */
class RatingDistributionWidget extends Widget
{
    protected static string $view = 'filament.widgets.rating-distribution';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $from = now()->startOfMonth()->toDateString();
        $to = now()->endOfMonth()->toDateString();

        $service = app(RatingAnalyticsService::class);

        $rows = [];

        foreach (Place::query()->where('is_active', true)->orderBy('name')->get() as $place) {
            $report = $service->reportForPlace($place, $from, $to);
            $rows[] = ['place' => $place, 'report' => $report];
        }

        return [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
        ];
    }
}
