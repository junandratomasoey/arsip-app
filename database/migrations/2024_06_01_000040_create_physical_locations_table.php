<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lokasi arsip fisik berbentuk pohon berjenjang tetap: gedung > lantai
     * > ruangan > rak > box > folder (lihat Bab IV.7 dokumen perancangan).
     * Berbeda dari organizational_units, jenjang di sini wajib berurutan -
     * divalidasi di kode aplikasi (WorkLocation-nya arsip fisik), bukan di
     * migration ini, supaya pesan errornya bisa berbahasa Indonesia yang
     * jelas untuk Petugas Arsip.
     */
    public function up(): void
    {
        Schema::create('physical_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()
                ->constrained('physical_locations')->nullOnDelete();
            $table->string('type', 20);
            // type (berurutan, wajib sesuai jenjang parent-nya):
            // gedung -> lantai -> ruangan -> rak -> box -> folder
            $table->string('code', 50)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('order_column')->default(0);
            $table->timestamps();

            $table->index(['parent_id', 'order_column']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_locations');
    }
};
