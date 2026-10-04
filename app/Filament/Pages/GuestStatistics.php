<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\GuestEntry;
use Carbon\CarbonImmutable;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

/**
 * Statistik kendaraan masuk (dari input mingguan).
 */
class GuestStatistics extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationGroup = 'Analitik';

    protected static ?string $navigationLabel = 'Statistik Kendaraan';

    protected static ?string $title = 'Statistik Kendaraan Masuk';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.guest-statistics';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'preset' => 'last30',
            'from' => now()->subDays(29)->toDateString(),
            'to' => now()->toDateString(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('preset')
                    ->label('Periode')
                    ->options([
                        'week' => 'Minggu ini',
                        'month' => 'Bulan ini',
                        'last30' => '30 hari terakhir',
                        'year' => 'Tahun ini',
                        'custom' => 'Custom (pilih tanggal)',
                    ])
                    ->default('last30')
                    ->live()
                    ->afterStateUpdated(function (Forms\Set $set, ?string $state): void {
                        [$from, $to] = $this->resolveRange($state);
                        $set('from', $from);
                        $set('to', $to);
                    }),
                Forms\Components\DatePicker::make('from')
                    ->label('Dari')
                    ->native(false)
                    ->live()
                    ->visible(fn (Forms\Get $get): bool => $get('preset') === 'custom'),
                Forms\Components\DatePicker::make('to')
                    ->label('Sampai')
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
            'week' => [$now->startOfWeek(CarbonImmutable::MONDAY)->toDateString(), $now->endOfWeek(CarbonImmutable::SUNDAY)->toDateString()],
            'month' => [$now->startOfMonth()->toDateString(), $now->endOfMonth()->toDateString()],
            'year' => [$now->startOfYear()->toDateString(), $now->endOfYear()->toDateString()],
            'custom' => [(string) ($this->data['from'] ?? $now->subDays(29)->toDateString()), (string) ($this->data['to'] ?? $now->toDateString())],
            default => [$now->subDays(29)->toDateString(), $now->toDateString()],
        };
    }

    private function range(): array
    {
        $preset = $this->data['preset'] ?? 'last30';

        if ($preset === 'custom') {
            return [
                (string) ($this->data['from'] ?? now()->subDays(29)->toDateString()),
                (string) ($this->data['to'] ?? now()->toDateString()),
            ];
        }

        return $this->resolveRange($preset);
    }

    public function getViewData(): array
    {
        [$from, $to] = $this->range();

        $entries = GuestEntry::query()
            ->betweenDates($from, $to)
            ->orderBy('week_start')
            ->get();

        $labels = $entries->map(fn (GuestEntry $e) => $e->week_start->translatedFormat('d M'))->all();
        $values = $entries->pluck('vehicles')->all();

        return [
            'from' => $from,
            'to' => $to,
            'labels' => $labels,
            'values' => $values,
            'total' => $entries->sum('vehicles'),
            'count' => $entries->count(),
            'average' => $entries->count() > 0 ? round($entries->avg('vehicles'), 1) : null,
            'max' => $entries->max('vehicles'),
        ];
    }
}
