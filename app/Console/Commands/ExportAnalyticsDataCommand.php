<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Ekspor data analitik (bukan kode) agar bisa dipindah ke server saat deploy.
 *
 * Menghasilkan 1 file JSON berisi tabel: places (opsional), reviews,
 * rating_snapshots, daily_review_stats. Riwayat data ini TIDAK ikut Git,
 * jadi perlu dipindah manual.
 *
 * Pakai:
 *   php artisan analytics:export-data
 *   php artisan analytics:export-data --no-places     (jangan sertakan places)
 *   php artisan analytics:export-data --path=/tmp/analytics.json
 */
class ExportAnalyticsDataCommand extends Command
{
    protected $signature = 'analytics:export-data
                            {--path= : Lokasi file output (default storage/app/analytics-export-<tanggal>.json)}
                            {--no-places : Jangan sertakan tabel places}
                            {--no-reviews : Jangan sertakan tabel reviews (hanya ringkasan)}';

    protected $description = 'Ekspor data analitik (places, reviews, snapshot, statistik harian) ke JSON untuk dipindah ke server.';

    public function handle(): int
    {
        $path = (string) ($this->option('path') ?: storage_path('app/analytics-export-'.now()->format('Ymd-His').'.json'));

        $payload = [
            'meta' => [
                'app' => config('app.name'),
                'exported_at' => now()->toIso8601String(),
                'timezone' => config('app.timezone'),
                'version' => 1,
            ],
            'tables' => [],
        ];

        $tables = ['reviews', 'rating_snapshots', 'daily_review_stats'];

        if (! $this->option('no-places')) {
            array_unshift($tables, 'places');
        }

        if ($this->option('no-reviews')) {
            $tables = array_values(array_diff($tables, ['reviews']));
        }

        foreach ($tables as $table) {
            $rows = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
            $payload['tables'][$table] = $rows;
            $this->line(sprintf('  %-22s %d baris', $table.':', count($rows)));
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $size = round(File::size($path) / 1024, 1);

        $this->info("Ekspor selesai: {$path} ({$size} KB)");
        $this->line('Kirim file ini ke server lalu jalankan: php artisan analytics:import-data --path=...');

        return self::SUCCESS;
    }
}
