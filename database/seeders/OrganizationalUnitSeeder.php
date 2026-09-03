<?php

namespace Database\Seeders;

use App\Models\OrganizationalUnit;
use Illuminate\Database\Seeder;

/**
 * Contoh struktur organisasi awal, persis seperti pada Bab IV.1 dokumen
 * perancangan. Ini hanyalah DATA AWAL - admin bisa mengubah, menambah,
 * atau menghapus lewat CRUD Struktur Organisasi tanpa menyentuh kode.
 */
class OrganizationalUnitSeeder extends Seeder
{
    public function run(): void
    {
        $root = OrganizationalUnit::create([
            'type' => 'organisasi',
            'name' => 'BBWS Nusa Tenggara II',
            'code' => 'BBWSNT2',
            'order_column' => 0,
        ]);

        $bagianUmum = OrganizationalUnit::create([
            'parent_id' => $root->id, 'type' => 'bagian',
            'name' => 'Bagian Umum dan Tata Usaha', 'order_column' => 1,
        ]);
        $bidangKpiSda = OrganizationalUnit::create([
            'parent_id' => $root->id, 'type' => 'bidang',
            'name' => 'Bidang KPI SDA', 'order_column' => 2,
        ]);
        $bidangPelaksanaan = OrganizationalUnit::create([
            'parent_id' => $root->id, 'type' => 'bidang',
            'name' => 'Bidang Pelaksanaan', 'order_column' => 3,
        ]);
        $bidangOp = OrganizationalUnit::create([
            'parent_id' => $root->id, 'type' => 'bidang',
            'name' => 'Bidang OP', 'order_column' => 4,
        ]);

        $satkerInduk = OrganizationalUnit::create([
            'parent_id' => $root->id, 'type' => 'satker',
            'name' => 'Satker BBWS Nusa Tenggara II', 'order_column' => 5,
        ]);
        foreach ([
            'PPK Tatalaksana',
            'PPK Perencanaan dan Program',
            'PPK Penatagunaan PSDA',
            'PPK BMN',
        ] as $i => $name) {
            OrganizationalUnit::create([
                'parent_id' => $satkerInduk->id, 'type' => 'ppk',
                'name' => $name, 'order_column' => $i,
            ]);
        }

        $satkerOp = OrganizationalUnit::create([
            'parent_id' => $root->id, 'type' => 'satker',
            'name' => 'Satker Operasi dan Pemeliharaan', 'order_column' => 6,
        ]);
        foreach (['PPK OP I', 'PPK OP II', 'PPK OP III', 'PPK OP IV', 'PPK OP V'] as $i => $name) {
            OrganizationalUnit::create([
                'parent_id' => $satkerOp->id, 'type' => 'ppk',
                'name' => $name, 'order_column' => $i,
            ]);
        }
    }
}
