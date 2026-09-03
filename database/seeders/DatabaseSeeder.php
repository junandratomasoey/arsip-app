<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            OrganizationalUnitSeeder::class,
            WorkTypeSeeder::class,
            PhaseSeeder::class,
            WorkStatusSeeder::class,
        ]);

        $admin = User::firstOrCreate(
            ['email' => 'admin@bbwsnt2.go.id'],
            ['name' => 'Super Admin', 'password' => bcrypt('password')]
        );
        $admin->assignRole('Super Admin');
    }
}
