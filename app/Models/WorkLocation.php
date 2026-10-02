<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Titik koordinat milik satu pekerjaan (WGS84, lat/lng desimal).
 *
 * VERSI MYSQL / SHARED HOSTING: dulu memakai kolom PostGIS geometry,
 * sekarang kolom latitude/longitude biasa supaya jalan di MySQL & MariaDB
 * tanpa ekstensi spasial. Helper lama (createFromLatLng, updateLatLng,
 * latLng, withLatLng, withinRadius) dipertahankan dengan nama & perilaku
 * sama supaya kode pemanggil (halaman Titik Lokasi GPS) tidak perlu diubah.
 */
class WorkLocation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['work_id', 'name', 'type', 'description', 'latitude', 'longitude'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }

    public static function createFromLatLng(array $attributes, float $lat, float $lng): self
    {
        return self::create(array_merge($attributes, [
            'latitude' => $lat,
            'longitude' => $lng,
        ]));
    }

    public function updateLatLng(float $lat, float $lng): void
    {
        $this->update(['latitude' => $lat, 'longitude' => $lng]);
    }

    public function latLng(): ?array
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        return ['lat' => (float) $this->latitude, 'lng' => (float) $this->longitude];
    }

    /**
     * Menyertakan alias `lat`/`lng` pada hasil query, sama seperti versi
     * PostGIS dulu, supaya tampilan yang membaca $row->lat/$row->lng tetap jalan.
     */
    public function scopeWithLatLng(Builder $query): Builder
    {
        return $query->addSelect([
            'work_locations.*',
            'work_locations.latitude as lat',
            'work_locations.longitude as lng',
        ]);
    }

    /**
     * Memfilter titik dalam radius tertentu (meter) dari sebuah pusat,
     * memakai rumus Haversine (radius bumi 6.371.000 m). Kotak pembatas
     * lat/lng di depan memanfaatkan index supaya tidak menghitung semua baris.
     */
    public function scopeWithinRadius(Builder $query, float $lat, float $lng, float $radiusMeters): Builder
    {
        $latDelta = rad2deg($radiusMeters / 6371000);
        $lngDelta = rad2deg($radiusMeters / (6371000 * max(cos(deg2rad($lat)), 0.000001)));

        return $query
            ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta])
            ->whereRaw(
                '(6371000 * 2 * ASIN(SQRT(POWER(SIN(RADIANS(latitude - ?) / 2), 2) + COS(RADIANS(?)) * COS(RADIANS(latitude)) * POWER(SIN(RADIANS(longitude - ?) / 2), 2)))) <= ?',
                [$lat, $lat, $lng, $radiusMeters]
            );
    }
}
