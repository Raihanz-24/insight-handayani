<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\SerpApi\SerpApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SerpApiClientTest extends TestCase
{
    use RefreshDatabase;

    private function client(string $key = 'test-key'): SerpApiClient
    {
        return new SerpApiClient(apiKey: $key, retry: 0, timeout: 5);
    }

    public function test_not_configured_without_key(): void
    {
        $result = $this->client('')->fetchRating(dataId: '0x1:0x2');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('SERPAPI_KEY', (string) $result->error);
    }

    public function test_requires_data_or_place_id(): void
    {
        $result = $this->client()->fetchRating();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('data_id', (string) $result->error);
    }

    public function test_fetches_rating_and_review_count(): void
    {
        Http::fake([
            'serpapi.com/*' => Http::response([
                'place_info' => [
                    'title' => 'RM Handayani',
                    'rating' => 4.6,
                    'reviews' => 1591,
                ],
            ], 200),
        ]);

        $costs = [];
        $result = $this->client()->fetchRating(
            dataId: '0x2dd7036fcefb89b5:0xbaf316dbc59eefd5',
            onSearch: function (int $c) use (&$costs): void {
                $costs[] = $c;
            },
        );

        $this->assertTrue($result->success);
        $this->assertSame(4.6, $result->rating);
        $this->assertSame(1591, $result->reviewsCount);
        $this->assertSame('RM Handayani', $result->placeTitle);
        $this->assertSame([1], $costs); // 1 search terpakai.

        Http::assertSent(function (Request $req): bool {
            return str_contains($req->url(), 'serpapi.com')
                && $req['engine'] === 'google_maps_reviews'
                && $req['data_id'] === '0x2dd7036fcefb89b5:0xbaf316dbc59eefd5'
                && $req['api_key'] === 'test-key';
        });
    }

    public function test_handles_error_payload(): void
    {
        Http::fake([
            'serpapi.com/*' => Http::response(['error' => 'Invalid API key'], 200),
        ]);

        $result = $this->client()->fetchRating(dataId: '0x1:0x2');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid API key', (string) $result->error);
    }

    public function test_missing_place_info_is_error(): void
    {
        Http::fake([
            'serpapi.com/*' => Http::response(['search_metadata' => []], 200),
        ]);

        $result = $this->client()->fetchRating(dataId: '0x1:0x2');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('place_info', (string) $result->error);
    }

    // -----------------------------------------------------------------
    // fetchReviews (paginasi)
    // -----------------------------------------------------------------

    public function test_fetch_reviews_paginates_and_counts_searches(): void
    {
        Http::fake([
            'serpapi.com/*' => Http::sequence()
                ->push([
                    'reviews' => [
                        ['rating' => 5.0, 'iso_date' => '2026-06-10T10:00:00Z', 'review_id' => 'a', 'user' => ['name' => 'A']],
                        ['rating' => 3.0, 'iso_date' => '2026-06-09T10:00:00Z', 'review_id' => 'b', 'user' => ['name' => 'B']],
                    ],
                    'serpapi_pagination' => ['next_page_token' => 'TOKEN1'],
                ])
                ->push([
                    'reviews' => [
                        ['rating' => 1.0, 'iso_date' => '2026-06-08T10:00:00Z', 'review_id' => 'c', 'user' => ['name' => 'C']],
                    ],
                    'serpapi_pagination' => [],
                ]),
        ]);

        $costs = [];
        $out = $this->client()->fetchReviews(
            dataId: '0x1:0x2',
            maxPages: 5,
            onSearch: function (int $c) use (&$costs): void {
                $costs[] = $c;
            },
        );

        $this->assertTrue($out['success']);
        $this->assertCount(3, $out['reviews']);
        $this->assertSame(2, $out['pages_fetched']);
        $this->assertSame([1, 1], $costs); // 2 search terpakai.

        // Halaman lanjutan WAJIB kirim num (maks 20) untuk menghemat request.
        Http::assertSent(fn (Request $req): bool => isset($req['next_page_token']) && (int) $req['num'] === 20);
    }

    public function test_fetch_reviews_resumes_from_initial_page_one_without_refetch(): void
    {
        // Halaman 1 sudah diambil (lewat fetchRating) → klien hanya mengambil
        // halaman ke-2 dst, TIDAK mengulang halaman pertama.
        Http::fake([
            'serpapi.com/*' => Http::response([
                'reviews' => [
                    ['rating' => 4.0, 'iso_date' => '2026-06-05T10:00:00Z', 'review_id' => 'z', 'user' => ['name' => 'Z']],
                ],
                'serpapi_pagination' => [],
            ], 200),
        ]);

        $costs = [];
        $out = $this->client()->fetchReviews(
            dataId: '0x1:0x2',
            maxPages: 3,
            onSearch: function (int $c) use (&$costs): void {
                $costs[] = $c;
            },
            initialReviews: [
                ['rating' => 5.0, 'iso_date' => '2026-06-10T10:00:00Z', 'review_id' => 'a', 'user' => ['name' => 'A']],
            ],
            initialToken: 'TOKEN1',
        );

        $this->assertTrue($out['success']);
        $this->assertCount(2, $out['reviews']); // 1 dari halaman 1 + 1 dari halaman 2.
        $this->assertSame(2, $out['pages_fetched']); // halaman 1 tetap dihitung.
        $this->assertSame([1], $costs); // hanya 1 search baru terpakai.

        Http::assertSent(fn (Request $req): bool => ($req['next_page_token'] ?? null) === 'TOKEN1');
    }

    public function test_fetch_reviews_respects_max_pages(): void
    {
        Http::fake([
            'serpapi.com/*' => Http::response([
                'reviews' => [
                    ['rating' => 5.0, 'iso_date' => '2026-06-10T10:00:00Z', 'review_id' => 'a', 'user' => ['name' => 'A']],
                ],
                'serpapi_pagination' => ['next_page_token' => 'KEEP_GOING'],
            ], 200),
        ]);

        $out = $this->client()->fetchReviews(dataId: '0x1:0x2', maxPages: 2);

        $this->assertSame(2, $out['pages_fetched']);
    }
}
