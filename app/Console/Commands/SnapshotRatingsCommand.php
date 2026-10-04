<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Place;
use App\Services\Analytics\RatingSyncService;
use App\Services\SerpApi\QuotaGuard;
use Illuminate\Console\Command;

/**
 * Mengambil snapshot rating untuk tempat yang ANALISIS-nya aktif.
 *
 * Dijalankan oleh scheduler (lihat routes/console.php) ATAU manual.
 * Hanya memproses tempat dengan mode `manual`/`scheduled` (mode `off` dilewati).
 * Sinkronisasi terjadwal hanya untuk tempat yang sudah due (interval & jam).
 */
class SnapshotRatingsCommand extends Command
{
    protected $signature = 'analytics:snapshot-ratings
                            {--force : Ambil untuk SEMUA tempat aktif (abaikan jadwal & mode off)}
                            {--place= : ID tempat tertentu saja}';

    protected $description = 'Ambil snapshot rating Google Maps (via SerpApi) untuk tempat yang aktif.';

    public function handle(RatingSyncService $sync, QuotaGuard $quota): int
    {
        $this->info('Sisa kuota SerpApi hari ini: '.$quota->remaining().'/'.$quota->dailyLimit());

        if ($this->quotaExhausted($quota)) {
            $this->warn('Kuota SerpApi harian sudah habis. Berhenti.');

            return self::SUCCESS;
        }

        if ($placeId = $this->option('place')) {
            return $this->syncOne((int) $placeId, $sync);
        }

        $count = 0;

        if ($this->option('force')) {
            $places = Place::query()->active()->get();

            foreach ($places as $place) {
                $count += $this->process($place, $sync, force: true);
            }
        } else {
            // Mode terjadwal yang sudah due.
            $snapshots = $sync->syncDue();
            $count = count($snapshots);

            foreach ($snapshots as $snapshot) {
                $this->line(sprintf(
                    '  - %s: %s',
                    $snapshot->place->name ?? "place#{$snapshot->place_id}",
                    $snapshot->status === 'ok' ? "OK ({$snapshot->rating}★, {$snapshot->reviews_count} ulasan)" : "GAGAL ({$snapshot->error_message})",
                ));
            }
        }

        $this->info("Selesai. {$count} tempat diproses.");

        return self::SUCCESS;
    }

    private function syncOne(int $placeId, RatingSyncService $sync): int
    {
        $place = Place::query()->find($placeId);

        if ($place === null) {
            $this->error("Tempat #{$placeId} tidak ditemukan.");

            return self::FAILURE;
        }

        $snapshot = $sync->syncPlace($place, force: true);

        if ($snapshot->status === 'ok') {
            $this->info("{$place->name}: OK ({$snapshot->rating}★, {$snapshot->reviews_count} ulasan)");
        } else {
            $this->error("{$place->name}: GAGAL ({$snapshot->error_message})");
        }

        return self::SUCCESS;
    }

    /**
     * @return int jumlah tempat berhasil diproses
     */
    private function process(Place $place, RatingSyncService $sync, bool $force): int
    {
        $snapshot = $sync->syncPlace($place, force: $force);

        $this->line(sprintf(
            '  - %s: %s',
            $place->name,
            $snapshot->status === 'ok' ? "OK ({$snapshot->rating}★)" : "GAGAL ({$snapshot->error_message})",
        ));

        return 1;
    }

    private function quotaExhausted(QuotaGuard $quota): bool
    {
        return ! $quota->hasRemaining();
    }
}
