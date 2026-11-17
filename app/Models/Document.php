<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dokumen milik satu pekerjaan. Lihat Bab IV.3 dokumen perancangan.
 * Visibilitas per Bab V.7: public | internal | restricted | confidential.
 */
class Document extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_INTERNAL = 'internal';

    public const VISIBILITY_RESTRICTED = 'restricted';

    public const VISIBILITY_CONFIDENTIAL = 'confidential';

    public const CATEGORY_GENERAL = 'umum';

    public const CATEGORY_REPORT = 'laporan';

    public const REPORT_TYPES = [
        'rmk' => 'RMK / RMPK',
        'pendahuluan' => 'Laporan Pendahuluan',
        'antara' => 'Laporan Antara',
        'akhir' => 'Laporan Akhir',
        'bulanan' => 'Laporan Bulanan',
        'mingguan' => 'Laporan Mingguan',
        'harian' => 'Laporan Harian',
        'lainnya' => 'Laporan Lainnya',
    ];

    public function isReport(): bool
    {
        return $this->category === self::CATEGORY_REPORT;
    }

    public function reportTypeLabel(): ?string
    {
        return $this->report_type ? (self::REPORT_TYPES[$this->report_type] ?? $this->report_type) : null;
    }

    protected $fillable = [
        'work_id', 'phase_id', 'title', 'description', 'category', 'report_type', 'visibility', 'status', 'created_by',
        'physical_location_id', 'physical_code', 'physical_stored_at',
    ];

    protected function casts(): array
    {
        return [
            'physical_stored_at' => 'date',
        ];
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DocumentFile::class);
    }

    public function physicalLocation(): BelongsTo
    {
        return $this->belongsTo(PhysicalLocation::class, 'physical_location_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function scopePublic($query)
    {
        return $query->where('visibility', self::VISIBILITY_PUBLIC);
    }

    /*
     * Kelengkapan arsip per dokumen:
     *  - softfile = minimal satu slot berkas yang sudah ada unggahannya;
     *  - hardcopy = dokumen sudah ditempatkan di lokasi Arsip Fisik.
     * Keduanya diturunkan dari data yang sudah ada (tidak diisi manual),
     * supaya angkanya selalu mengikuti kondisi nyata.
     */
    public function scopeWithSoftfile($query)
    {
        return $query->whereHas('files', fn ($f) => $f->whereNotNull('current_version_id'));
    }

    public function scopeWithoutSoftfile($query)
    {
        return $query->whereDoesntHave('files', fn ($f) => $f->whereNotNull('current_version_id'));
    }

    public function scopeWithHardcopy($query)
    {
        return $query->whereNotNull('physical_location_id');
    }

    public function scopeWithoutHardcopy($query)
    {
        return $query->whereNull('physical_location_id');
    }
}
