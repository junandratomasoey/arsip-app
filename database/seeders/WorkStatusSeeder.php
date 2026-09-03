<?php

namespace Database\Seeders;

use App\Models\WorkStatus;
use Illuminate\Database\Seeder;

class WorkStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            'Perencanaan', 'Survey', 'Identifikasi', 'Desain', 'Tender',
            'Kontrak', 'Pelaksanaan', 'Selesai', 'Operasi', 'Pemeliharaan', 'Arsip',
        ];

        foreach ($statuses as $i => $name) {
            WorkStatus::create([
                'code' => \Illuminate\Support\Str::slug($name, '_'),
                'name' => $name,
                'order_column' => $i,
            ]);
        }
    }
}
