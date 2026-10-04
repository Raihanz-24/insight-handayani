<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Place>
 */
class PlaceFactory extends Factory
{
    protected $model = Place::class;

    public function definition(): array
    {
        return [
            'name' => 'Tempat '.fake()->unique()->company(),
            'type' => fake()->randomElement([Place::TYPE_RESTAURANT, Place::TYPE_COTTAGE]),
            'serpapi_data_id' => null,
            'serpapi_place_id' => null,
            'query' => null,
            'is_active' => true,
            'note' => null,
        ];
    }

    public function restaurant(): static
    {
        return $this->state(fn (): array => ['type' => Place::TYPE_RESTAURANT]);
    }

    public function cottage(): static
    {
        return $this->state(fn (): array => ['type' => Place::TYPE_COTTAGE]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function fetchable(): static
    {
        return $this->state(fn (): array => [
            'serpapi_data_id' => fake()->regexify('[A-Za-z0-9:]{18}'),
        ]);
    }
}
