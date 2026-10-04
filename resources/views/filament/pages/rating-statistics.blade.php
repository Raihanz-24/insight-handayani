<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit.prevent="submit">
            {{ $this->form }}
        </form>
    </x-filament::section>

    <p class="text-sm text-gray-500 dark:text-gray-400">
        Periode review: <strong>{{ \Carbon\Carbon::parse($from)->translatedFormat('d M Y') }}</strong>
        s/d <strong>{{ \Carbon\Carbon::parse($to)->translatedFormat('d M Y') }}</strong>.
        Statistik dihitung dari <em>tanggal review</em> yang tersimpan di sistem.
    </p>

    @forelse ($reports as $row)
        @php
            $dist = $row['report']->distribution;
            $pct = $row['report']->percentages();
            $labels = ['5 ★', '4 ★', '3 ★', '2 ★', '1 ★'];
            $series = [$dist[5], $dist[4], $dist[3], $dist[2], $dist[1]];

            $pieOptions = [
                'chart' => ['type' => 'donut', 'height' => 260],
                'series' => $series,
                'labels' => $labels,
                'legend' => ['position' => 'bottom'],
                'colors' => ['#22c55e', '#84cc16', '#eab308', '#f97316', '#ef4444'],
                'plotOptions' => ['pie' => ['donut' => ['size' => '62%']]],
            ];

            $trendOptions = [
                'chart' => ['type' => 'area', 'height' => 240, 'toolbar' => ['show' => false]],
                'series' => [['name' => 'Jumlah review', 'data' => array_values($row['trend'])]],
                'xaxis' => ['categories' => array_keys($row['trend'])],
                'stroke' => ['curve' => 'smooth', 'width' => 2],
                'fill' => ['type' => 'gradient'],
                'dataLabels' => ['enabled' => false],
                'colors' => ['#3b82f6'],
            ];
        @endphp

        <x-filament::section
            :heading="$row['place']->name"
            :description="'Total '.$row['report']->total.' review'.($row['report']->average ? ' • rata-rata '.number_format($row['report']->average, 2).' ★' : '')"
        >
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {{-- Distribusi bintang (bar) --}}
                <div>
                    <h4 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Distribusi Bintang</h4>

                    <div class="space-y-2">
                        @foreach ([5, 4, 3, 2, 1] as $star)
                            <div class="flex items-center gap-3">
                                <span class="w-14 shrink-0 text-sm text-gray-600 dark:text-gray-300">{{ $star }} ★</span>
                                <div class="h-4 flex-1 overflow-hidden rounded bg-gray-100 dark:bg-gray-800">
                                    <div class="h-full rounded bg-primary-500 transition-all"
                                         style="width: {{ $pct[$star] }}%"></div>
                                </div>
                                <span class="w-24 shrink-0 text-right text-sm text-gray-600 dark:text-gray-300">
                                    {{ $dist[$star] }} org ({{ $pct[$star] }}%)
                                </span>
                            </div>
                        @endforeach
                    </div>

                    @if ($row['report']->total === 0)
                        <p class="mt-3 text-sm text-gray-400">
                            Belum ada review tersimpan pada periode ini. Jalankan "Ambil Sekarang" pada tempat ini.
                        </p>
                    @endif
                </div>

                {{-- Pie distribusi --}}
                <div>
                    <h4 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Proporsi</h4>

                    @if ($row['report']->total > 0)
                        <div
                            x-ignore
                            x-load
                            x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('apexcharts') }}"
                            x-data="apexcharts({
                                options: @js($pieOptions),
                                chartId: '#pie-{{ $row['place']->id }}',
                                theme: document.querySelector('html').matches('.dark') ? 'dark' : 'light',
                                extraJsOptions: {},
                            })"
                            wire:key="pie-{{ $row['place']->id }}-{{ $from }}-{{ $to }}"
                        >
                            <div wire:ignore class="w-full">
                                <div id="pie-{{ $row['place']->id }}"></div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Tren harian --}}
            @if (! empty($row['trend']))
                <div class="mt-6">
                    <h4 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Tren Jumlah Review</h4>
                    <div
                        x-ignore
                        x-load
                        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('apexcharts') }}"
                        x-data="apexcharts({
                            options: @js($trendOptions),
                            chartId: '#area-{{ $row['place']->id }}',
                            theme: document.querySelector('html').matches('.dark') ? 'dark' : 'light',
                            extraJsOptions: {},
                        })"
                        wire:key="area-{{ $row['place']->id }}-{{ $from }}-{{ $to }}"
                    >
                        <div wire:ignore class="w-full">
                            <div id="area-{{ $row['place']->id }}"></div>
                        </div>
                    </div>
                </div>
            @endif
        </x-filament::section>
    @empty
        <x-filament::section>
            <p class="text-sm text-gray-500">Belum ada tempat yang dikonfigurasi. Tambahkan dari menu "Tempat & Analisis".</p>
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
