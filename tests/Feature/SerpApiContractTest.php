<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Place;
use App\Models\Review;
use App\Services\Analytics\RatingAnalyticsService;
use App\Services\Analytics\RatingSyncService;
use App\Services\SerpApi\QuotaGuard;
use App\Services\SerpApi\SerpApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Uji integrasi memakai bentuk respons PERSIS seperti contoh di dokumentasi
 * SerpApi (https://serpapi.com/google-maps-reviews-api) → memastikan parser
 * kita cocok dengan struktur JSON nyata (place_info, reviews[], user, pagination).
 */
class SerpApiContractTest extends TestCase
{
    use RefreshDatabase;

    private function service(int $dailyLimit = 40): RatingSyncService
    {
        return new RatingSyncService(
            new SerpApiClient(apiKey: 'test-key', retry: 0, timeout: 5),
            new QuotaGuard(dailyLimit: $dailyLimit),
        );
    }

    /**
     * Cuplikan respons sesuai dokumentasi resmi (dipotong seperlunya).
     *
     * @return array<string, mixed>
     */
    private function docsPayload(): array
    {
        return [
            'place_info' => [
                'title' => "McDonald's",
                'address' => '1528 Broadway Times Square, New York, NY 10036',
                'rating' => 3.7,
                'reviews' => 1591,
                'type' => 'Fast food restaurant',
            ],
            'topics' => [
                ['keyword' => 'bathroom', 'mentions' => 33, 'id' => '/m/01j2bj'],
            ],
            'reviews' => [
                [
                    'position' => 1,
                    'link' => 'https://www.google.com/maps/reviews/data=!4m8',
                    'rating' => 1.0,
                    'date' => '2 months ago',
                    'iso_date' => '2025-11-17T13:48:47Z',
                    'iso_date_of_last_edit' => '2025-11-17T13:48:47Z',
                    'source' => 'Google',
                    'review_id' => 'REV_A',
                    'user' => [
                        'name' => 'Breanna Blackwell',
                        'contributor_id' => '10689629',
                        'local_guide' => true,
                        'reviews' => 6,
                        'photos' => 2,
                    ],
                    'snippet' => 'As someone who work in fast food .',
                    'likes' => 0,
                ],
                [
                    'position' => 2,
                    'rating' => 5.0,
                    'date' => '3 weeks ago',
                    'iso_date' => '2025-12-01T09:00:00Z',
                    'review_id' => 'REV_B',
                    'user' => ['name' => 'Andi', 'contributor_id' => '999'],
                    'snippet' => 'Enak!',
                    'likes' => 2,
                ],
            ],
            'serpapi_pagination' => [
                'next' => 'https://serpapi.com/search.json?data_id=0x89c25977%3A0x4387a82e&engine=google_maps_reviews',
                'next_page_token' => 'NEXT_TOKEN',
            ],
        ];
    }

    public function test_parses_documentation_payload_and_stores_reviews(): void
    {
        Http::fake(['serpapi.com/*' => Http::response($this->docsPayload(), 200)]);

        $place = Place::factory()->fetchable()->create([
            'name' => "McDonald's",
            'analysis_mode' => Place::MODE_MANUAL,
        ]);

        // maxReviewPages: 1 → cukup halaman pertama (yang sudah ikut), tanpa request lanjutan.
        $snapshot = $this->service()->syncPlace($place, withReviews: true, maxReviewPages: 1);

        $this->assertSame('ok', $snapshot->status);
        $this->assertSame(3.7, $snapshot->rating);
        $this->assertSame(1591, $snapshot->reviews_count);

        $this->assertSame(2, Review::query()->where('place_id', $place->id)->count());

        $first = Review::query()->where('review_id', 'REV_A')->first();
        $this->assertNotNull($first);
        $this->assertSame(1, $first->rating);
        $this->assertSame('2025-11-17', $first->review_date->toDateString());
        $this->assertSame('Breanna Blackwell', $first->author_name);
        $this->assertSame('10689629', $first->author_id);
    }

    public function test_star_distribution_matches_stored_reviews(): void
    {
        Http::fake(['serpapi.com/*' => Http::response($this->docsPayload(), 200)]);

        $place = Place::factory()->fetchable()->create(['analysis_mode' => Place::MODE_MANUAL]);
        $this->service()->syncPlace($place, withReviews: true, maxReviewPages: 1);

        $report = app(RatingAnalyticsService::class)
            ->reportForPlace($place, '2025-01-01', '2025-12-31');

        // 1× bintang 1, 1× bintang 5 (sesuai payload dokumentasi).
        $this->assertSame(1, $report->distribution[5]);
        $this->assertSame(1, $report->distribution[1]);
        $this->assertSame(0, $report->distribution[4]);
        $this->assertSame(2, $report->total);
    }

    public function test_follows_pagination_token_using_num_20(): void
    {
        Http::fake([
            'serpapi.com/*' => Http::sequence()
                ->push($this->docsPayload(), 200)
                ->push([
                    'reviews' => [
                        ['rating' => 4.0, 'iso_date' => '2025-10-01T00:00:00Z', 'review_id' => 'REV_C', 'user' => ['name' => 'C']],
                    ],
                    'serpapi_pagination' => [],
                ], 200),
        ]);

        $place = Place::factory()->fetchable()->create(['analysis_mode' => Place::MODE_MANUAL]);

        $this->service()->syncPlace($place, withReviews: true, maxReviewPages: 3);

        $this->assertSame(3, Review::query()->where('place_id', $place->id)->count());

        // Halaman ke-2 harus dikirim dengan next_page_token + num=20.
        Http::assertSent(function ($req): bool {
            return ($req['next_page_token'] ?? null) === 'NEXT_TOKEN'
                && (int) ($req['num'] ?? 0) === 20;
        });
    }
}
