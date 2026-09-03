<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Titik koordinat spasial milik satu pekerjaan (bendungan, intake,
     * outlet, camp, akses, dst). Satu pekerjaan bisa punya banyak titik.
     *
     * REVISI dari rancangan awal (lihat Bab IV.6 & Bab X dokumen
     * perancangan): kolom `geometry` (PostGIS) adalah SATU-SATUNYA sumber
     * kebenaran koordinat - TIDAK ada kolom latitude/longitude terpisah.
     * Untuk kebutuhan tampilan, turunkan nilainya saat query, mis.:
     *   DB::selectOne("select ST_Y(geometry) as lat, ST_X(geometry) as lng
     *                  from work_locations where id = ?", [$id]);
     *
     * Kolom geometry ditulis lewat SQL mentah (bukan lewat Blueprint macro
     * dari paket clickbar/laravel-magellan) supaya migration ini tidak
     * bergantung pada versi API paket tersebut, yang belum bisa diuji di
     * lingkungan penulisan kode ini (composer install belum berjalan).
     * Model Eloquent `WorkLocation` tetap memakai cast dari paket tersebut
     * untuk kemudahan pemakaian di kode aplikasi - lihat catatan di model.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');

        Schema::create('work_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('work_id')->constrained('works')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 30)->nullable();
            // type umum: bendungan, intake, outlet, camp, akses, lainnya
            $table->text('description')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE work_locations ADD COLUMN geometry geometry(Point,4326) NOT NULL');
        DB::statement('CREATE INDEX work_locations_geometry_gist ON work_locations USING GIST (geometry)');
    }

    public function down(): void
    {
        Schema::dropIfExists('work_locations');
    }
};
