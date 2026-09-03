<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Setiap unggahan/revisi sebuah document_file disimpan sebagai baris
     * baru di sini (tidak menimpa file lama) - lihat Bab IV.10 dokumen
     * perancangan. checksum_sha256 dipakai untuk mendeteksi berkas yang
     * berubah/rusak.
     */
    public function up(): void
    {
        Schema::create('document_file_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_file_id')->constrained('document_files')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('original_filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('checksum_sha256', 64)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['document_file_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_file_versions');
    }
};
