<?php

use App\Models\AuditLog;
use App\Models\Letter;
use App\Models\LetterClassification;
use App\Models\OrganizationalUnit;
use App\Models\Work;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public ?Letter $letter = null;

    public string $direction = Letter::DIRECTION_INCOMING;

    public string $letterDate = '';
    public string $receivedDate = '';
    public string $letterNumber = '';
    public bool $autoNumber = true;
    public string $correspondent = '';
    public string $subject = '';
    public string $summary = '';
    public ?int $classificationId = null;
    public string $nature = 'biasa';
    public ?int $unitId = null;
    public string $status = '';

    public ?string $workId = null;
    public string $workLabel = '';
    public string $workSearch = '';

    public $scanFile = null;

    public function mount(?Letter $letter = null): void
    {
        if ($letter && $letter->exists) {
            abort_unless(auth()->user()->can('letter.update') && $letter->isVisibleTo(auth()->user()), 403);

            $this->letter = $letter;
            $this->direction = $letter->direction;
            $this->letterDate = $letter->letter_date->format('Y-m-d');
            $this->receivedDate = $letter->received_date?->format('Y-m-d') ?? '';
            $this->letterNumber = $letter->letter_number ?? '';
            $this->autoNumber = false;
            $this->correspondent = $letter->correspondent;
            $this->subject = $letter->subject;
            $this->summary = $letter->summary ?? '';
            $this->classificationId = $letter->letter_classification_id;
            $this->nature = $letter->nature;
            $this->unitId = $letter->organizational_unit_id;
            $this->status = $letter->status;
            $this->workId = $letter->work_id;
            $this->workLabel = $letter->work ? $letter->work->code . ' - ' . $letter->work->name : '';
        } else {
            abort_unless(auth()->user()->can('letter.create'), 403);

            $this->letter = null;
            $this->direction = request()->query('arah') === 'keluar' ? Letter::DIRECTION_OUTGOING : Letter::DIRECTION_INCOMING;
            $this->letterDate = now()->format('Y-m-d');
            $this->receivedDate = $this->direction === Letter::DIRECTION_INCOMING ? now()->format('Y-m-d') : '';
            $this->status = $this->direction === Letter::DIRECTION_INCOMING ? Letter::STATUS_NEW : Letter::STATUS_DRAFT;
            $this->unitId = auth()->user()->organizational_unit_id;
        }
    }

    public function isIncoming(): bool
    {
        return $this->direction === Letter::DIRECTION_INCOMING;
    }

    public function with(): array
    {
        $workResults = collect();
        if (strlen(trim($this->workSearch)) >= 2) {
            $workResults = Work::query()
                ->where(fn ($q) => $q->where('code', 'like', "%{$this->workSearch}%")->orWhere('name', 'like', "%{$this->workSearch}%"))
                ->orderByDesc('fiscal_year')
                ->limit(8)
                ->get(['id', 'code', 'name', 'fiscal_year']);
        }

        return [
            'classifications' => LetterClassification::where('is_active', true)
                ->orWhere('id', $this->classificationId)
                ->orderBy('order_column')->orderBy('code')->get(),
            'units' => OrganizationalUnit::where('is_active', true)
                ->orWhere('id', $this->unitId)
                ->orderBy('order_column')->get(),
            'workResults' => $workResults,
            'numberPreview' => $this->numberPreview(),
            'maxFileMb' => (int) round(config('siarpu.letter_max_file_kb') / 1024),
        ];
    }

    private function numberPreview(): ?string
    {
        if ($this->isIncoming() || $this->letter || ! $this->autoNumber) {
            return null;
        }

        try {
            $date = Carbon::parse($this->letterDate ?: now());
        } catch (\Throwable) {
            $date = now();
        }

        $next = ((int) Letter::withTrashed()->where('direction', Letter::DIRECTION_OUTGOING)
            ->where('agenda_year', (int) $date->format('Y'))->max('agenda_number')) + 1;

        return Letter::formatOutgoingNumber(
            $next,
            $date,
            $this->classificationId ? LetterClassification::find($this->classificationId) : null,
            $this->unitId ? OrganizationalUnit::find($this->unitId) : null,
        );
    }

    public function pickWork(string $id): void
    {
        $work = Work::findOrFail($id);
        $this->workId = $work->id;
        $this->workLabel = $work->code . ' - ' . $work->name;
        $this->workSearch = '';
    }

    public function clearWork(): void
    {
        $this->workId = null;
        $this->workLabel = '';
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can($this->letter ? 'letter.update' : 'letter.create'), 403);

        $needsManualNumber = $this->isIncoming() || $this->letter || ! $this->autoNumber;

        $this->validate([
            'letterDate' => 'required|date',
            'receivedDate' => 'nullable|date',
            'letterNumber' => ($needsManualNumber && $this->isIncoming() ? 'required' : 'nullable') . '|string|max:255',
            'correspondent' => 'required|string|max:255',
            'subject' => 'required|string|max:500',
            'summary' => 'nullable|string|max:5000',
            'classificationId' => 'nullable|exists:letter_classifications,id',
            'nature' => 'required|in:' . implode(',', array_keys(Letter::NATURES)),
            'unitId' => 'nullable|exists:organizational_units,id',
            'status' => 'required|in:' . implode(',', $this->isIncoming()
                ? [Letter::STATUS_NEW, Letter::STATUS_DISPOSED, Letter::STATUS_DONE]
                : [Letter::STATUS_DRAFT, Letter::STATUS_SENT]),
            'scanFile' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:' . config('siarpu.letter_max_file_kb'),
        ], [], [
            'letterDate' => 'tanggal surat', 'letterNumber' => 'nomor surat', 'correspondent' => $this->isIncoming() ? 'pengirim' : 'tujuan',
            'subject' => 'perihal', 'scanFile' => 'berkas surat',
        ]);

        // Nomor surat keluar yang diketik manual tidak boleh kembar dengan
        // surat keluar lain (surat masuk dari instansi berbeda boleh sama).
        if (! $this->isIncoming() && $needsManualNumber && $this->letterNumber !== '') {
            $duplicate = Letter::withTrashed()
                ->where('direction', Letter::DIRECTION_OUTGOING)
                ->where('letter_number', $this->letterNumber)
                ->when($this->letter, fn ($q) => $q->whereKeyNot($this->letter->id))
                ->exists();

            if ($duplicate) {
                $this->addError('letterNumber', 'Nomor surat keluar ini sudah dipakai.');

                return;
            }
        }

        $data = [
            'letter_date' => $this->letterDate,
            'received_date' => $this->isIncoming() && $this->receivedDate !== '' ? $this->receivedDate : null,
            'letter_number' => $needsManualNumber && $this->letterNumber !== '' ? $this->letterNumber : null,
            'correspondent' => $this->correspondent,
            'subject' => $this->subject,
            'summary' => $this->summary !== '' ? $this->summary : null,
            'letter_classification_id' => $this->classificationId ?: null,
            'nature' => $this->nature,
            'organizational_unit_id' => $this->unitId ?: null,
            'work_id' => $this->workId,
            'status' => $this->status,
        ];

        if ($this->scanFile) {
            $disk = config('filesystems.document_files_disk', 'local');
            $path = $this->scanFile->store('letters/' . now()->format('Y'), $disk);

            $data += [
                'file_disk' => $disk,
                'file_path' => $path,
                'file_original_name' => $this->scanFile->getClientOriginalName(),
                'file_mime' => $this->scanFile->getMimeType(),
                'file_size' => $this->scanFile->getSize(),
                'file_checksum' => hash_file('sha256', $this->scanFile->getRealPath()),
            ];
        }

        $isNew = ! $this->letter;

        if ($this->letter) {
            $oldFile = $this->scanFile && $this->letter->file_path ? [$this->letter->file_disk, $this->letter->file_path] : null;
            $this->letter->update($data);
            $letter = $this->letter;

            if ($oldFile) {
                Storage::disk($oldFile[0])->delete($oldFile[1]);
            }
        } else {
            $data['direction'] = $this->direction;
            $data['created_by'] = auth()->id();
            $letter = Letter::createWithAgenda($data);
        }

        AuditLog::record(
            $isNew ? 'letter.created' : 'letter.updated',
            $letter,
            ($isNew ? 'Catat ' : 'Ubah ') . strtolower($letter->directionLabel()) . ': ' . ($letter->letter_number ?? '-') . ' - ' . $letter->subject,
            ['agenda' => $letter->agendaLabel()]
        );

        session()->flash('status', $isNew
            ? 'Surat tersimpan dengan nomor agenda ' . $letter->agendaLabel() . ($letter->isIncoming() ? '.' : ', nomor surat ' . $letter->letter_number . '.')
            : 'Perubahan surat disimpan.');

        $this->redirect(route('admin.letters.show', $letter), navigate: true);
    }
}; ?>

