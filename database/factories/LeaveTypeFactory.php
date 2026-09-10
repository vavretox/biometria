<?php

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'code' => Str::slug($name, '_'),
            'abbreviation' => Str::upper(fake()->unique()->lexify('???')),
            'color' => fake()->randomElement(LeaveType::COLOR_PALETTE),
            'is_active' => true,
        ];
    }
}
