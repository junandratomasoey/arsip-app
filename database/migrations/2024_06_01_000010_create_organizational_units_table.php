<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Struktur organisasi berbentuk pohon (parent-child), CRUD penuh oleh
     * admin. Satker dan PPK direpresentasikan sebagai baris pada tabel yang
     * sama dengan kolom `type`, bukan tabel terpisah, supaya perubahan
     * struktur organisasi di masa depan tidak memerlukan migrasi baru.
     * Lihat Bab IV.1 dan catatan 4.11 pada dokumen perancangan.
     */
    public function up(): void
    {
        Schema::create('organizational_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()
                ->constrained('organizational_units')->nullOnDelete();
            $table->string('type', 30)->default('unit');
            // type umum: organisasi, bagian, bidang, satker, ppk, lainnya
            $table->string('code', 50)->nullable();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('order_column')->default(0);
            $table->timestamps();

            $table->index(['parent_id', 'order_column']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizational_units');
    }
};
