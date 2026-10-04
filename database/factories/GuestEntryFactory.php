<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GuestEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuestEntry>
 */
class GuestEntryFactory extends Factory
{
    protected $model = GuestEntry::class;

    public function definition(): array
    {
        $start = CarbonImmutable::parse(fake()->unique()->dateTimeBetween('-2 years', 'now'))
            ->startOfWeek(CarbonImmutable::MONDAY)
            ->startOfDay();

        return [
            'week_start' => $start->toDateString(),
            'week_end' => $start->addDays(6)->toDateString(),
            'vehicles' => fake()->numberBetween(50, 1500),
            'note' => null,
            'entered_by' => null,
        ];
    }
}
