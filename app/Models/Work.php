<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Data paket pekerjaan. Lihat Bab IV.4 dokumen perancangan.
 * Primary key UUID (bukan auto-increment) karena akan diekspos lewat URL
 * publik pada Public Digital Library - lihat Bab III.2 & VII.1.
 */
class Work extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'package_name', 'cover_path', 'work_type_id', 'fiscal_year', 'funding_source',
        'province', 'regency', 'district', 'village', 'location_description',
        'contract_number', 'contract_date', 'contract_value',
        'spmk_number', 'spmk_date', 'bast_number', 'bast_date',
        'start_date', 'end_date',
        'provider_name', 'provider_address', 'provider_npwp', 'provider_leader_name', 'provider_role',
        'satker_unit_id', 'ppk_unit_id', 'bidang_unit_id', 'person_in_charge_id', 'work_status_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'contract_date' => 'date',
            'spmk_date' => 'date',
            'bast_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'contract_value' => 'decimal:2',
        ];
    }

    public function workType(): BelongsTo
    {
        return $this->belongsTo(WorkType::class);
    }

    public function workStatus(): BelongsTo
    {
        return $this->belongsTo(WorkStatus::class);
    }

    public function satkerUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'satker_unit_id');
    }

    public function ppkUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'ppk_unit_id');
    }

    public function bidangUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'bidang_unit_id');
    }

    public function personInCharge(): BelongsTo
    {
        return $this->belongsTo(User::class, 'person_in_charge_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(WorkLocation::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /**
     * URL publik cover/sampul, atau null kalau belum ada - dipakai di
     * kartu & detail Perpustakaan Digital Publik.
     */
    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }
}
