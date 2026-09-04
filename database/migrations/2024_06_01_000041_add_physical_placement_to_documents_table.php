<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penempatan arsip fisik untuk dokumen (Bab IV.7): satu dokumen paling
     * banyak berada di satu lokasi fisik pada satu waktu. Dikelola oleh
     * Petugas Arsip lewat permission archive.* (terpisah dari document.*),
     * makanya kolomnya di sini, bukan tabel pivot terpisah - riwayat
     * perpindahan dianggap tidak kritis untuk versi ini.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('physical_location_id')->nullable()
                ->after('status')
                ->constrained('physical_locations')->nullOnDelete();
            $table->string('physical_code', 100)->nullable()->after('physical_location_id');
            $table->date('physical_stored_at')->nullable()->after('physical_code');

            $table->index('physical_location_id');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('physical_location_id');
            $table->dropColumn(['physical_code', 'physical_stored_at']);
        });
    }
};
