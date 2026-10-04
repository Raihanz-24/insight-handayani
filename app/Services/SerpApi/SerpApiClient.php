<?php

declare(strict_types=1);

namespace App\Services\SerpApi;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Klien SerpApi untuk engine `google_maps_reviews`.
 *
 * Dokumentasi: https://serpapi.com/google-maps-reviews-api
 *
 * Penting:
 *  - Setiap request (halaman) dihitung 1 "search" pada kuota.
 *  - Hanya membutuhkan `data_id` (atau `place_id`) sebuah tempat.
 *  - Mengembalikan rating rata-rata + jumlah ulasan kumulatif.
 */
class SerpApiClient
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly string $baseUrl = 'https://serpapi.com/search',
        private readonly string $engine = 'google_maps_reviews',
        private readonly string $hl = 'id',
        private readonly string $gl = 'id',
        private readonly int $timeout = 20,
        private readonly int $retry = 2,
        private readonly int $retryDelay = 500,
        private readonly int $maxPages = 3,
    ) {}

    /**
     * Apakah klien siap dipakai (API key tersedia).
     */
    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * Ambil rating + jumlah ulasan untuk sebuah tempat.
     *
     * @param  callable(int $cost): void|null  $onSearch  dipanggil setiap 1 search terpakai (untuk guard kuota).
     */
    public function fetchRating(
        ?string $dataId = null,
        ?string $placeId = null,
        ?callable $onSearch = null,
    ): RatingResult {
        if (! $this->isConfigured()) {
            return RatingResult::error('SERPAPI_KEY belum diatur.');
        }

        if (blank($dataId) && blank($placeId)) {
            return RatingResult::error('Tempat belum punya data_id/place_id.');
        }

        $pages = 0;
        $placeInfo = null;

        try {
            // Ambil halaman pertama (cukup untuk place_info rating & reviews).
            $params = [
                'engine' => $this->engine,
                'api_key' => $this->apiKey,
                'hl' => $this->hl,
                'gl' => $this->gl,
            ];

            if (filled($dataId)) {
                $params['data_id'] = $dataId;
            } else {
                $params['place_id'] = $placeId;
            }

            $response = $this->request($params);
            $pages++;
            $onSearch?->__invoke(1);

            $json = $response->json() ?? [];

            if ($response->failed()) {
                return RatingResult::error(
                    'SerpApi HTTP '.$response->status().': '.$this->extractError($json),
                    $pages,
                );
            }

            if (isset($json['error'])) {
                return RatingResult::error('SerpApi error: '.$json['error'], $pages);
            }

            $placeInfo = $json['place_info'] ?? null;

            if (! is_array($placeInfo)) {
                return RatingResult::error('Respons SerpApi tidak memuat place_info.', $pages);
            }

            $rating = isset($placeInfo['rating']) ? (float) $placeInfo['rating'] : null;
            $reviews = $this->extractReviewsCount($placeInfo);

            if ($rating === null || $reviews === null) {
                return RatingResult::error('Rating/jumlah ulasan tidak ditemukan pada respons.', $pages);
            }

            return RatingResult::ok(
                rating: $rating,
                reviewsCount: $reviews,
                placeTitle: $placeInfo['title'] ?? null,
                pagesFetched: $pages,
                meta: [
                    'engine' => $this->engine,
                    'data_id' => $dataId,
                    'place_id' => $placeId,
                ],
            );
        } catch (Throwable $e) {
            return RatingResult::error('Exception: '.$e->getMessage(), $pages);
        }
    }

    /**
     * Ambil daftar review berhalaman untuk sebuah tempat.
     *
     * Mengembalikan review + info ulasan. Berhenti lebih awal bila:
     *  - mencapai `maxPages`,
     *  - halaman tidak punya token lanjutan,
     *  - `stopBeforeDate` diisi & halaman sudah memuat review lebih tua dari itu
     *    (berguna dengan sort_by=newestFirst agar hemat kuota).
     *
     * @param  callable(int $cost): void|null  $onSearch
     * @return array{success: bool, reviews: array<int, array<string, mixed>>, place_info: ?array<string, mixed>, pages_fetched: int, error: ?string}
     */
    public function fetchReviews(
        ?string $dataId = null,
        ?string $placeId = null,
        int $maxPages = 1,
        string $sortBy = 'newestFirst',
        ?callable $onSearch = null,
        ?string $stopBeforeDate = null,
    ): array {
        if (! $this->isConfigured()) {
            return $this->reviewsResult(false, [], null, 0, 'SERPAPI_KEY belum diatur.');
        }

        if (blank($dataId) && blank($placeId)) {
            return $this->reviewsResult(false, [], null, 0, 'Tempat belum punya data_id/place_id.');
        }

        $pages = 0;
        $all = [];
        $placeInfo = null;
        $token = null;

        try {
            do {
                $params = [
                    'engine' => $this->engine,
                    'api_key' => $this->apiKey,
                    'hl' => $this->hl,
                    'gl' => $this->gl,
                    'sort_by' => $sortBy,
                ];

                if (filled($dataId)) {
                    $params['data_id'] = $dataId;
                } else {
                    $params['place_id'] = $placeId;
                }

                if ($token !== null) {
                    $params['next_page_token'] = $token;
                }

                $response = $this->request($params);
                $pages++;
                $onSearch?->__invoke(1);

                $json = $response->json() ?? [];

                if ($response->failed()) {
                    return $this->reviewsResult(false, $all, $placeInfo, $pages, 'SerpApi HTTP '.$response->status().': '.$this->extractError($json));
                }

                if (isset($json['error'])) {
                    return $this->reviewsResult(false, $all, $placeInfo, $pages, 'SerpApi error: '.$json['error']);
                }

                $placeInfo ??= is_array($json['place_info'] ?? null) ? $json['place_info'] : null;

                $pageReviews = is_array($json['reviews'] ?? null) ? $json['reviews'] : [];
                $all = array_merge($all, $pageReviews);

                $token = $json['serpapi_pagination']['next_page_token'] ?? null;

                if ($stopBeforeDate !== null && $this->hasReviewOlderThan($pageReviews, $stopBeforeDate)) {
                    break;
                }
            } while (filled($token) && $pages < $maxPages);

            return $this->reviewsResult(true, $all, $placeInfo, $pages, null);
        } catch (Throwable $e) {
            return $this->reviewsResult(false, $all, $placeInfo, $pages, 'Exception: '.$e->getMessage());
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $reviews
     */
    private function hasReviewOlderThan(array $reviews, string $date): bool
    {
        foreach ($reviews as $review) {
            $iso = $review['iso_date'] ?? null;

            if (is_string($iso) && $iso !== '' && substr($iso, 0, 10) < $date) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{success: bool, reviews: array<int, array<string, mixed>>, place_info: ?array<string, mixed>, pages_fetched: int, error: ?string}
     */
    private function reviewsResult(bool $success, array $reviews, ?array $placeInfo, int $pages, ?string $error): array
    {
        return [
            'success' => $success,
            'reviews' => $reviews,
            'place_info' => $placeInfo,
            'pages_fetched' => $pages,
            'error' => $error,
        ];
    }

    /**
     * SerpApi memakai beberapa nama: `reviews` (jumlah) atau `reviews_count`.
     *
     * @param  array<string, mixed>  $placeInfo
     */
    private function extractReviewsCount(array $placeInfo): ?int
    {
        foreach (['reviews', 'reviews_count', 'total_reviews'] as $key) {
            if (isset($placeInfo[$key]) && is_numeric($placeInfo[$key])) {
                return (int) $placeInfo[$key];
            }
        }

        return null;
    }

    private function request(array $params): Response
    {
        /** @var PendingRequest $pending */
        $pending = Http::timeout($this->timeout)
            ->retry($this->retry, $this->retryDelay, throw: false);

        return $pending->get($this->baseUrl, $params);
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function extractError(array $json): string
    {
        return (string) ($json['error'] ?? 'Unknown error');
    }
}
