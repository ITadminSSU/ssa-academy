<?php

namespace Database\Seeders;

use App\Models\ProfessionalType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProfessionalTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Architect', 'sort_order' => 1],
            ['name' => 'Civil Engineer', 'sort_order' => 2],
            ['name' => 'Construction Estimating', 'sort_order' => 3],
            ['name' => 'Other', 'sort_order' => 4],
        ];

        foreach ($types as $type) {
            ProfessionalType::create($type);
        }
    }
}
