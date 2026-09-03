<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unit kerja tempat pengguna berada - dipakai untuk pembatasan data
     * (data scoping) peran Admin Unit. Lihat Bab VII.3 dokumen perancangan:
     * ini BUKAN permission, melainkan filter baris data berdasarkan unit.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organizational_unit_id')->nullable()->after('id')
                ->constrained('organizational_units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organizational_unit_id');
        });
    }
};
