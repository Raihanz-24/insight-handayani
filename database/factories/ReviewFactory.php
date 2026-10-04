<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Place;
use App\Models\Review;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        $at = CarbonImmutable::parse(fake()->dateTimeBetween('-2 years', 'now'));
        $rating = fake()->numberBetween(1, 5);
        $snippet = fake()->sentence();

        return [
            'place_id' => Place::factory(),
            'review_id' => null,
            'review_key' => 'h:'.sha1(fake()->uuid().$snippet),
            'rating' => $rating,
            'rating_raw' => (float) $rating,
            'review_date' => $at->toDateString(),
            'reviewed_at' => $at,
            'author_name' => fake()->name(),
            'author_id' => (string) fake()->numberBetween(1000000, 9999999),
            'likes' => fake()->numberBetween(0, 20),
            'snippet' => $snippet,
            'captured_at' => now(),
            'meta' => null,
        ];
    }

    public function stars(int $rating): static
    {
        return $this->state(fn (): array => [
            'rating' => $rating,
            'rating_raw' => (float) $rating,
        ]);
    }

    public function onDate(string $date): static
    {
        return $this->state(fn (): array => [
            'review_date' => $date,
            'reviewed_at' => $date.' 12:00:00',
        ]);
    }
}
