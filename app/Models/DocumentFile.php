<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Slot" file logis di dalam sebuah dokumen (mis. "DED", "Kontrak").
 * Riwayat versinya ada di DocumentFileVersion - lihat Bab IV.10.
 */
class DocumentFile extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['document_id', 'label', 'current_version_id'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentFileVersion::class)->orderByDesc('version_number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentFileVersion::class, 'current_version_id');
    }

    /**
     * Unggah versi baru dan jadikan versi aktif. Tidak pernah menimpa file
     * lama - setiap pemanggilan menambah baris baru di document_file_versions.
     */
    public function addVersion(array $attributes): DocumentFileVersion
    {
        $nextVersion = ((int) $this->versions()->max('version_number')) + 1;

        $version = $this->versions()->create(array_merge($attributes, [
            'version_number' => $nextVersion,
        ]));

        $this->update(['current_version_id' => $version->id]);

        return $version;
    }
}
