<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laporan & Revisi sebagai perluasan modul Dokumen:
     * - documents.category membedakan dokumen umum dan laporan;
     *   report_type = jenis laporan (pendahuluan, antara, akhir, dst).
     * - Setiap versi berkas laporan punya status review sendiri, sehingga
     *   revisi ke-n tetap tercatat lengkap dengan catatan reviewernya.
     * - document_reviews = jejak semua tindakan review (diajukan, minta
     *   revisi, disetujui) untuk ditampilkan sebagai riwayat revisi.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('category', 20)->default('umum')->after('description');
            $table->string('report_type', 30)->nullable()->after('category');
            $table->index('category');
        });

        Schema::table('document_file_versions', function (Blueprint $table) {
            $table->string('review_status', 20)->nullable()->after('notes');
            // review_status (hanya untuk laporan): draf | menunggu_review | revisi | disetujui
            $table->timestamp('submitted_at')->nullable()->after('review_status');
            $table->foreignId('reviewed_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');
            $table->index('review_status');
        });

        Schema::create('document_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('document_file_version_id')->constrained('document_file_versions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20);
            // action: diajukan | revisi | disetujui
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_reviews');

        Schema::table('document_file_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropIndex(['review_status']);
            $table->dropColumn(['review_status', 'submitted_at', 'reviewed_at', 'review_notes']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn(['category', 'report_type']);
        });
    }
};
