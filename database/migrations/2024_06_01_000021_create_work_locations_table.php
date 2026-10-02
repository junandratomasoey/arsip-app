<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Titik koordinat milik satu pekerjaan (bendungan, intake, outlet, camp,
     * akses, dst). Satu pekerjaan bisa punya banyak titik.
     *
     * VERSI MYSQL / SHARED HOSTING: koordinat disimpan sebagai pasangan
     * latitude/longitude desimal (WGS84), bukan kolom geometry PostGIS.
     * Tipe ini jalan sama di MySQL 5.7/8 maupun MariaDB tanpa ekstensi
     * spasial apa pun, sesuatu yang tidak bisa dijamin di shared hosting.
     * Presisi 7 digit desimal = ~1 cm, lebih dari cukup untuk titik lapangan.
     * Query radius dihitung dengan rumus Haversine di model WorkLocation.
     */
    public function up(): void
    {
        Schema::create('work_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('work_id')->constrained('works')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 30)->nullable();
            // type umum: bendungan, intake, outlet, camp, akses, lainnya
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamps();

            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_locations');
    }
};
