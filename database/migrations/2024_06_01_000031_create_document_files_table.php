<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Slot" file logis di dalam sebuah dokumen, mis. "DED", "Kontrak".
     * Riwayat setiap unggahan/perubahan tersimpan di document_file_versions
     * (lihat migration berikutnya) - file ini sendiri tidak menyimpan path
     * fisik, hanya menunjuk ke versi yang sedang aktif.
     */
    public function up(): void
    {
        Schema::create('document_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('label');
            // current_version_id ditambahkan di migration berikutnya
            // (setelah tabel document_file_versions ada) untuk menghindari
            // dependensi melingkar antar migration.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_files');
    }
};
