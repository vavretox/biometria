<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $leaveTypes = [
            ['name' => 'Vacación', 'code' => 'vacation', 'abbreviation' => 'VL', 'color' => '#2563EB'],
            ['name' => 'Baja médica', 'code' => 'medical', 'abbreviation' => 'BM', 'color' => '#DC2626'],
            ['name' => 'Asunto personal', 'code' => 'personal', 'abbreviation' => 'AP', 'color' => '#7C3AED'],
            ['name' => 'Comisión', 'code' => 'commission', 'abbreviation' => 'CO', 'color' => '#059669'],
            ['name' => 'Otro', 'code' => 'other', 'abbreviation' => 'OT', 'color' => '#D97706'],
            ['name' => 'Maternidad', 'code' => 'maternidad', 'abbreviation' => 'MA', 'color' => '#0891B2'],
        ];

        foreach ($leaveTypes as $leaveType) {
            LeaveType::updateOrCreate(
                ['code' => $leaveType['code']],
                [...$leaveType, 'is_active' => true],
            );
        }
    }
}
