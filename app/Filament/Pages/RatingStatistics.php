<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Place;
use App\Services\Analytics\RatingAnalyticsService;
use Carbon\CarbonImmutable;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;

/**
 * Halaman Statistik Rating — distribusi bintang per rentang tanggal review.
 *
 * Bisa diakses SEMUA role (developer & user). Filter: hari ini / minggu ini /
 * bulan ini / 7 hari / 30 hari / custom.
 */
class RatingStatistics extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Analitik';

    protected static ?string $navigationLabel = 'Statistik Rating';

    protected static ?string $title = 'Statistik & Tren Rating Google Maps';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.rating-statistics';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** Granularitas rekap: 'week' (mingguan) atau 'month' (bulanan). */
    public string $recapGranularity = 'week';

    public function mount(): void
    {
        $this->form->fill([
            'preset' => 'month',
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('preset')
                    ->label('Periode')
                    ->options([
                        'today' => 'Hari ini',
                        'week' => 'Minggu ini',
                        'month' => 'Bulan ini',
                        'last7' => '7 hari terakhir',
                        'last30' => '30 hari terakhir',
                        'custom' => 'Custom (pilih tanggal)',
                    ])
                    ->default('month')
                    ->live()
                    ->afterStateUpdated(function (Forms\Set $set, ?string $state): void {
                        [$from, $to] = $this->resolveRange($state);
                        $set('from', $from);
                        $set('to', $to);
                    }),
                Forms\Components\DatePicker::make('from')
                    ->label('Dari tanggal (review)')
                    ->native(false)
                    ->live()
                    ->visible(fn (Forms\Get $get): bool => $get('preset') === 'custom'),
                Forms\Components\DatePicker::make('to')
                    ->label('Sampai tanggal (review)')
                    ->native(false)
                    ->live()
                    ->visible(fn (Forms\Get $get): bool => $get('preset') === 'custom'),
            ])
            ->columns(3)
            ->statePath('data');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveRange(?string $preset): array
    {
        $now = CarbonImmutable::now(config('app.timezone'));

        return match ($preset) {
            'today' => [$now->toDateString(), $now->toDateString()],
            'week' => [$now->startOfWeek(CarbonImmutable::MONDAY)->toDateString(), $now->endOfWeek(CarbonImmutable::SUNDAY)->toDateString()],
            'last7' => [$now->subDays(6)->toDateString(), $now->toDateString()],
            'last30' => [$now->subDays(29)->toDateString(), $now->toDateString()],
            'custom' => [(string) ($this->data['from'] ?? $now->startOfMonth()->toDateString()), (string) ($this->data['to'] ?? $now->toDateString())],
            default => [$now->startOfMonth()->toDateString(), $now->endOfMonth()->toDateString()],
        };
    }

    private function range(): array
    {
        $preset = $this->data['preset'] ?? 'month';

        if ($preset === 'custom') {
            return [
                (string) ($this->data['from'] ?? now()->startOfMonth()->toDateString()),
                (string) ($this->data['to'] ?? now()->toDateString()),
            ];
        }

        return $this->resolveRange($preset);
    }

    public function getViewData(): array
    {
        [$from, $to] = $this->range();

        $service = app(RatingAnalyticsService::class);
        $places = Place::query()->orderBy('name')->get();

        $reports = [];

        foreach ($places as $place) {
            $report = $service->reportForPlace($place, $from, $to);

            $reports[] = [
                'place' => $place,
                'report' => $report,
                // Tren akurat dari SNAPSHOT (rating & total ulasan per hari capture).
                'snapshotTrend' => $service->snapshotTrend($place, $from, $to),
                // Distribusi bintang per tanggal review (dari review tersimpan).
                'dailyStars' => $service->dailyStarDistribution($place, $from, $to),
                // Rekap mingguan/bulanan dari snapshot.
                'recap' => $service->snapshotRecap($place, $from, $to, $this->recapGranularity),
                'latestSnapshot' => $place->latestSnapshot(),
            ];
        }

        return [
            'from' => $from,
            'to' => $to,
            'reports' => $reports,
            'recapGranularity' => $this->recapGranularity,
        ];
    }

    public function setRecapGranularity(string $granularity): void
    {
        if (in_array($granularity, ['week', 'month'], true)) {
            $this->recapGranularity = $granularity;
        }
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }
}
