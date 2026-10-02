<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu versi unggahan dari sebuah DocumentFile, lengkap dengan checksum
 * SHA-256 untuk mendeteksi berkas yang berubah/rusak. Lihat Bab IV.10.
 */
class DocumentFileVersion extends Model
{
    use HasFactory, HasUuids;

    // Status review - hanya terisi untuk versi berkas pada dokumen kategori laporan.
    public const REVIEW_DRAFT = 'draf';
    public const REVIEW_PENDING = 'menunggu_review';
    public const REVIEW_REVISION = 'revisi';
    public const REVIEW_APPROVED = 'disetujui';

    public const REVIEW_LABELS = [
        self::REVIEW_DRAFT => 'Draf',
        self::REVIEW_PENDING => 'Menunggu Review',
        self::REVIEW_REVISION => 'Perlu Revisi',
        self::REVIEW_APPROVED => 'Disetujui',
    ];

    protected $fillable = [
        'document_file_id', 'version_number', 'disk', 'path',
        'original_filename', 'mime_type', 'size_bytes', 'checksum_sha256',
        'notes', 'uploaded_by',
        'review_status', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function documentFile(): BelongsTo
    {
        return $this->belongsTo(DocumentFile::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(DocumentReview::class)->orderBy('created_at');
    }

    public function reviewStatusLabel(): ?string
    {
        return $this->review_status ? (self::REVIEW_LABELS[$this->review_status] ?? $this->review_status) : null;
    }

    public function submitForReview(?int $userId): void
    {
        $this->update(['review_status' => self::REVIEW_PENDING, 'submitted_at' => now()]);
        $this->reviews()->create(['user_id' => $userId, 'action' => DocumentReview::ACTION_SUBMITTED]);
    }

    public function recordReview(string $decision, ?int $userId, ?string $notes): void
    {
        $this->update([
            'review_status' => $decision === DocumentReview::ACTION_APPROVED ? self::REVIEW_APPROVED : self::REVIEW_REVISION,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        $this->reviews()->create(['user_id' => $userId, 'action' => $decision, 'notes' => $notes]);
    }
}
