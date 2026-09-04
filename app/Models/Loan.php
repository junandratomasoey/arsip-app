<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Peminjaman dokumen. Lihat catatan di migration create_loans_table.
 */
class Loan extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Menunggu Persetujuan',
        self::STATUS_APPROVED => 'Disetujui',
        self::STATUS_REJECTED => 'Ditolak',
        self::STATUS_RETURNED => 'Sudah Dikembalikan',
    ];

    protected $fillable = [
        'document_id',
        'borrower_name',
        'borrower_instansi',
        'borrower_contact',
        'purpose',
        'status',
        'requested_by',
        'processed_by',
        'processed_at',
        'rejection_reason',
        'due_date',
        'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'due_date' => 'date',
            'returned_at' => 'datetime',
        ];
    }

    /**
     * withTrashed() supaya riwayat peminjaman tetap bisa ditampilkan
     * (mis. di antrian admin/loans) walau dokumennya sudah dihapus
     * (soft delete) belakangan - tanpa ini $loan->document jadi null dan
     * merusak tampilan yang mengasumsikan dokumen selalu ada.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED], true);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
