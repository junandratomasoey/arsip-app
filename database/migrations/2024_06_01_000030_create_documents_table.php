<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dokumen milik satu pekerjaan, dikelompokkan menurut fase. Satu
     * dokumen memiliki banyak file (lihat document_files) - bukan relasi
     * 1 dokumen = 1 file. Lihat Bab IV.3 dokumen perancangan.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('work_id')->constrained('works')->cascadeOnDelete();
            $table->foreignId('phase_id')->nullable()->constrained('phases')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();

            $table->string('visibility', 20)->default('internal');
            // visibility: public | internal | restricted | confidential
            // lihat Bab V.7 dokumen perancangan (disarankan diselaraskan
            // dengan klasifikasi UU Keterbukaan Informasi Publik)

            $table->string('status', 20)->default('active');
            // status: active | archived | deleted (soft delete bertingkat,
            // lihat Bab VII.5)

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['work_id', 'phase_id']);
            $table->index('visibility');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
