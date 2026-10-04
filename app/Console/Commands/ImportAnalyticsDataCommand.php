<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DailyReviewStat;
use App\Models\Place;
use App\Models\RatingSnapshot;
use App\Models\Review;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Impor data analitik hasil `analytics:export-data` ke server.
 *
 * Idempoten: aman dijalankan berulang.
 *  - places : dicocokkan berdasarkan serpapi_data_id, jika tidak ada → name.
 *  - reviews / rating_snapshots / daily_review_stats : memakai updateOrCreate
 *    dengan kunci unik masing-masing, dan place_id DIPETAKAN dari file ke DB
 *    (agar ID lokal ≠ ID server tetap benar).
 *
 * Pakai:
 *   php artisan analytics:import-data --path=/path/analytics-export.json
 */
class ImportAnalyticsDataCommand extends Command
{
    protected $signature = 'analytics:import-data
                            {--path= : File JSON hasil ekspor (wajib)}
                            {--skip-places : Jangan impor tabel places (pakai place yang sudah ada)}
                            {--truncate : Kosongkan dulu tabel tujuan (HATI-HATI)}';

    protected $description = 'Impor data analitik dari JSON (hasil analytics:export-data) ke database ini.';

    public function handle(): int
    {
        $path = (string) $this->option('path');

        if ($path === '' || ! File::exists($path)) {
            $this->error('File tidak ditemukan. Gunakan --path=...');

            return self::FAILURE;
        }

        $payload = json_decode((string) File::get($path), true);

        if (! is_array($payload) || ! isset($payload['tables'])) {
            $this->error('Format file tidak valid.');

            return self::FAILURE;
        }

        $tables = $payload['tables'];

        if ($this->option('truncate')) {
            if (! $this->confirm('Kosongkan tabel reviews/rating_snapshots/daily_review_stats terlebih dahulu?', false)) {
                return self::FAILURE;
            }

            DB::table('reviews')->delete();
            DB::table('rating_snapshots')->delete();
            DB::table('daily_review_stats')->delete();
            $this->warn('Tabel dikosongkan.');
        }

        // 1. Places (opsional) + peta ID lokal → ID server.
        $idMap = $this->importPlaces($tables['places'] ?? []);

        // 2. reviews
        $reviewCount = $this->importReviews($tables['reviews'] ?? [], $idMap);

        // 3. rating_snapshots
        $snapCount = $this->importSnapshots($tables['rating_snapshots'] ?? [], $idMap);

        // 4. daily_review_stats
        $statCount = $this->importDailyStats($tables['daily_review_stats'] ?? [], $idMap);

        // 5. Rapikan statistik tempat (reviews_synced, tanggal).
        $this->refreshPlaces();

        $this->info("Impor selesai: {$reviewCount} review, {$snapCount} snapshot, {$statCount} statistik harian.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, int> peta place_id lama → baru
     */
    private function importPlaces(array $rows): array
    {
        $map = [];

        if ($this->option('skip-places') || $rows === []) {
            // Tetap bangun peta dari data yang ada bila skip-places.
            if ($this->option('skip-places')) {
                $this->line('  places: dilewati (--skip-places).');
            }

            return $map;
        }

        $count = 0;

        foreach ($rows as $row) {
            $match = null;

            if (filled($row['serpapi_data_id'] ?? null)) {
                $match = Place::query()->where('serpapi_data_id', $row['serpapi_data_id'])->first();
            }
            $match ??= Place::query()->where('name', $row['name'])->first();

            $attributes = collect($row)
                ->except(['id', 'created_at', 'updated_at'])
                ->all();

            if ($match !== null) {
                $match->update($attributes);
                $map[(int) $row['id']] = (int) $match->id;
            } else {
                $created = Place::query()->create($attributes);
                $map[(int) $row['id']] = (int) $created->id;
                $count++;
            }
        }

        $this->line('  places: '.count($rows).' diproses ('.$count.' baru).');

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, int>  $idMap
     */
    private function importReviews(array $rows, array $idMap): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $placeId = $this->mapPlaceId($row['place_id'] ?? null, $idMap);

            if ($placeId === null) {
                continue;
            }

            $attributes = collect($row)->except(['id', 'created_at', 'updated_at'])->all();
            $attributes['place_id'] = $placeId;

            Review::query()->updateOrCreate(
                ['place_id' => $placeId, 'review_key' => $row['review_key']],
                $attributes,
            );
            $count++;
        }

        $this->line("  reviews: {$count} diproses.");

        return $count;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, int>  $idMap
     */
    private function importSnapshots(array $rows, array $idMap): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $placeId = $this->mapPlaceId($row['place_id'] ?? null, $idMap);

            if ($placeId === null) {
                continue;
            }

            $attributes = collect($row)->except(['id', 'created_at', 'updated_at'])->all();
            $attributes['place_id'] = $placeId;

            RatingSnapshot::query()->updateOrCreate(
                [
                    'place_id' => $placeId,
                    'captured_date' => $row['captured_date'],
                    'source' => $row['source'] ?? RatingSnapshot::SOURCE_SERPAPI,
                ],
                $attributes,
            );
            $count++;
        }

        $this->line("  rating_snapshots: {$count} diproses.");

        return $count;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, int>  $idMap
     */
    private function importDailyStats(array $rows, array $idMap): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $placeId = $this->mapPlaceId($row['place_id'] ?? null, $idMap);

            if ($placeId === null) {
                continue;
            }

            $attributes = collect($row)->except(['id', 'created_at', 'updated_at'])->all();
            $attributes['place_id'] = $placeId;

            DailyReviewStat::query()->updateOrCreate(
                ['place_id' => $placeId, 'stat_date' => $row['stat_date']],
                $attributes,
            );
            $count++;
        }

        $this->line("  daily_review_stats: {$count} diproses.");

        return $count;
    }

    /**
     * @param  array<int, int>  $idMap
     */
    private function mapPlaceId(mixed $oldId, array $idMap): ?int
    {
        if ($oldId === null) {
            return null;
        }

        $oldId = (int) $oldId;

        if ($idMap !== [] && isset($idMap[$oldId])) {
            return $idMap[$oldId];
        }

        // Tanpa peta (mis. --skip-places): asumsikan ID sama.
        return Place::query()->whereKey($oldId)->exists() ? $oldId : null;
    }

    private function refreshPlaces(): void
    {
        foreach (Place::query()->get() as $place) {
            $stats = Review::query()
                ->where('place_id', $place->id)
                ->selectRaw('COUNT(*) as total, MIN(review_date) as oldest, MAX(review_date) as newest')
                ->first();

            $place->forceFill([
                'reviews_synced' => (int) ($stats->total ?? 0),
                'oldest_review_date' => $stats->oldest ?? null,
                'newest_review_date' => $stats->newest ?? null,
            ])->save();
        }

        $this->line('  statistik tempat: diperbarui.');
    }
}
