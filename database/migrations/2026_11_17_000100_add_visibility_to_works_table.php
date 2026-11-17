<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Visibilitas pekerjaan: menentukan apakah pekerjaan tampil di
     * Perpustakaan Digital Publik (/pustaka). Default "internal" supaya
     * pekerjaan baru tidak pernah terbuka ke umum tanpa disengaja.
     *
     * Pekerjaan yang saat migrasi ini dijalankan sudah punya dokumen
     * publik langsung dijadikan "public", supaya dokumen yang sebelumnya
     * sudah tampil di /pustaka tidak tiba-tiba hilang.
     */
    public function up(): void
    {
        Schema::table('works', function (Blueprint $table) {
            $table->string('visibility', 20)->default('internal')->after('cover_path')->index();
        });

        $workIds = DB::table('documents')
            ->where('visibility', 'public')
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('work_id');

        if ($workIds->isNotEmpty()) {
            DB::table('works')->whereIn('id', $workIds)->update(['visibility' => 'public']);
        }
    }

    public function down(): void
    {
        Schema::table('works', function (Blueprint $table) {
            $table->dropIndex(['visibility']);
            $table->dropColumn('visibility');
        });
    }
};
