<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu versi unggahan dari sebuah DocumentFile, lengkap dengan checksum
 * SHA-256 untuk mendeteksi berkas yang berubah/rusak. Lihat Bab IV.10.
 */
class DocumentFileVersion extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'document_file_id', 'version_number', 'disk', 'path',
        'original_filename', 'mime_type', 'size_bytes', 'checksum_sha256',
        'notes', 'uploaded_by',
    ];

    public function documentFile(): BelongsTo
    {
        return $this->belongsTo(DocumentFile::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
