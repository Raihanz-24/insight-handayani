<?php

declare(strict_types=1);

namespace App\Services\SerpApi;

use App\Models\Review;
use Carbon\CarbonImmutable;

/**
 * Mengubah satu review mentah SerpApi menjadi array siap simpan.
 */
final class ReviewParser
{
    /**
     * @param  array<string, mixed>  $raw
     * @return array{
     *     review_id: ?string,
     *     review_key: string,
     *     rating: int,
     *     rating_raw: ?float,
     *     review_date: string,
     *     reviewed_at: ?string,
     *     author_name: ?string,
     *     author_id: ?string,
     *     likes: ?int,
     *     snippet: ?string
     * }|null  null bila review tidak valid (tanpa rating/tanggal).
     */
    public static function parse(array $raw): ?array
    {
        $ratingRaw = isset($raw['rating']) && is_numeric($raw['rating']) ? (float) $raw['rating'] : null;
        $isoDate = isset($raw['iso_date']) && is_string($raw['iso_date']) ? $raw['iso_date'] : null;

        if ($ratingRaw === null || $isoDate === null) {
            return null;
        }

        // Bintang: bulatkan ke integer 1..5.
        $rating = (int) max(1, min(5, (int) round($ratingRaw)));

        $date = CarbonImmutable::parse($isoDate);

        $reviewId = isset($raw['review_id']) && is_string($raw['review_id']) ? $raw['review_id'] : null;
        $authorName = $raw['user']['name'] ?? null;
        $authorId = $raw['user']['contributor_id'] ?? null;
        $snippet = $raw['snippet'] ?? ($raw['extracted_snippet']['original'] ?? null);

        return [
            'review_id' => $reviewId,
            'review_key' => Review::keyFor(
                $reviewId,
                is_string($authorName) ? $authorName : null,
                $rating,
                $date->toDateString(),
                is_string($snippet) ? $snippet : null,
            ),
            'rating' => $rating,
            'rating_raw' => $ratingRaw,
            'review_date' => $date->toDateString(),
            'reviewed_at' => $date->toDateTimeString(),
            'author_name' => is_string($authorName) ? $authorName : null,
            'author_id' => is_string($authorId) ? $authorId : null,
            'likes' => isset($raw['likes']) && is_numeric($raw['likes']) ? (int) $raw['likes'] : null,
            'snippet' => is_string($snippet) ? $snippet : null,
        ];
    }
}
