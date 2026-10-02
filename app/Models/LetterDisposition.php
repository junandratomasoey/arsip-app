<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LetterDisposition extends Model
{
    public const STATUS_NEW = 'baru';
    public const STATUS_READ = 'dibaca';
    public const STATUS_IN_PROGRESS = 'diproses';
    public const STATUS_DONE = 'selesai';

    public const STATUS_LABELS = [
        self::STATUS_NEW => 'Baru',
        self::STATUS_READ => 'Dibaca',
        self::STATUS_IN_PROGRESS => 'Diproses',
        self::STATUS_DONE => 'Selesai',
    ];

    protected $fillable = [
        'letter_id', 'parent_id', 'from_user_id', 'to_user_id', 'instruction', 'due_date',
        'status', 'read_at', 'response', 'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'read_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function letter(): BelongsTo
    {
        // withTrashed: kotak masuk tetap bisa menampilkan disposisi meski
        // suratnya sudah dihapus (soft delete), sama seperti relasi Loan.
        return $this->belongsTo(Letter::class)->withTrashed();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isOverdue(): bool
    {
        return $this->due_date && $this->status !== self::STATUS_DONE && $this->due_date->endOfDay()->isPast();
    }

    public function scopeOpen($query)
    {
        return $query->where('status', '!=', self::STATUS_DONE);
    }
}
