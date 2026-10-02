<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentReview extends Model
{
    public const ACTION_SUBMITTED = 'diajukan';
    public const ACTION_REVISION = 'revisi';
    public const ACTION_APPROVED = 'disetujui';

    public const ACTION_LABELS = [
        self::ACTION_SUBMITTED => 'Diajukan untuk review',
        self::ACTION_REVISION => 'Diminta revisi',
        self::ACTION_APPROVED => 'Disetujui',
    ];

    protected $fillable = ['document_file_version_id', 'user_id', 'action', 'notes'];

    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentFileVersion::class, 'document_file_version_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }
}
