<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Status API SerpApi</div>
            <div class="text-lg font-bold {{ $apiConfigured ? 'text-success-600' : 'text-danger-600' }}">
                {{ $apiConfigured ? 'Terhubung (key terisi)' : 'Belum ada API key' }}
            </div>
            <p class="mt-1 text-xs text-gray-500">
                Isi <code>SERPAPI_KEY</code> pada <code>.env</code> untuk mengaktifkan pengambilan data.
            </p>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Kuota hari ini</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ $quotaUsed }} / {{ $quotaLimit }}
            </div>
            <p class="mt-1 text-xs text-gray-500">Sisa {{ $quotaRemaining }} search.</p>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Tips hemat kuota</div>
            <ul class="mt-1 list-disc pl-4 text-xs text-gray-500 space-y-1">
                <li>Set mode <strong>Nonaktif</strong> bila tidak perlu.</li>
                <li><strong>Manual</strong>: hanya ambil saat tombol ditekan.</li>
                <li><strong>Terjadwal</strong>: interval bisa 2, 7 hari, dst.</li>
            </ul>
        </x-filament::section>
    </div>

    <x-filament::section
        heading="Kontrol Analisis per Tempat"
        description="Ubah mode atau ambil data sekarang tanpa masuk ke form edit."
    >
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
