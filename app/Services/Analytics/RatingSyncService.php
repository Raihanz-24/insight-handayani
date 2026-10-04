<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\DailyReviewStat;
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

        $starCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        if ($snapshot->status === RatingSnapshot::STATUS_OK && $withReviews) {
            // Berhenti lebih awal begitu review lebih tua dari (hari ini - buffer).
            // Karena review per hari sedikit, sync harian biasanya cukup 1-2 request.
            $bufferDays = (int) config('serpapi.stop_before_buffer_days', 1);
            $stopBefore = $now->subDays($bufferDays)->toDateString();

            // Halaman pertama sudah diambil lewat fetchRating → lanjutkan dari
            // token-nya agar tidak memakai kuota ekstra (hemat 1 search).
            $starCounts = $this->syncReviews(
                $place,
                $maxReviewPages,
                initialReviews: $result->reviews,
                initialToken: $result->nextPageToken,
                stopBeforeDate: $stopBefore,
            );
        }

        // Catat ringkasan HARIAN (review baru hari ini + pecahan bintang).
        if ($snapshot->status === RatingSnapshot::STATUS_OK) {
            $this->recordDailyStat($place, $now, $snapshot, $starCounts);
        }

        return $snapshot;
    }

    /**
     * Ambil review individual (berhalaman) & simpan secara akumulatif.
     *
     * @param  array<int, array<string, mixed>>  $initialReviews  review halaman 1 (bila sudah diambil)
     * @param  string|null  $stopBeforeDate  berhenti bila review lebih tua dari tanggal ini (hemat kuota)
     * @return array<int, int> jumlah review BARU per bintang [1=>n, ..., 5=>n]
     */
    public function syncReviews(
        Place $place,
        ?int $maxReviewPages = null,
        array $initialReviews = [],
        ?string $initialToken = null,
        ?string $stopBeforeDate = null,
    ): array {
        $empty = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        if (! $this->client->isConfigured() || ! $place->isFetchable()) {
            return $empty;
        }

        // Batasi jumlah halaman agar tidak melebihi sisa kuota hari ini.
        $remaining = $this->quota->remaining();

        if ($remaining <= 0 && $initialReviews === []) {
            return $empty;
        }

        $requestedPages = $maxReviewPages ?? (int) config('serpapi.reviews_max_pages', 25);

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
            stopBeforeDate: $stopBeforeDate,
        );

        if (! $response['success'] || $response['reviews'] === []) {
            return $empty;
        }

        $starCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        DB::transaction(function () use ($place, $response, $now, &$starCounts): void {
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

                $starCounts[$parsed['rating']] = ($starCounts[$parsed['rating']] ?? 0) + 1;
            }

            $this->refreshReviewStats($place);
        });

        return $starCounts;
    }

    /**
     * Backfill: tarik review historis sampai kehabisan token atau batas halaman.
     *
     * @param  string|null  $stopBeforeDate  berhenti bila review lebih tua dari tanggal ini (mis. 7 hari lalu)
     * @return int jumlah review BARU yang tersimpan
     */
    public function backfillReviews(Place $place, ?int $maxPages = null, ?string $stopBeforeDate = null): int
    {
        if (! $this->client->isConfigured() || ! $place->isFetchable()) {
            return 0;
        }

        $limit = $maxPages ?? (int) config('serpapi.reviews_max_pages_backfill', 100);

        $before = (int) $place->reviews_synced;

        // Tidak pakai `initialReviews` → ambil dari halaman 1 (rating sudah
        // tersimpan terpisah bila perlu; backfill fokus review).
        $this->syncReviews($place, $limit, stopBeforeDate: $stopBeforeDate);

        $place->refresh();

        return max(0, (int) $place->reviews_synced - $before);
    }

    /**
     * Catat / perbarui ringkasan HARIAN untuk sebuah tempat.
     *
     * `new_reviews` = review baru yang tersimpan HARI INI (akumulatif dalam hari).
     * Pecahan bintang diakumulasikan bila dipanggil beberapa kali pada hari sama.
     * `reviews_delta` = selisih total ulasan Google vs hari snapshot sebelumnya.
     *
     * @param  array<int, int>  $starCounts
     */
    public function recordDailyStat(Place $place, CarbonImmutable $now, RatingSnapshot $snapshot, array $starCounts): DailyReviewStat
    {
        $date = $now->toDateString();
        $newToday = array_sum($starCounts);

        $existing = DailyReviewStat::query()
            ->where('place_id', $place->id)
            ->where('stat_date', $date)
            ->first();

        // Total ulasan pada snapshot sebelumnya (untuk menghitung delta).
        $previousTotal = RatingSnapshot::query()
            ->where('place_id', $place->id)
            ->successful()
            ->where('captured_date', '<', $date)
            ->orderByDesc('captured_date')
            ->value('reviews_count');

        $delta = ($previousTotal !== null && $snapshot->reviews_count !== null)
            ? (int) $snapshot->reviews_count - (int) $previousTotal
            : null;

        $place->refresh();

        $data = [
            'new_reviews' => ($existing->new_reviews ?? 0) + $newToday,
            'star_1' => ($existing->star_1 ?? 0) + ($starCounts[1] ?? 0),
            'star_2' => ($existing->star_2 ?? 0) + ($starCounts[2] ?? 0),
            'star_3' => ($existing->star_3 ?? 0) + ($starCounts[3] ?? 0),
            'star_4' => ($existing->star_4 ?? 0) + ($starCounts[4] ?? 0),
            'star_5' => ($existing->star_5 ?? 0) + ($starCounts[5] ?? 0),
            'total_reviews' => $snapshot->reviews_count,
            'reviews_delta' => $delta,
            'average_rating' => $snapshot->rating,
            'synced_total' => (int) $place->reviews_synced,
        ];

        return DailyReviewStat::query()->updateOrCreate(
            ['place_id' => $place->id, 'stat_date' => $date],
            $data,
        );
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
