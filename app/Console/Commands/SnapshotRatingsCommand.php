<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Place;
use App\Services\Analytics\RatingSyncService;
use App\Services\SerpApi\QuotaGuard;
use Illuminate\Console\Command;

/**
 * Mengambil snapshot rating + review individual untuk tempat yang analisisnya aktif.
 *
 * Dijalankan oleh scheduler (lihat routes/console.php) ATAU manual.
 * Hanya memproses tempat dengan mode `manual`/`scheduled` (mode `off` dilewati).
 * Sinkronisasi terjadwal hanya untuk tempat yang sudah due (interval & jam).
 */
class SnapshotRatingsCommand extends Command
{
    protected $signature = 'analytics:snapshot-ratings
                            {--force : Ambil untuk SEMUA tempat aktif (abaikan jadwal & mode off)}
                            {--place= : ID tempat tertentu saja}
                            {--no-reviews : Jangan ambil review individual (hanya ringkasan rating)}';

    protected $description = 'Ambil snapshot rating + review Google Maps (via SerpApi) untuk tempat yang aktif.';

    public function handle(RatingSyncService $sync, QuotaGuard $quota): int
    {
        $withReviews = ! (bool) $this->option('no-reviews');

        $this->info('Sisa kuota SerpApi hari ini: '.$quota->remaining().'/'.$quota->dailyLimit());

        if ($this->quotaExhausted($quota)) {
            $this->warn('Kuota SerpApi harian sudah habis. Berhenti.');

            return self::SUCCESS;
        }

        if ($placeId = $this->option('place')) {
            return $this->syncOne((int) $placeId, $sync, $withReviews);
        }

        $count = 0;

        if ($this->option('force')) {
            foreach (Place::query()->active()->get() as $place) {
                $this->process($place, $sync, force: true, withReviews: $withReviews);
                $count++;
            }
        } else {
            // Mode terjadwal yang sudah due.
            $snapshots = [];

            foreach (Place::query()->scheduled()->get() as $place) {
                if (! $place->isDueForSync()) {
                    continue;
                }

                if (! $quota->hasRemaining()) {
                    $this->warn('Kuota habis di tengah proses. Berhenti.');
                    break;
                }

                $snapshots[] = $sync->syncPlace($place, withReviews: $withReviews);
                $count++;
            }

            foreach ($snapshots as $snapshot) {
                $this->line(sprintf(
                    '  - %s: %s',
                    $snapshot->place->name ?? "place#{$snapshot->place_id}",
                    $snapshot->status === 'ok'
                        ? "OK ({$snapshot->rating}★, {$snapshot->reviews_count} ulasan)"
                        : "GAGAL ({$snapshot->error_message})",
                ));
            }
        }

        $this->info("Selesai. {$count} tempat diproses.");

        return self::SUCCESS;
    }

    private function syncOne(int $placeId, RatingSyncService $sync, bool $withReviews): int
    {
        $place = Place::query()->find($placeId);

        if ($place === null) {
            $this->error("Tempat #{$placeId} tidak ditemukan.");

            return self::FAILURE;
        }

        $snapshot = $sync->syncPlace($place, force: true, withReviews: $withReviews);

        if ($snapshot->status === 'ok') {
            $place->refresh();
            $this->info("{$place->name}: OK ({$snapshot->rating}★, {$snapshot->reviews_count} ulasan; total review tersimpan: {$place->reviews_synced})");
        } else {
            $this->error("{$place->name}: GAGAL ({$snapshot->error_message})");
        }

        return self::SUCCESS;
    }

    private function process(Place $place, RatingSyncService $sync, bool $force, bool $withReviews): void
    {
        $snapshot = $sync->syncPlace($place, force: $force, withReviews: $withReviews);

        $place->refresh();

        $this->line(sprintf(
            '  - %s: %s',
            $place->name,
            $snapshot->status === 'ok'
                ? "OK ({$snapshot->rating}★, review tersimpan: {$place->reviews_synced})"
                : "GAGAL ({$snapshot->error_message})",
        ));
    }

    private function quotaExhausted(QuotaGuard $quota): bool
    {
        return ! $quota->hasRemaining();
    }
}
