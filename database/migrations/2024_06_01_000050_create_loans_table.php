<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Peminjaman dokumen (Bab IV.5 / VII.2 dokumen perancangan): siapa saja
     * yang bisa melihat dokumennya (document.view) boleh mengajukan
     * peminjaman - tidak ada permission loan.create tersendiri di matriks
     * permission (RolePermissionSeeder), hanya loan.view/approve/reject/
     * return untuk Petugas Arsip yang memprosesnya. Data peminjam dicatat
     * langsung di baris ini (bukan tabel terpisah) karena peminjam bisa
     * pihak luar tanpa akun - identitasnya cukup nama/instansi/kontak.
     */
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();

            $table->string('borrower_name');
            $table->string('borrower_instansi')->nullable();
            $table->string('borrower_contact');
            $table->text('purpose');

            $table->string('status', 20)->default('pending');
            // status: pending | approved | rejected | returned

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('returned_at')->nullable();

            $table->timestamps();

            $table->index(['document_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
