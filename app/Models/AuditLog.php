<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak audit tindakan penting (Bab VII.6 dokumen perancangan). Dicatat
 * lewat AuditLog::record() dari Livewire action yang relevan - bukan
 * observer otomatis pada semua model, supaya deskripsi yang tersimpan
 * tetap ramah dibaca manusia dan hanya mencatat tindakan yang memang
 * signifikan (bukan setiap perubahan field).
 */
class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'subject_type', 'subject_id', 'description', 'properties', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Catat satu entri audit log. $subject boleh model apa pun (UUID
     * maupun bigint) atau null untuk tindakan tanpa subjek tunggal
     * (mis. login/logout).
     */
    public static function record(string $action, ?Model $subject, string $description, array $properties = []): self
    {
        return self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject ? (string) $subject->getKey() : null,
            'description' => $description,
            'properties' => $properties !== [] ? $properties : null,
            'ip_address' => request()?->ip(),
        ]);
    }

    public function subjectLabel(): string
    {
        return match ($this->subject_type) {
            \App\Models\Work::class => 'Pekerjaan',
            \App\Models\Document::class => 'Dokumen',
            \App\Models\PhysicalLocation::class => 'Lokasi Fisik',
            \App\Models\Loan::class => 'Peminjaman',
            default => '-',
        };
    }
}
