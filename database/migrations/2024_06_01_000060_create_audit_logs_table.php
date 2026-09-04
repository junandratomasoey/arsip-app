<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak audit untuk tindakan penting di aplikasi (Bab VII.6 dokumen
     * perancangan). subject_type/subject_id dibuat polymorphic manual
     * (bukan $table->morphs()) karena model yang diaudit ada yang
     * berprimary key bigint (Work internal - tidak, works pakai UUID -
     * lihat catatan di bawah) dan ada yang UUID, jadi kolom id disimpan
     * sebagai string agar menampung keduanya.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 100)->nullable();
            $table->string('subject_id', 40)->nullable();
            $table->string('description', 500);
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
