<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit.prevent="submit">
            {{ $this->form }}
        </form>
    </x-filament::section>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Total kendaraan</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($total) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Rata-rata / minggu</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $average ? number_format($average, 1) : '-' }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Minggu tercatat</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $count }}</div>
        </x-filament::section>
    </div>

    <x-filament::section
        heading="Grafik Kendaraan Masuk"
        :description="'Periode '.$from.' s/d '.$to"
    >
        @if ($count === 0)
            <p class="text-sm text-gray-500">
                Belum ada data kendaraan pada periode ini. Input lewat menu "Kendaraan Masuk".
            </p>
        @else
            @php
                $options = [
                    'chart' => ['type' => 'bar', 'height' => 320, 'toolbar' => ['show' => false]],
                    'series' => [['name' => 'Kendaraan', 'data' => $values]],
                    'xaxis' => ['categories' => $labels],
                    'plotOptions' => ['bar' => ['columnWidth' => '55%', 'borderRadius' => 6]],
                    'dataLabels' => ['enabled' => false],
                    'colors' => ['#3b82f6'],
                ];
            @endphp

            <div
                x-ignore
                x-load
                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('apexcharts') }}"
                x-data="apexcharts({
                    options: @js($options),
                    chartId: '#guest-chart',
                    theme: document.querySelector('html').matches('.dark') ? 'dark' : 'light',
                    extraJsOptions: {},
                })"
                wire:key="guest-chart-{{ $from }}-{{ $to }}"
            >
                <div wire:ignore class="w-full">
                    <div id="guest-chart"></div>
                </div>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="Rincian per Minggu">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        <th class="py-2">Minggu</th>
                        <th class="py-2 text-right">Kendaraan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (\App\Models\GuestEntry::query()->betweenDates($from, $to)->orderBy('week_start')->get() as $entry)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="py-2">
                                {{ $entry->week_start->translatedFormat('d M Y') }}
                                – {{ $entry->week_end->translatedFormat('d M Y') }}
                            </td>
                            <td class="py-2 text-right font-medium">{{ number_format($entry->vehicles) }}</td>
                        </tr>
                    @empty
                        <tr><td class="py-2 text-gray-400" colspan="2">Tidak ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
