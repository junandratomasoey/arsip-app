<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Struktur organisasi berbentuk pohon: organisasi -> bidang/bagian ->
 * satker -> PPK, seluruhnya baris pada tabel yang sama. Lihat Bab IV.1
 * dokumen perancangan. `type` umum: organisasi, bagian, bidang, satker, ppk.
 */
class OrganizationalUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'type',
        'code',
        'name',
        'is_active',
        'order_column',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order_column');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function worksAsSatker(): HasMany
    {
        return $this->hasMany(Work::class, 'satker_unit_id');
    }

    public function worksAsPpk(): HasMany
    {
        return $this->hasMany(Work::class, 'ppk_unit_id');
    }

    public function worksAsBidang(): HasMany
    {
        return $this->hasMany(Work::class, 'bidang_unit_id');
    }
}
