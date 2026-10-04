<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit.prevent="submit">
            {{ $this->form }}
        </form>
    </x-filament::section>

    <p class="text-sm text-gray-500 dark:text-gray-400">
        Periode: <strong>{{ \Carbon\Carbon::parse($from)->translatedFormat('d M Y') }}</strong>
        s/d <strong>{{ \Carbon\Carbon::parse($to)->translatedFormat('d M Y') }}</strong>.
        <span class="block mt-1">
            <strong>Tren rating &amp; total ulasan</strong> dihitung dari <em>snapshot harian</em> (akurat).
            <strong>Distribusi bintang</strong> dihitung dari <em>tanggal review</em> yang berhasil tersimpan.
        </span>
    </p>

    @forelse ($reports as $row)
        @php
            $place = $row['place'];
            $report = $row['report'];
            $dist = $report->distribution;
            $pct = $report->percentages();
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

            // Tren snapshot harian: rating & total ulasan.
            $snap = $row['snapshotTrend'];
            $snapLabels = array_map(fn ($r) => \Carbon\Carbon::parse($r['date'])->translatedFormat('d M'), $snap);
            $snapRating = array_map(fn ($r) => $r['rating'], $snap);
            $snapReviews = array_map(fn ($r) => $r['reviews'], $snap);

            $ratingTrendOptions = [
                'chart' => ['type' => 'line', 'height' => 260, 'toolbar' => ['show' => false]],
                'series' => [['name' => 'Rating rata-rata', 'data' => $snapRating]],
                'xaxis' => ['categories' => $snapLabels],
                'yaxis' => ['min' => 1, 'max' => 5, 'tickAmount' => 4],
                'stroke' => ['curve' => 'smooth', 'width' => 3],
                'markers' => ['size' => 4],
                'dataLabels' => ['enabled' => true],
                'colors' => ['#f59e0b'],
            ];

            $reviewsTrendOptions = [
                'chart' => ['type' => 'area', 'height' => 260, 'toolbar' => ['show' => false]],
                'series' => [['name' => 'Total ulasan', 'data' => $snapReviews]],
                'xaxis' => ['categories' => $snapLabels],
                'stroke' => ['curve' => 'smooth', 'width' => 2],
                'fill' => ['type' => 'gradient'],
                'dataLabels' => ['enabled' => false],
                'colors' => ['#3b82f6'],
            ];

            // Distribusi bintang per hari (stacked).
            $ds = $row['dailyStars'];
            $starsOptions = [
                'chart' => ['type' => 'bar', 'height' => 300, 'stacked' => true, 'toolbar' => ['show' => false]],
                'series' => $ds['series'],
                'xaxis' => ['categories' => array_map(fn ($d) => \Carbon\Carbon::parse($d)->translatedFormat('d M'), $ds['labels'])],
                'plotOptions' => ['bar' => ['columnWidth' => '55%', 'borderRadius' => 4]],
                'legend' => ['position' => 'bottom'],
                'colors' => ['#22c55e', '#84cc16', '#eab308', '#f97316', '#ef4444'],
                'dataLabels' => ['enabled' => false],
            ];
        @endphp

        <x-filament::section>
            <x-slot name="heading">{{ $place->name }}</x-slot>
            <x-slot name="description">
                @if ($row['latestSnapshot'])
                    Rating terakhir ({{ $row['latestSnapshot']->captured_at->translatedFormat('d M Y H:i') }}):
                    <strong>{{ $row['latestSnapshot']->rating }} ★</strong>
                    &middot; {{ number_format($row['latestSnapshot']->reviews_count) }} ulasan total
                @else
                    Belum ada snapshot. Tekan "Ambil Sekarang".
                @endif
            </x-slot>

            {{-- Kartu ringkasan --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                    <div class="text-xs text-gray-500 dark:text-gray-400">Rating rata-rata (periode)</div>
                    <div class="text-xl font-bold text-gray-900 dark:text-white">
                        {{ $report->average ? number_format($report->average, 2).' ★' : '-' }}
                    </div>
                </div>
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                    <div class="text-xs text-gray-500 dark:text-gray-400">Review tersimpan (periode)</div>
                    <div class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($report->total) }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                    <div class="text-xs text-gray-500 dark:text-gray-400">Total review tersimpan</div>
                    <div class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($place->reviews_synced) }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                    <div class="text-xs text-gray-500 dark:text-gray-400">Hari snapshot</div>
                    <div class="text-xl font-bold text-gray-900 dark:text-white">{{ count($snap) }}</div>
                </div>
            </div>

            {{-- Distribusi bintang --}}
            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div>
                    <h4 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Distribusi Bintang (dari review tersimpan pada periode)
                    </h4>

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

                    @if ($report->total === 0)
                        <p class="mt-3 text-sm text-gray-400">
                            Belum ada review tersimpan pada periode ini.
                        </p>
                    @endif
                </div>

                <div>
                    <h4 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Proporsi</h4>

                    @if ($report->total > 0)
                        <div
                            x-ignore
                            x-load
                            x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('apexcharts') }}"
                            x-data="apexcharts({
                                options: @js($pieOptions),
                                chartId: '#pie-{{ $place->id }}',
                                theme: document.querySelector('html').matches('.dark') ? 'dark' : 'light',
                                extraJsOptions: {},
                            })"
                            wire:key="pie-{{ $place->id }}-{{ $from }}-{{ $to }}"
                        >
                            <div wire:ignore class="w-full">
                                <div id="pie-{{ $place->id }}"></div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Tren harian dari snapshot --}}
            <div class="mt-6">
                <h4 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                    Tren Harian — Rating Rata-rata &amp; Total Ulasan <span class="font-normal text-gray-400">(dari snapshot harian)</span>
                </h4>

                @if (count($snap) === 0)
                    <p class="text-sm text-gray-400">
                        Belum ada snapshot pada periode ini. Snapshot terbentuk otomatis setiap hari (jam 00:00)
                        atau saat menekan "Ambil Sekarang".
                    </p>
                @else
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <div
                            x-ignore x-load
                            x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('apexcharts') }}"
                            x-data="apexcharts({
                                options: @js($ratingTrendOptions),
                                chartId: '#rating-{{ $place->id }}',
                                theme: document.querySelector('html').matches('.dark') ? 'dark' : 'light',
                                extraJsOptions: {},
                            })"
                            wire:key="rating-{{ $place->id }}-{{ $from }}-{{ $to }}"
                        >
                            <div wire:ignore class="w-full"><div id="rating-{{ $place->id }}"></div></div>
                        </div>

                        <div
                            x-ignore x-load
                            x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('apexcharts') }}"
                            x-data="apexcharts({
                                options: @js($reviewsTrendOptions),
                                chartId: '#reviews-{{ $place->id }}',
                                theme: document.querySelector('html').matches('.dark') ? 'dark' : 'light',
                                extraJsOptions: {},
                            })"
                            wire:key="reviews-{{ $place->id }}-{{ $from }}-{{ $to }}"
                        >
                            <div wire:ignore class="w-full"><div id="reviews-{{ $place->id }}"></div></div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Jumlah orang per bintang per hari (stacked) --}}
            @if (count($ds['labels']) > 0)
                <div class="mt-6">
                    <h4 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Jumlah Orang per Bintang per Hari <span class="font-normal text-gray-400">(dari tanggal review tersimpan)</span>
                    </h4>
                    <div
                        x-ignore x-load
                        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('apexcharts') }}"
                        x-data="apexcharts({
                            options: @js($starsOptions),
                            chartId: '#stars-{{ $place->id }}',
                            theme: document.querySelector('html').matches('.dark') ? 'dark' : 'light',
                            extraJsOptions: {},
                        })"
                        wire:key="stars-{{ $place->id }}-{{ $from }}-{{ $to }}"
                    >
                        <div wire:ignore class="w-full"><div id="stars-{{ $place->id }}"></div></div>
                    </div>
                </div>
            @endif

            {{-- Review BARU per hari (akurat: dari review baru yang tersimpan + validasi Google) --}}
            <div class="mt-6">
                <h4 class="mb-1 text-sm font-semibold text-gray-700 dark:text-gray-300">
                    Review Baru per Hari — Berapa Orang per Bintang
                </h4>
                <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                    Dihitung dari review <strong>baru yang tersimpan pada hari itu</strong>.
                    Kolom "menurut Google" = selisih total ulasan (akurat) sebagai pembanding.
                </p>

                @php
                    $nr = $row['newReviews'];
                    $nrs = $row['newReviewsStars'];

                    $newStarsOptions = [
                        'chart' => ['type' => 'bar', 'height' => 300, 'stacked' => true, 'toolbar' => ['show' => false]],
                        'series' => $nrs['series'],
                        'xaxis' => ['categories' => array_map(fn ($d) => \Carbon\Carbon::parse($d)->translatedFormat('d M'), $nrs['labels'])],
                        'plotOptions' => ['bar' => ['columnWidth' => '55%', 'borderRadius' => 4]],
                        'legend' => ['position' => 'bottom'],
                        'colors' => ['#22c55e', '#84cc16', '#eab308', '#f97316', '#ef4444'],
                        'dataLabels' => ['enabled' => false],
                    ];
                @endphp

                @if (count($nr) === 0)
                    <p class="text-sm text-gray-400">
                        Belum ada data review harian. Data ini terbentuk otomatis setiap kali sync (1×/hari).
                    </p>
                @else
                    <div
                        x-ignore x-load
                        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('apexcharts') }}"
                        x-data="apexcharts({
                            options: @js($newStarsOptions),
                            chartId: '#newstars-{{ $place->id }}',
                            theme: document.querySelector('html').matches('.dark') ? 'dark' : 'light',
                            extraJsOptions: {},
                        })"
                        wire:key="newstars-{{ $place->id }}-{{ $from }}-{{ $to }}"
                    >
                        <div wire:ignore class="w-full"><div id="newstars-{{ $place->id }}"></div></div>
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500 dark:text-gray-400">
                                    <th class="py-2">Tanggal</th>
                                    <th class="py-2 text-right">★5</th>
                                    <th class="py-2 text-right">★4</th>
                                    <th class="py-2 text-right">★3</th>
                                    <th class="py-2 text-right">★2</th>
                                    <th class="py-2 text-right">★1</th>
                                    <th class="py-2 text-right">Review baru (tersimpan)</th>
                                    <th class="py-2 text-right">Menurut Google</th>
                                    <th class="py-2 text-right">Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($nr as $r)
                                    <tr class="border-t border-gray-100 dark:border-gray-800">
                                        <td class="py-2">{{ \Carbon\Carbon::parse($r['date'])->translatedFormat('d M Y') }}</td>
                                        <td class="py-2 text-right">{{ $r['distribution'][5] }}</td>
                                        <td class="py-2 text-right">{{ $r['distribution'][4] }}</td>
                                        <td class="py-2 text-right">{{ $r['distribution'][3] }}</td>
                                        <td class="py-2 text-right">{{ $r['distribution'][2] }}</td>
                                        <td class="py-2 text-right">{{ $r['distribution'][1] }}</td>
                                        <td class="py-2 text-right font-medium">{{ $r['new_reviews'] }}</td>
                                        <td class="py-2 text-right {{ $r['reviews_delta'] !== null ? 'text-success-600' : 'text-gray-400' }}">
                                            {{ $r['reviews_delta'] !== null ? ($r['reviews_delta'] >= 0 ? '+'.number_format($r['reviews_delta']) : number_format($r['reviews_delta'])) : '—' }}
                                        </td>
                                        <td class="py-2 text-right">{{ $r['average_rating'] ? number_format($r['average_rating'], 2).' ★' : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p class="mt-2 text-xs text-gray-400">
                            Catatan: "review baru (tersimpan)" adalah yang berhasil kita ambil;
                            "menurut Google" (selisih total ulasan) adalah angka sebenarnya — selisihnya
                            berarti review yang belum kita ambil.
                        </p>
                    </div>
                @endif
            </div>

            {{-- Rekap mingguan/bulanan --}}
            <div class="mt-6">
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Rekap Laporan</h4>
                    <div class="flex gap-2">
                        <button type="button"
                                wire:click="setRecapGranularity('week')"
                                class="rounded px-3 py-1 text-xs font-medium {{ $recapGranularity === 'week' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' }}">
                            Mingguan
                        </button>
                        <button type="button"
                                wire:click="setRecapGranularity('month')"
                                class="rounded px-3 py-1 text-xs font-medium {{ $recapGranularity === 'month' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' }}">
                            Bulanan
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="py-2">{{ $recapGranularity === 'week' ? 'Minggu' : 'Bulan' }}</th>
                                <th class="py-2 text-right">Rating rata-rata</th>
                                <th class="py-2 text-right">Total ulasan (akhir)</th>
                                <th class="py-2 text-right">Pertambahan ulasan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($row['recap'] as $r)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="py-2">{{ $r['label'] }}</td>
                                    <td class="py-2 text-right font-medium">{{ $r['rating'] ? number_format($r['rating'], 2).' ★' : '-' }}</td>
                                    <td class="py-2 text-right">{{ $r['end_reviews'] !== null ? number_format($r['end_reviews']) : '-' }}</td>
                                    <td class="py-2 text-right {{ $r['delta'] > 0 ? 'text-success-600' : 'text-gray-400' }}">
                                        {{ $r['delta'] > 0 ? '+'.number_format($r['delta']) : ($r['delta'] < 0 ? number_format($r['delta']) : '—') }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="py-2 text-gray-400" colspan="4">Belum ada data snapshot untuk direkap.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </x-filament::section>
    @empty
        <x-filament::section>
            <p class="text-sm text-gray-500">Belum ada tempat yang dikonfigurasi. Tambahkan dari menu "Tempat & Analisis".</p>
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
