<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Office;
use App\Models\WorkUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
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
            'work_unit_id' => fn (array $attributes) => WorkUnit::factory()->create(['office_id' => $attributes['office_id']])->id,
            'employee_code' => fake()->unique()->numerify('EMP-####'),
            'item_number' => fake()->unique()->numerify('ITEM-####'),
            'employee_type' => fake()->randomElement(['permanent', 'contract', 'consultant', 'temporary']),
            'budget_line' => fake()->numerify('#####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'maternal_last_name' => fake()->lastName(),
            'identity_document' => fake()->unique()->numerify('V########'),
            'identity_issued_in' => fake()->randomElement(['LP', 'SC', 'CB', 'TJ']),
            'has_children' => fake()->boolean(),
            'address' => fake()->address(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'mobile' => fake()->phoneNumber(),
            'position' => fake()->jobTitle(),
            'job_description' => fake()->sentence(),
            'employment_observations' => fake()->optional()->sentence(),
            'memo_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'memo_number' => fake()->bothify('MEM-####'),
            'department' => fake()->randomElement(['Administración', 'Operaciones', 'Tecnología']),
            'biometric_id' => fake()->unique()->numberBetween(1, 99999),
            'attendance_method' => 'biometric',
            'access_code' => null,
            'hire_date' => fake()->dateTimeBetween('-5 years', 'now'),
            'employment_end_date' => null,
            'birth_date' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'marital_status' => fake()->randomElement(['single', 'married', 'divorced', 'widowed']),
            'gender' => fake()->randomElement(['female', 'male', 'other']),
            'is_active' => true,
        ];
    }
}
