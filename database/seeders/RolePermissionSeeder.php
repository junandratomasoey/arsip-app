<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Peran dan matriks permission sesuai Bab VII.1 & VII.2 dokumen
 * perancangan. Permission mengatur JENIS TINDAKAN yang boleh dilakukan;
 * pembatasan BARIS DATA (mis. Admin Unit hanya unitnya sendiri) diterapkan
 * lewat scope/policy di kode aplikasi, bukan lewat permission tambahan -
 * lihat Bab VII.3.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'work.view', 'work.create', 'work.update', 'work.delete',
            'document.view', 'document.create', 'document.update', 'document.delete', 'document.download',
            'loan.view', 'loan.approve', 'loan.reject', 'loan.return',
            'archive.view', 'archive.create', 'archive.update',
            'user.manage', 'organization.manage', 'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $superAdmin = Role::findOrCreate('Super Admin', 'web');
        $superAdmin->syncPermissions($permissions);

        Role::findOrCreate('Admin Arsip', 'web')->syncPermissions([
            'work.view', 'work.create', 'work.update', 'work.delete',
            'document.view', 'document.create', 'document.update', 'document.delete', 'document.download',
        ]);

        // Admin Unit: permission sama dengan Admin Arsip; pembatasan pada
        // unit kerjanya sendiri diterapkan lewat scope, bukan permission.
        Role::findOrCreate('Admin Unit', 'web')->syncPermissions([
            'work.view', 'work.create', 'work.update',
            'document.view', 'document.create', 'document.update', 'document.download',
        ]);

        Role::findOrCreate('Petugas Arsip', 'web')->syncPermissions([
            'archive.view', 'archive.create', 'archive.update',
            'loan.view', 'loan.approve', 'loan.reject', 'loan.return',
            'document.view', 'document.download',
        ]);

        Role::findOrCreate('Viewer Internal', 'web')->syncPermissions([
            'work.view', 'document.view',
        ]);

        // "Public" sengaja tidak dibuat sebagai role - pengunjung yang
        // belum login mengakses Public Digital Library tanpa autentikasi,
        // dibatasi lewat dokumen berstatus visibility=public.
    }
}
