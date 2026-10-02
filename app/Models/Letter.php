<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Surat masuk / surat keluar. Aturan akses baca (scopeVisibleTo):
 * - penerima disposisi & pembuat surat selalu boleh melihat suratnya;
 * - pemilik permission letter.view melihat semua surat, KECUALI surat
 *   bersifat rahasia yang butuh letter.confidential.
 */
class Letter extends Model
{
    use HasUuids, SoftDeletes;

    public const DIRECTION_INCOMING = 'incoming';
    public const DIRECTION_OUTGOING = 'outgoing';

    public const STATUS_NEW = 'baru';
    public const STATUS_DISPOSED = 'didisposisi';
    public const STATUS_DONE = 'selesai';
    public const STATUS_DRAFT = 'draf';
    public const STATUS_SENT = 'terkirim';

    public const NATURE_CONFIDENTIAL = 'rahasia';

    public const NATURES = [
        'biasa' => 'Biasa',
        'penting' => 'Penting',
        'segera' => 'Segera',
        'rahasia' => 'Rahasia',
    ];

    public const STATUS_LABELS = [
        self::STATUS_NEW => 'Baru',
        self::STATUS_DISPOSED => 'Didisposisi',
        self::STATUS_DONE => 'Selesai',
        self::STATUS_DRAFT => 'Draf',
        self::STATUS_SENT => 'Terkirim',
    ];

    protected $fillable = [
        'direction', 'agenda_year', 'agenda_number', 'letter_number', 'letter_date', 'received_date',
        'correspondent', 'subject', 'summary', 'letter_classification_id', 'nature',
        'organizational_unit_id', 'work_id', 'status',
        'file_disk', 'file_path', 'file_original_name', 'file_mime', 'file_size', 'file_checksum',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'letter_date' => 'date',
            'received_date' => 'date',
        ];
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(LetterClassification::class, 'letter_classification_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dispositions(): HasMany
    {
        return $this->hasMany(LetterDisposition::class);
    }

    public function isIncoming(): bool
    {
        return $this->direction === self::DIRECTION_INCOMING;
    }

    public function directionLabel(): string
    {
        return $this->isIncoming() ? 'Surat Masuk' : 'Surat Keluar';
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function natureLabel(): string
    {
        return self::NATURES[$this->nature] ?? $this->nature;
    }

    public function agendaLabel(): string
    {
        return str_pad((string) $this->agenda_number, 4, '0', STR_PAD_LEFT) . '/' . $this->agenda_year;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhereHas('dispositions', fn ($d) => $d->where('to_user_id', $user->id));

            if ($user->can('letter.view')) {
                if ($user->can('letter.confidential')) {
                    $q->orWhereNotNull('letters.id');
                } else {
                    $q->orWhere('nature', '!=', self::NATURE_CONFIDENTIAL);
                }
            }
        });
    }

    public function isVisibleTo(User $user): bool
    {
        return self::withTrashed()->visibleTo($user)->whereKey($this->getKey())->exists();
    }

    /**
     * Nomor agenda berikutnya untuk arah & tahun tertentu. Dipanggil di
     * dalam transaksi; baris terakhir dikunci supaya dua petugas yang
     * menyimpan bersamaan tidak mendapat nomor sama (ada juga unique index).
     */
    public static function nextAgendaNumber(string $direction, int $year): int
    {
        $last = self::withTrashed()
            ->where('direction', $direction)
            ->where('agenda_year', $year)
            ->lockForUpdate()
            ->max('agenda_number');

        return ((int) $last) + 1;
    }

    /**
     * Susun nomor surat keluar dari format di config('siarpu.letter_number_format').
     */
    public static function formatOutgoingNumber(int $sequence, Carbon $date, ?LetterClassification $classification, ?OrganizationalUnit $unit): string
    {
        $romans = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

        $tokens = [
            '{no}' => (string) $sequence,
            '{klasifikasi}' => $classification?->code ?? '',
            '{unit}' => $unit?->code ?: config('siarpu.agency_code', ''),
            '{bulan_romawi}' => $romans[(int) $date->format('n')],
            '{tahun}' => $date->format('Y'),
        ];

        $number = strtr(config('siarpu.letter_number_format', '{no}/{klasifikasi}/{unit}/{tahun}'), $tokens);

        // Buang segmen kosong: "12//BBWSNT2/2026" -> "12/BBWSNT2/2026".
        $number = preg_replace('#/{2,}#', '/', $number);

        return trim($number, '/ ');
    }

    /**
     * Status surat masuk mengikuti disposisinya: masih ada yang terbuka =
     * didisposisi, semua selesai = selesai. Surat keluar tidak disentuh.
     */
    public function refreshDispositionStatus(): void
    {
        if (! $this->isIncoming() || ! $this->dispositions()->exists()) {
            return;
        }

        $status = $this->dispositions()->open()->exists() ? self::STATUS_DISPOSED : self::STATUS_DONE;

        if ($status !== $this->status) {
            $this->update(['status' => $status]);
        }
    }

    public static function createWithAgenda(array $attributes): self
    {
        return DB::transaction(function () use ($attributes) {
            $date = Carbon::parse($attributes['letter_date']);
            $year = (int) $date->format('Y');
            $attributes['agenda_year'] = $year;
            $attributes['agenda_number'] = self::nextAgendaNumber($attributes['direction'], $year);

            if ($attributes['direction'] === self::DIRECTION_OUTGOING && blank($attributes['letter_number'] ?? null)) {
                $attributes['letter_number'] = self::formatOutgoingNumber(
                    $attributes['agenda_number'],
                    $date,
                    isset($attributes['letter_classification_id']) ? LetterClassification::find($attributes['letter_classification_id']) : null,
                    isset($attributes['organizational_unit_id']) ? OrganizationalUnit::find($attributes['organizational_unit_id']) : null,
                );
            }

            return self::create($attributes);
        });
    }
}
