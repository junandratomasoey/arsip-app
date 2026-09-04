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

    protected $fillable = [
        'work_id', 'phase_id', 'title', 'description', 'visibility', 'status', 'created_by',
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
}