<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ $letter ? 'Ubah ' : ($direction === 'incoming' ? 'Catat ' : 'Buat ') }}{{ $direction === 'incoming' ? 'Surat Masuk' : 'Surat Keluar' }}
    </h2>
</x-slot>

<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <form wire:submit="save" class="space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Identitas Surat</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    @if ($direction === 'incoming' || $letter)
                        <div class="sm:col-span-2">
                            <x-input-label for="letterNumber" value="Nomor Surat {{ $direction === 'incoming' ? '*' : '' }}" />
                            <x-text-input wire:model="letterNumber" id="letterNumber" type="text" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('letterNumber')" class="mt-1" />
                        </div>
                    @else
                        <div class="sm:col-span-2">
                            <x-input-label value="Nomor Surat" />
                            <label class="mt-1 inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" wire:model.live="autoNumber" class="rounded border-gray-300 text-pu-navy focus:ring-pu-navy-500">
                                Buat nomor otomatis
                            </label>
                            @if ($autoNumber)
                                <p class="mt-2 text-sm">
                                    <span class="font-mono bg-gray-100 rounded px-2 py-1">{{ $numberPreview }}</span>
                                    <span class="text-xs text-gray-400 ml-2">perkiraan; nomor pasti ditetapkan saat disimpan</span>
                                </p>
                            @else
                                <x-text-input wire:model="letterNumber" type="text" class="mt-2 block w-full" placeholder="mis. nomor dari SRIKANDI" />
                            @endif
                            <x-input-error :messages="$errors->get('letterNumber')" class="mt-1" />
                        </div>
                    @endif

                    <div>
                        <x-input-label for="letterDate" value="Tanggal Surat *" />
                        <x-text-input wire:model.live="letterDate" id="letterDate" type="date" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('letterDate')" class="mt-1" />
                    </div>

                    @if ($direction === 'incoming')
                        <div>
                            <x-input-label for="receivedDate" value="Tanggal Diterima" />
                            <x-text-input wire:model="receivedDate" id="receivedDate" type="date" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('receivedDate')" class="mt-1" />
                        </div>
                    @endif

                    <div class="sm:col-span-2">
                        <x-input-label for="correspondent" value="{{ $direction === 'incoming' ? 'Pengirim *' : 'Tujuan *' }}" />
                        <x-text-input wire:model="correspondent" id="correspondent" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('correspondent')" class="mt-1" />
                    </div>

                    <div class="sm:col-span-2">
                        <x-input-label for="subject" value="Perihal *" />
                        <x-text-input wire:model="subject" id="subject" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('subject')" class="mt-1" />
                    </div>

                    <div class="sm:col-span-2">
                        <x-input-label for="summary" value="Ringkasan Isi" />
                        <textarea wire:model="summary" id="summary" rows="3" class="mt-1 block w-full border-gray-300 focus:border-pu-navy-500 focus:ring-pu-navy-500 rounded-md shadow-sm"></textarea>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Klasifikasi &amp; Pengolah</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="classificationId" value="Klasifikasi Arsip" />
                        <x-select-input wire:model.live="classificationId" id="classificationId" class="mt-1 block w-full">
                            <option value="">-</option>
                            @foreach ($classifications as $c)
                                <option value="{{ $c->id }}">{{ $c->code }} - {{ $c->name }}</option>
                            @endforeach
                        </x-select-input>
                        @if ($classifications->isEmpty())
                            <p class="text-xs text-gray-400 mt-1">Belum ada data. Tambahkan di menu Klasifikasi Arsip.</p>
                        @endif
                    </div>
                    <div>
                        <x-input-label for="nature" value="Sifat Surat *" />
                        <x-select-input wire:model="nature" id="nature" class="mt-1 block w-full">
                            @foreach (\App\Models\Letter::NATURES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-select-input>
                        <p class="text-xs text-gray-400 mt-1">Surat rahasia hanya terlihat oleh pimpinan, pembuat, dan penerima disposisinya.</p>
                    </div>
                    <div>
                        <x-input-label for="unitId" value="{{ $direction === 'incoming' ? 'Unit Penerima' : 'Unit Pengolah' }}" />
                        <x-select-input wire:model.live="unitId" id="unitId" class="mt-1 block w-full">
                            <option value="">-</option>
                            @foreach ($units as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}{{ $u->code ? ' (' . $u->code . ')' : '' }}</option>
                            @endforeach
                        </x-select-input>
                    </div>
                    <div>
                        <x-input-label for="status" value="Status" />
                        <x-select-input wire:model="status" id="status" class="mt-1 block w-full">
                            @if ($direction === 'incoming')
                                <option value="baru">Baru</option>
                                <option value="didisposisi">Didisposisi</option>
                                <option value="selesai">Selesai</option>
                            @else
                                <option value="draf">Draf</option>
                                <option value="terkirim">Terkirim</option>
                            @endif
                        </x-select-input>
                    </div>

                    <div class="sm:col-span-2">
                        <x-input-label value="Terkait Pekerjaan (opsional)" />
                        @if ($workId)
                            <div class="mt-1 flex items-center gap-3 text-sm">
                                <span class="bg-pu-navy-50 text-pu-navy-800 rounded px-2 py-1">{{ $workLabel }}</span>
                                <button type="button" wire:click="clearWork" class="text-xs text-red-600 hover:underline">lepas</button>
                            </div>
                        @else
                            <x-text-input wire:model.live.debounce.300ms="workSearch" type="search" placeholder="Ketik kode atau nama pekerjaan..." class="mt-1 block w-full" />
                            @if ($workResults->isNotEmpty())
                                <ul class="mt-1 border border-gray-200 rounded-md divide-y divide-gray-100 text-sm bg-white">
                                    @foreach ($workResults as $w)
                                        <li>
                                            <button type="button" wire:click="pickWork('{{ $w->id }}')" class="w-full text-left px-3 py-2 hover:bg-gray-50">
                                                <span class="text-gray-500">{{ $w->code }}</span> - {{ $w->name }}
                                                @if ($w->fiscal_year) <span class="text-xs text-gray-400">({{ $w->fiscal_year }})</span> @endif
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @elseif (strlen(trim($workSearch)) >= 2)
                                <p class="text-xs text-gray-400 mt-1">Tidak ada pekerjaan yang cocok.</p>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-1">Berkas Surat</h3>
                <p class="text-xs text-gray-400 mb-3">PDF atau gambar hasil pindai, maks {{ $maxFileMb }}MB.</p>
                @if ($letter && $letter->file_path)
                    <p class="text-sm text-gray-600 mb-2">Berkas saat ini: <span class="font-medium">{{ $letter->file_original_name }}</span>. Unggah berkas baru untuk menggantinya.</p>
                @endif
                <input type="file" wire:model="scanFile" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:uppercase file:bg-pu-navy-50 file:text-pu-navy-700 hover:file:bg-pu-navy-100">
                <div wire:loading wire:target="scanFile" class="text-xs text-gray-400 mt-1">Mengunggah...</div>
                <x-input-error :messages="$errors->get('scanFile')" class="mt-1" />
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ $letter ? route('admin.letters.show', $letter) : route('admin.letters.index', ['arah' => $direction === 'incoming' ? 'masuk' : 'keluar']) }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">Batal</a>
                <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 bg-pu-navy border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-pu-navy-700 disabled:opacity-50">Simpan</button>
            </div>
        </form>
    </div>
</div>
