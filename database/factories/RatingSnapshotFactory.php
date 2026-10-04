<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Place;
use App\Models\RatingSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RatingSnapshot>
 */
class RatingSnapshotFactory extends Factory
{
    protected $model = RatingSnapshot::class;

    public function definition(): array
    {
        $at = CarbonImmutable::now();

        return [
            'place_id' => Place::factory(),
            'captured_at' => $at,
            'captured_date' => $at->toDateString(),
            'rating' => fake()->randomFloat(2, 3, 5),
            'reviews_count' => fake()->numberBetween(20, 2000),
            'source' => RatingSnapshot::SOURCE_SERPAPI,
            'status' => RatingSnapshot::STATUS_OK,
            'error_message' => null,
            'meta' => null,
        ];
    }

    public function error(): static
    {
        return $this->state(fn (): array => [
            'rating' => null,
            'reviews_count' => null,
            'status' => RatingSnapshot::STATUS_ERROR,
            'error_message' => 'Gagal mengambil data.',
        ]);
    }
}
