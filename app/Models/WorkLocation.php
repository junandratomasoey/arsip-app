<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Titik koordinat spasial milik satu pekerjaan (PostGIS geometry(Point,4326)).
 * Lihat Bab IV.6 & Bab X (catatan revisi) dokumen perancangan: kolom
 * `geometry` adalah satu-satunya sumber kebenaran koordinat, TIDAK ada
 * kolom latitude/longitude terpisah yang bisa tidak sinkron.
 *
 * Kolom `geometry` sengaja TIDAK dimasukkan ke $fillable/$casts - nilainya
 * ditulis/dibaca lewat fungsi PostGIS (ST_MakePoint, ST_X, ST_Y) memakai
 * helper di bawah, supaya model ini tidak bergantung pada versi API paket
 * clickbar/laravel-magellan (belum bisa diuji saat kode ini ditulis, lihat
 * catatan di migration create_work_locations_table).
 *
 * Setelah composer install, silakan pertimbangkan migrasi ke cast
 * `\Clickbar\Magellan\Data\Geometries\Point::class` bila ingin memakai
 * objek Point langsung di kode aplikasi - cek dokumentasi paket tersebut
 * untuk API persis versi yang terpasang.
 */
class WorkLocation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['work_id', 'name', 'type', 'description'];

    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }

    /**
     * Buat titik lokasi baru dari lat/lng desimal (WGS84 / SRID 4326).
     */
    public static function createFromLatLng(array $attributes, float $lat, float $lng): self
    {
        // Kolom geometry NOT NULL tanpa default di DB, jadi tidak bisa lewat
        // $model->save() dulu baru UPDATE geometry belakangan (insert pertama
        // akan gagal karena geometry masih null) - geometry harus ikut di
        // dalam INSERT yang sama.
        $id = (string) \Illuminate\Support\Str::uuid();
        $now = now();

        DB::statement(
            'INSERT INTO work_locations (id, work_id, name, type, description, geometry, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326), ?, ?)',
            [
                $id,
                $attributes['work_id'],
                $attributes['name'],
                $attributes['type'],
                $attributes['description'] ?? null,
                $lng,
                $lat,
                $now,
                $now,
            ]
        );

        return self::findOrFail($id);
    }

    public function updateLatLng(float $lat, float $lng): void
    {
        DB::statement(
            'UPDATE work_locations SET geometry = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
            [$lng, $lat, $this->id]
        );
    }

    /**
     * Ambil lat/lng titik ini sebagai array ['lat' => ..., 'lng' => ...].
     */
    public function latLng(): ?array
    {
        $row = DB::selectOne(
            'SELECT ST_Y(geometry) AS lat, ST_X(geometry) AS lng FROM work_locations WHERE id = ?',
            [$this->id]
        );

        return $row ? ['lat' => (float) $row->lat, 'lng' => (float) $row->lng] : null;
    }

    /**
     * Scope untuk menyertakan kolom lat/lng turunan pada query list,
     * supaya tidak perlu query terpisah per baris (hindari N+1).
     */
    public function scopeWithLatLng(Builder $query): Builder
    {
        return $query->addSelect([
            '*',
            DB::raw('ST_Y(geometry) AS lat'),
            DB::raw('ST_X(geometry) AS lng'),
        ]);
    }

    /**
     * Scope untuk memfilter titik dalam radius tertentu (meter) dari
     * sebuah pusat - contoh pemanfaatan PostGIS yang tidak mungkin
     * dilakukan dengan pasangan lat/lng biasa. Lihat Bab III.2.
     */
    public function scopeWithinRadius(Builder $query, float $lat, float $lng, float $radiusMeters): Builder
    {
        return $query->whereRaw(
            'ST_DWithin(geometry::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)',
            [$lng, $lat, $radiusMeters]
        );
    }
}
