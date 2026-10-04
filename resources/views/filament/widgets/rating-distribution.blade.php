<x-filament-widgets::widget>
    <x-filament::section
        heading="Distribusi Bintang Bulan Ini"
        :description="'Periode '.$from.' s/d '.$to.' • dihitung dari tanggal review'"
    >
        <div class="space-y-6">
            @forelse ($rows as $row)
                @php
                    $dist = $row['report']->distribution;
                    $pct = $row['report']->percentages();
                @endphp

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $row['place']->name }}</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $row['report']->total }} review
                            @if ($row['report']->average) &middot; {{ number_format($row['report']->average, 2) }} ★ @endif
                        </span>
                    </div>

                    @foreach ([5, 4, 3, 2, 1] as $star)
                        <div class="flex items-center gap-3 py-0.5">
                            <span class="w-10 shrink-0 text-xs text-gray-500">{{ $star }} ★</span>
                            <div class="h-3 flex-1 overflow-hidden rounded bg-gray-100 dark:bg-gray-800">
                                <div class="h-full rounded bg-primary-500" style="width: {{ $pct[$star] }}%"></div>
                            </div>
                            <span class="w-24 shrink-0 text-right text-xs text-gray-500">
                                {{ $dist[$star] }} org ({{ $pct[$star] }}%)
                            </span>
                        </div>
                    @endforeach
                </div>
            @empty
                <p class="text-sm text-gray-500">Belum ada tempat aktif.</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
