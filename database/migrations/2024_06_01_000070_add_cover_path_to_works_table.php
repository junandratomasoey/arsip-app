<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cover/sampul pekerjaan, ditampilkan di kartu & detail dokumen pada
     * Perpustakaan Digital Publik. Disimpan di disk "public" (bukan disk
     * dokumen yang privat) karena gambar sampul memang dimaksudkan untuk
     * tampil langsung ke pengunjung tanpa login.
     */
    public function up(): void
    {
        Schema::table('works', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('package_name');
        });
    }

    public function down(): void
    {
        Schema::table('works', function (Blueprint $table) {
            $table->dropColumn('cover_path');
        });
    }
};
