<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lokasi arsip fisik berbentuk pohon berjenjang tetap (Bab IV.7): gedung ->
 * lantai -> ruangan -> rak -> box -> folder. Jenjang di TYPE_ORDER wajib
 * berurutan; validasi ada di sini (bukan di form Livewire) supaya bisa
 * dipakai ulang oleh modul lain.
 */
class PhysicalLocation extends Model
{
    use HasFactory;

    public const TYPE_ORDER = ['gedung', 'lantai', 'ruangan', 'rak', 'box', 'folder'];

    public const TYPE_LABELS = [
        'gedung' => 'Gedung',
        'lantai' => 'Lantai',
        'ruangan' => 'Ruangan',
        'rak' => 'Rak',
        'box' => 'Box',
        'folder' => 'Folder',
    ];

    protected $fillable = [
        'parent_id',
        'type',
        'code',
        'name',
        'description',
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

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'physical_location_id');
    }

    /**
     * Tipe yang valid sebagai anak dari tipe ini (null = tidak boleh punya anak).
     */
    public static function nextType(?string $type): ?string
    {
        if ($type === null) {
            return self::TYPE_ORDER[0];
        }

        $index = array_search($type, self::TYPE_ORDER, true);

        return $index === false ? null : (self::TYPE_ORDER[$index + 1] ?? null);
    }

    /**
     * Breadcrumb dari root sampai lokasi ini, urutan gedung -> ... -> lokasi ini.
     *
     * @return array<int, self>
     */
    public function breadcrumb(): array
    {
        $path = [];
        $node = $this;

        while ($node !== null) {
            array_unshift($path, $node);
            $node = $node->parent;
        }

        return $path;
    }

    public function breadcrumbLabel(): string
    {
        return collect($this->breadcrumb())
            ->map(fn (self $node) => $node->name)
            ->implode(' / ');
    }
}
