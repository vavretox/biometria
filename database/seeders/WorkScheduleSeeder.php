<?php

namespace Database\Seeders;

use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

class WorkScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schedules = [
            'HORARIO CONTINUO' => [
                'starts_at' => '08:00',
                'ends_at' => '16:00',
                'second_starts_at' => null,
                'second_ends_at' => null,
            ],
            'HORARIO DISCONTINUO' => [
                'starts_at' => '08:00',
                'ends_at' => '12:00',
                'second_starts_at' => '15:00',
                'second_ends_at' => '19:00',
            ],
            'HORARIO LACTANCIA' => [
                'starts_at' => '08:30',
                'ends_at' => '12:00',
                'second_starts_at' => '15:00',
                'second_ends_at' => '19:00',
            ],
            'HORARIO DOCENCIA' => [
                'starts_at' => '08:00',
                'ends_at' => '12:00',
                'second_starts_at' => '15:00',
                'second_ends_at' => '18:30',
            ],
        ];

        foreach ($schedules as $name => $times) {
            $schedule = WorkSchedule::updateOrCreate(
                ['name' => $name],
                [
                    'description' => null,
                    'is_default' => false,
                    'is_active' => true,
                ],
            );

            foreach (range(1, 5) as $dayOfWeek) {
                $schedule->days()->updateOrCreate(
                    ['day_of_week' => $dayOfWeek],
                    [
                        ...$times,
                        'grace_minutes' => 5,
                        'is_working_day' => true,
                    ],
                );
            }
        }
    }
}
