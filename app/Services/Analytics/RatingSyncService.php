<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Place;
use App\Models\RatingSnapshot;
use App\Models\Review;
use App\Services\SerpApi\QuotaGuard;
use App\Services\SerpApi\RatingResult;
use App\Services\SerpApi\ReviewParser;
use App\Services\SerpApi\SerpApiClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Mengambil data rating & review sebuah tempat dari SerpApi, lalu menyimpannya:
 *  - `rating_snapshots` : ringkasan (rating rata-rata + total ulasan) per hari,
 *  - `reviews`          : review individual (bintang + tanggal) — AKUMULATIF.
 *
 * Menghormati: mode analisis tempat, guard kuota, idempotensi.
 */
class RatingSyncService
{
    public function __construct(
        private readonly SerpApiClient $client,
        private readonly QuotaGuard $quota,
    ) {}

    /**
     * Ambil & simpan untuk sebuah tempat.
     *
     * @param  bool  $force  Abaikan saklar mode (tombol manual developer).
     * @param  bool  $withReviews  Sekaligus ambil review individual (paginasi).
     * @param  int  $maxReviewPages  Batas halaman review (hemat kuota).
     */
    public function syncPlace(Place $place, bool $force = false, bool $withReviews = true, ?int $maxReviewPages = null): RatingSnapshot
    {
        $now = CarbonImmutable::now();

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

        $withReviews = $withReviews && (bool) config('serpapi.reviews_enabled', true);

        $snapshot = $this->store($place, $now, $result);

        if ($snapshot->status === RatingSnapshot::STATUS_OK && $withReviews) {
            // Halaman pertama sudah diambil lewat fetchRating → lanjutkan dari
            // token-nya agar tidak memakai kuota ekstra (hemat 1 search).
            $this->syncReviews(
                $place,
                $maxReviewPages,
                initialReviews: $result->reviews,
                initialToken: $result->nextPageToken,
            );
        }

        return $snapshot;
    }

    /**
     * Ambil review individual (berhalaman) & simpan secara akumulatif.
     *
     * @param  array<int, array<string, mixed>>  $initialReviews  review halaman 1 (bila sudah diambil)
     * @return int jumlah review BARU yang disimpan
     */
    public function syncReviews(
        Place $place,
        ?int $maxReviewPages = null,
        array $initialReviews = [],
        ?string $initialToken = null,
    ): int {
        if (! $this->client->isConfigured() || ! $place->isFetchable()) {
            return 0;
        }

        // Batasi jumlah halaman agar tidak melebihi sisa kuota hari ini.
        $remaining = $this->quota->remaining();

        if ($remaining <= 0 && $initialReviews === []) {
            return 0;
        }

        $requestedPages = $maxReviewPages ?? (int) config('serpapi.reviews_max_pages', 3);

        // `maxPages` = total anggaran halaman (halaman 1 yang sudah diambil
        // lewat fetchRating tetap dihitung 1 oleh klien).
        $maxPages = min(max(1, $requestedPages), max(1, $remaining));

        $now = CarbonImmutable::now();

        $response = $this->client->fetchReviews(
            dataId: $place->serpapi_data_id,
            placeId: $place->serpapi_place_id,
            maxPages: $maxPages,
            sortBy: 'newestFirst',
            onSearch: fn (int $cost) => $this->quota->record($cost),
            initialReviews: $initialReviews,
            initialToken: $initialToken,
        );

        if (! $response['success'] || $response['reviews'] === []) {
            return 0;
        }

        $inserted = 0;

        DB::transaction(function () use ($place, $response, $now, &$inserted): void {
            foreach ($response['reviews'] as $raw) {
                $parsed = ReviewParser::parse($raw);

                if ($parsed === null) {
                    continue;
                }

                // Akumulatif & idempoten: lewati yang sudah ada.
                $exists = Review::query()
                    ->where('place_id', $place->id)
                    ->where('review_key', $parsed['review_key'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                Review::query()->create([
                    'place_id' => $place->id,
                    'review_id' => $parsed['review_id'],
                    'review_key' => $parsed['review_key'],
                    'rating' => $parsed['rating'],
                    'rating_raw' => $parsed['rating_raw'],
                    'review_date' => $parsed['review_date'],
                    'reviewed_at' => $parsed['reviewed_at'],
                    'author_name' => $parsed['author_name'],
                    'author_id' => $parsed['author_id'],
                    'likes' => $parsed['likes'],
                    'snippet' => $parsed['snippet'],
                    'captured_at' => $now,
                ]);

                $inserted++;
            }

            $this->refreshReviewStats($place);
        });

        return $inserted;
    }

    /**
     * Perbarui statistik cakupan review pada tempat.
     */
    public function refreshReviewStats(Place $place): void
    {
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

    /**
     * Jalankan untuk semua tempat terjadwal yang sudah due.
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
