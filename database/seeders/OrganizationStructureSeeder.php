<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\WorkUnit;
use Illuminate\Database\Seeder;

class OrganizationStructureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $office = Office::updateOrCreate(
            ['code' => 'GENERAL'],
            [
                'name' => 'Unidades de oficina',
                'address' => null,
                'is_active' => true,
            ],
        );

        WorkUnit::updateOrCreate(
            [
                'office_id' => $office->id,
                'code' => 'UN-MDTEC',
            ],
            [
                'name' => 'UNIDAD DE MODERNIZACION TECNOLOGICA',
                'description' => null,
                'is_active' => true,
            ],
        );
    }
}
