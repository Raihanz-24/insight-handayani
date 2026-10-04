<?php

declare(strict_types=1);

namespace App\Services\SerpApi;

/**
 * Hasil pengambilan data rating sebuah tempat.
 */
final class RatingResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?float $rating = null,
        public readonly ?int $reviewsCount = null,
        public readonly ?string $placeTitle = null,
        public readonly int $pagesFetched = 0,
        public readonly ?string $error = null,
        public readonly array $meta = [],
    ) {}

    public static function ok(
        float $rating,
        int $reviewsCount,
        ?string $placeTitle,
        int $pagesFetched,
        array $meta = [],
    ): self {
        return new self(
            success: true,
            rating: $rating,
            reviewsCount: $reviewsCount,
            placeTitle: $placeTitle,
            pagesFetched: $pagesFetched,
            meta: $meta,
        );
    }

    public static function error(string $message, int $pagesFetched = 0, array $meta = []): self
    {
        return new self(
            success: false,
            pagesFetched: $pagesFetched,
            error: $message,
            meta: $meta,
        );
    }
}
