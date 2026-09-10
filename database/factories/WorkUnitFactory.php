<?php

namespace Database\Factories;

use App\Models\Office;
use App\Models\WorkUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkUnit>
 */
class WorkUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'office_id' => Office::factory(),
            'name' => fake()->unique()->words(3, true),
            'code' => fake()->unique()->bothify('UN-###'),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
