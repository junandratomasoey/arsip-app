<?php

namespace Database\Seeders;

use App\Models\Phase;
use Illuminate\Database\Seeder;

class PhaseSeeder extends Seeder
{
    public function run(): void
    {
        $phases = [
            ['code' => 'S', 'name' => 'Survey'],
            ['code' => 'I', 'name' => 'Identifikasi'],
            ['code' => 'D', 'name' => 'Desain'],
            ['code' => 'LA', 'name' => 'Land Acquisition'],
            ['code' => 'C', 'name' => 'Construction'],
            ['code' => 'O', 'name' => 'Operation'],
            ['code' => 'M', 'name' => 'Maintenance'],
            ['code' => 'OTH', 'name' => 'Lainnya'],
        ];

        foreach ($phases as $i => $phase) {
            Phase::create($phase + ['order_column' => $i]);
        }
    }
}
