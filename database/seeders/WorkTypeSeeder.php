<?php

namespace Database\Seeders;

use App\Models\WorkType;
use Illuminate\Database\Seeder;

class WorkTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Bendungan', 'Embung', 'Irigasi', 'Sungai', 'Tanggul', 'Jembatan', 'Lainnya',
        ];

        foreach ($types as $i => $name) {
            WorkType::create([
                'code' => \Illuminate\Support\Str::slug($name, '_'),
                'name' => $name,
                'order_column' => $i,
            ]);
        }
    }
}
