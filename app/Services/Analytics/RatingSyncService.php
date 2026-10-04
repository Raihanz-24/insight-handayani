<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Place;
use App\Models\RatingSnapshot;
use App\Services\SerpApi\QuotaGuard;
use App\Services\SerpApi\RatingResult;
use App\Services\SerpApi\SerpApiClient;
use Carbon\CarbonImmutable;

/**
 * Mengambil data rating satu tempat lalu menyimpannya sebagai snapshot.
 *
 * Menghormati:
 *  - mode analisis tempat (off → tidak diambil),
 *  - guard kuota SerpApi harian,
 *  - idempotensi (unik per tempat/hari/sumber).
 */
class RatingSyncService
{
    public function __construct(
        private readonly SerpApiClient $client,
        private readonly QuotaGuard $quota,
    ) {}

    /**
     * Ambil & simpan snapshot untuk sebuah tempat.
     *
     * @param  bool  $force  Abaikan saklar mode (untuk tombol manual developer).
     */
    public function syncPlace(Place $place, bool $force = false): RatingSnapshot
    {
        $now = CarbonImmutable::now();

        // Bila tidak dipaksa & analisis mati → catat sebagai error "off"?
        // Tidak: cukup kembalikan snapshot status error agar jejak jelas.
        if (! $force && ! $place->isAnalysisEnabled()) {
            return $this->storeError($place, $now, 'Analisis dinonaktifkan untuk tempat ini.');
        }

        if (! $place->isFetchable()) {
            return $this->storeError($place, $now, 'Tempat belum punya data_id/place_id.');
        }

        if (! $this->client->isConfigured()) {
            return $this->storeError($place, $now, 'SERPAPI_KEY belum diatur.', source: RatingSnapshot::SOURCE_MANUAL);
        }

        if (! $this->quota->hasRemaining()) {
            return $this->storeError($place, $now, 'Kuota SerpApi harian habis.');
        }

        $result = $this->client->fetchRating(
            dataId: $place->serpapi_data_id,
            placeId: $place->serpapi_place_id,
            onSearch: fn (int $cost) => $this->quota->record($cost),
        );

        return $this->store($place, $now, $result);
    }

    /**
     * Jalankan sinkronisasi untuk semua tempat terjadwal yang sudah waktunya.
     *
     * @return array<int, RatingSnapshot>
     */
    public function syncDue(): array
    {
        $snapshots = [];

        foreach (Place::query()->scheduled()->get() as $place) {
            if (! $place->isDueForSync()) {
                continue;
            }

            $snapshots[] = $this->syncPlace($place);
        }

        return $snapshots;
    }

    private function store(Place $place, CarbonImmutable $now, RatingResult $result): RatingSnapshot
    {
        if (! $result->success) {
            return $this->storeError($place, $now, (string) $result->error, $result->meta);
        }

        $snapshot = RatingSnapshot::query()->updateOrCreate(
            [
                'place_id' => $place->id,
                'captured_date' => $now->toDateString(),
                'source' => RatingSnapshot::SOURCE_SERPAPI,
            ],
            [
                'captured_at' => $now,
                'rating' => $result->rating,
                'reviews_count' => $result->reviewsCount,
                'status' => RatingSnapshot::STATUS_OK,
                'error_message' => null,
                'meta' => $result->meta,
            ],
        );

        $place->forceFill(['last_synced_at' => $now])->save();

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function storeError(
        Place $place,
        CarbonImmutable $now,
        string $message,
        array $meta = [],
        string $source = RatingSnapshot::SOURCE_SERPAPI,
    ): RatingSnapshot {
        return RatingSnapshot::query()->updateOrCreate(
            [
                'place_id' => $place->id,
                'captured_date' => $now->toDateString(),
                'source' => $source,
            ],
            [
                'captured_at' => $now,
                'rating' => null,
                'reviews_count' => null,
                'status' => RatingSnapshot::STATUS_ERROR,
                'error_message' => $message,
                'meta' => $meta,
            ],
        );
    }
}
