<?php

use App\Models\AuditLog;
use App\Models\Letter;
use App\Models\LetterDisposition;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Letter $letter;

    // Form disposisi baru
    public bool $showDisposeForm = false;
    public array $recipientIds = [];
    public string $instruction = '';
    public string $dueDate = '';
    public string $recipientSearch = '';

    // Tindak lanjut disposisi milik user ini
    public ?int $respondingId = null;
    public string $responseText = '';
    public string $responseStatus = LetterDisposition::STATUS_DONE;

    public function mount(Letter $letter): void
    {
        abort_unless($letter->isVisibleTo(auth()->user()), 403);

        $this->letter = $letter;

        // Membuka surat = disposisi untuk user ini otomatis dianggap dibaca.
        $letter->dispositions()
            ->where('to_user_id', auth()->id())
            ->where('status', LetterDisposition::STATUS_NEW)
            ->update(['status' => LetterDisposition::STATUS_READ, 'read_at' => now()]);
    }

    /**
     * Boleh memberi disposisi: pimpinan (letter.dispose) atau siapa pun
     * yang menerima disposisi surat ini (meneruskan ke bawahan).
     */
    public function canDispose(): bool
    {
        if (! $this->letter->isIncoming() || $this->letter->trashed()) {
            return false;
        }

        return auth()->user()->can('letter.dispose')
            || $this->letter->dispositions()->where('to_user_id', auth()->id())->exists();
    }

    public function with(): array
    {
        $dispositions = $this->letter->dispositions()
            ->with(['fromUser', 'toUser.organizationalUnit'])
            ->orderBy('created_at')
            ->get();

        $recipients = collect();
        if ($this->showDisposeForm) {
            $recipients = User::query()
                ->with('organizationalUnit')
                ->whereKeyNot(auth()->id())
                ->when($this->recipientSearch, fn ($q) => $q->where('name', 'like', "%{$this->recipientSearch}%"))
                ->orderBy('name')
                ->limit(50)
                ->get();
        }

        return [
            'tree' => $dispositions->groupBy(fn ($d) => $d->parent_id ?? 0),
            'dispositionCount' => $dispositions->count(),
            'recipients' => $recipients,
            'selectedRecipients' => User::whereIn('id', $this->recipientIds)->orderBy('name')->get(),
            'canDispose' => $this->canDispose(),
        ];
    }

    public function openDisposeForm(): void
    {
        abort_unless($this->canDispose(), 403);

        $this->resetValidation();
        $this->reset(['recipientIds', 'instruction', 'dueDate', 'recipientSearch']);
        $this->showDisposeForm = true;
    }

    public function closeDisposeForm(): void
    {
        $this->showDisposeForm = false;
    }

    public function toggleRecipient(int $userId): void
    {
        if (in_array($userId, $this->recipientIds, true)) {
            $this->recipientIds = array_values(array_diff($this->recipientIds, [$userId]));
        } else {
            $this->recipientIds[] = $userId;
        }
    }

    public function saveDisposition(): void
    {
        abort_unless($this->canDispose(), 403);

        $this->validate([
            'recipientIds' => 'required|array|min:1',
            'recipientIds.*' => 'integer|exists:users,id',
            'instruction' => 'required|string|max:2000',
            'dueDate' => 'nullable|date',
        ], [
            'recipientIds.required' => 'Pilih minimal satu penerima disposisi.',
        ], ['instruction' => 'isi disposisi']);

        // Disposisi lanjutan menginduk ke disposisi terakhir yang diterima
        // user ini, sehingga riwayatnya membentuk pohon berjenjang.
        $parentId = $this->letter->dispositions()
            ->where('to_user_id', auth()->id())
            ->latest('id')
            ->value('id');

        foreach (array_unique($this->recipientIds) as $userId) {
            $disposition = $this->letter->dispositions()->create([
                'parent_id' => $parentId,
                'from_user_id' => auth()->id(),
                'to_user_id' => $userId,
                'instruction' => $this->instruction,
                'due_date' => $this->dueDate !== '' ? $this->dueDate : null,
                'status' => LetterDisposition::STATUS_NEW,
            ]);
        }

        // Penerus disposisi otomatis dianggap sedang memproses.
        if ($parentId) {
            LetterDisposition::whereKey($parentId)
                ->whereIn('status', [LetterDisposition::STATUS_NEW, LetterDisposition::STATUS_READ])
                ->update(['status' => LetterDisposition::STATUS_IN_PROGRESS]);
        }

        $this->letter->update(['status' => Letter::STATUS_DISPOSED]);

        $names = User::whereIn('id', $this->recipientIds)->pluck('name')->implode(', ');
        AuditLog::record('letter.disposed', $this->letter, 'Disposisi surat ' . ($this->letter->letter_number ?? '-') . ' kepada ' . $names, [
            'instruction' => $this->instruction,
        ]);

        $this->showDisposeForm = false;
        session()->flash('status', 'Disposisi terkirim kepada ' . $names . '.');
    }

    public function startRespond(int $id): void
    {
        $disposition = $this->letter->dispositions()->where('to_user_id', auth()->id())->findOrFail($id);

        $this->resetValidation();
        $this->respondingId = $disposition->id;
        $this->responseText = $disposition->response ?? '';
        $this->responseStatus = LetterDisposition::STATUS_DONE;
    }

    public function cancelRespond(): void
    {
        $this->respondingId = null;
    }

    public function saveResponse(): void
    {
        $disposition = $this->letter->dispositions()->where('to_user_id', auth()->id())->findOrFail($this->respondingId);

        $this->validate([
            'responseText' => 'required|string|max:2000',
            'responseStatus' => 'required|in:diproses,selesai',
        ], [], ['responseText' => 'tindak lanjut']);

        $disposition->update([
            'response' => $this->responseText,
            'status' => $this->responseStatus,
            'responded_at' => now(),
            'read_at' => $disposition->read_at ?? now(),
        ]);

        $this->letter->refreshDispositionStatus();

        AuditLog::record('letter.disposition_responded', $this->letter, 'Tindak lanjut disposisi surat ' . ($this->letter->letter_number ?? '-') . ' (' . $disposition->statusLabel() . ')', [
            'response' => $this->responseText,
        ]);

        $this->respondingId = null;
        $this->letter->refresh();
        session()->flash('status', 'Tindak lanjut disimpan.');
    }

    public function markLetterDone(): void
    {
        abort_unless(auth()->user()->can('letter.update') && $this->letter->isIncoming(), 403);

        $this->letter->update(['status' => Letter::STATUS_DONE]);
        AuditLog::record('letter.updated', $this->letter, 'Tandai selesai surat masuk: ' . ($this->letter->letter_number ?? '-'));
        session()->flash('status', 'Surat ditandai selesai.');
    }
}; ?>

<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ $letter->directionLabel() }} · Agenda {{ $letter->agendaLabel() }}
    </h2>
</x-slot>

<div>
    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-md px-4 py-3">
                    {{ session('status') }}
                </div>
            @endif

            @if ($letter->trashed())
                <div class="bg-gray-100 border border-gray-200 text-gray-600 text-sm rounded-md px-4 py-3">Surat ini sudah dihapus.</div>
            @endif

            <div class="flex flex-wrap gap-3 justify-between">
                @can('letter.view')
                    <a href="{{ route('admin.letters.index', ['arah' => $letter->isIncoming() ? 'masuk' : 'keluar']) }}" wire:navigate class="text-sm text-pu-navy-600 hover:underline">&larr; Kembali ke daftar</a>
                @else
                    <a href="{{ route('admin.dispositions.index') }}" wire:navigate class="text-sm text-pu-navy-600 hover:underline">&larr; Kembali ke Disposisi Saya</a>
                @endcan
                <div class="flex gap-2">
                    @if ($canDispose)
                        <button type="button" wire:click="openDisposeForm" class="inline-flex items-center px-4 py-2 bg-pu-navy border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-pu-navy-700">Disposisi</button>
                    @endif
                    @can('letter.update')
                        @if ($letter->isIncoming() && $letter->status !== 'selesai' && ! $letter->trashed())
                            <button type="button" wire:click="markLetterDone" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">Tandai Selesai</button>
                        @endif
                        @unless ($letter->trashed())
                            <a href="{{ route('admin.letters.edit', $letter) }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">Ubah</a>
                        @endunless
                    @endcan
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Perihal</p>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $letter->subject }}</h3>
                    </div>
                    <div class="flex gap-2">
                        <span class="text-[10px] font-semibold uppercase tracking-wide rounded px-1.5 py-0.5 bg-gray-100 text-gray-700">{{ $letter->statusLabel() }}</span>
                        @if ($letter->nature !== 'biasa')
                            <span class="text-[10px] font-semibold uppercase tracking-wide rounded px-1.5 py-0.5 {{ $letter->nature === 'rahasia' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">{{ $letter->natureLabel() }}</span>
                        @endif
                    </div>
                </div>

                <dl class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div><dt class="text-gray-400">Nomor Surat</dt><dd class="text-gray-900 font-medium">{{ $letter->letter_number ?? '-' }}</dd></div>
                    <div><dt class="text-gray-400">Tanggal Surat</dt><dd class="text-gray-900">{{ $letter->letter_date->translatedFormat('d F Y') }}</dd></div>
                    <div><dt class="text-gray-400">{{ $letter->isIncoming() ? 'Pengirim' : 'Tujuan' }}</dt><dd class="text-gray-900">{{ $letter->correspondent }}</dd></div>
                    @if ($letter->isIncoming())
                        <div><dt class="text-gray-400">Tanggal Diterima</dt><dd class="text-gray-900">{{ $letter->received_date?->translatedFormat('d F Y') ?? '-' }}</dd></div>
                    @endif
                    <div><dt class="text-gray-400">Klasifikasi</dt><dd class="text-gray-900">{{ $letter->classification ? $letter->classification->code . ' - ' . $letter->classification->name : '-' }}</dd></div>
                    <div><dt class="text-gray-400">{{ $letter->isIncoming() ? 'Unit Penerima' : 'Unit Pengolah' }}</dt><dd class="text-gray-900">{{ $letter->unit?->name ?? '-' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-gray-400">Terkait Pekerjaan</dt>
                        <dd class="text-gray-900">
                            @if ($letter->work)
                                @can('work.view')
                                    <a href="{{ route('admin.works.documents', $letter->work) }}" wire:navigate class="text-pu-navy-600 hover:underline">{{ $letter->work->code }} - {{ $letter->work->name }}</a>
                                @else
                                    {{ $letter->work->code }} - {{ $letter->work->name }}
                                @endcan
                            @else
                                -
                            @endif
                        </dd>
                    </div>
                    @if ($letter->summary)
                        <div class="sm:col-span-2"><dt class="text-gray-400">Ringkasan</dt><dd class="text-gray-900 whitespace-pre-line">{{ $letter->summary }}</dd></div>
                    @endif
                </dl>

                <div class="mt-5 pt-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3 text-sm">
                    @if ($letter->file_path)
                        <span class="text-gray-600">{{ $letter->file_original_name }} <span class="text-xs text-gray-400">({{ number_format($letter->file_size / 1024, 0, ',', '.') }} KB)</span></span>
                        <div class="flex gap-4 text-xs">
                            @if (str_starts_with((string) $letter->file_mime, 'application/pdf') || str_starts_with((string) $letter->file_mime, 'image/'))
                                <a href="{{ route('admin.letters.file', [$letter, 'inline' => 1]) }}" target="_blank" class="text-pu-navy-600 hover:underline">lihat</a>
                            @endif
                            <a href="{{ route('admin.letters.file', $letter) }}" class="text-pu-navy-600 hover:underline">unduh</a>
                        </div>
                    @else
                        <span class="italic text-gray-400">Belum ada berkas surat.</span>
                    @endif
                </div>
                <p class="mt-3 text-xs text-gray-400">Dicatat oleh {{ $letter->creator?->name ?? '-' }} pada {{ $letter->created_at->format('d-m-Y H:i') }}</p>
            </div>

            @if ($letter->isIncoming())
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-4">Riwayat Disposisi</h3>

                    @if ($dispositionCount === 0)
                        <p class="text-sm text-gray-400 italic">Belum ada disposisi.</p>
                    @else
                        @include('letters.disposition-branch', ['tree' => $tree, 'parentKey' => 0, 'depth' => 0])
                    @endif
                </div>
            @endif
        </div>
    </div>

    @if ($showDisposeForm)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-500/75" wire:click="closeDisposeForm"></div>
            <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-lg sm:mx-auto">
                <form wire:submit="saveDisposition" class="p-6 space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">Disposisi Surat</h3>

                    <div>
                        <x-input-label value="Kepada *" />
                        @if ($selectedRecipients->isNotEmpty())
                            <div class="mt-1 flex flex-wrap gap-2">
                                @foreach ($selectedRecipients as $r)
                                    <span class="inline-flex items-center gap-1 bg-pu-navy-50 text-pu-navy-800 rounded px-2 py-1 text-xs">
                                        {{ $r->name }}
                                        <button type="button" wire:click="toggleRecipient({{ $r->id }})" class="text-pu-navy-500 hover:text-red-600">&times;</button>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                        <x-text-input wire:model.live.debounce.300ms="recipientSearch" type="search" placeholder="Cari nama pegawai..." class="mt-2 block w-full text-sm" />
                        <ul class="mt-1 max-h-48 overflow-y-auto border border-gray-200 rounded-md divide-y divide-gray-100 text-sm">
                            @forelse ($recipients as $r)
                                <li>
                                    <label class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 cursor-pointer">
                                        <input type="checkbox" wire:click="toggleRecipient({{ $r->id }})" @checked(in_array($r->id, $recipientIds, true)) class="rounded border-gray-300 text-pu-navy focus:ring-pu-navy-500">
                                        <span>{{ $r->name }}</span>
                                        <span class="text-xs text-gray-400">{{ $r->organizationalUnit?->name }}</span>
                                    </label>
                                </li>
                            @empty
                                <li class="px-3 py-2 text-gray-400 italic">Tidak ada pengguna lain.</li>
                            @endforelse
                        </ul>
                        <x-input-error :messages="$errors->get('recipientIds')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="instruction" value="Isi Disposisi *" />
                        <textarea wire:model="instruction" id="instruction" rows="3" placeholder="mis. Pelajari dan siapkan bahan jawaban" class="mt-1 block w-full border-gray-300 focus:border-pu-navy-500 focus:ring-pu-navy-500 rounded-md shadow-sm text-sm"></textarea>
                        <x-input-error :messages="$errors->get('instruction')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="dueDate" value="Batas Waktu (opsional)" />
                        <x-text-input wire:model="dueDate" id="dueDate" type="date" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('dueDate')" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeDisposeForm" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">Batal</button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-pu-navy border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-pu-navy-700">Kirim Disposisi</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($respondingId)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-500/75" wire:click="cancelRespond"></div>
            <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-md sm:mx-auto">
                <form wire:submit="saveResponse" class="p-6 space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">Tindak Lanjut Disposisi</h3>
                    <div>
                        <x-input-label for="responseText" value="Laporan tindak lanjut *" />
                        <textarea wire:model="responseText" id="responseText" rows="4" class="mt-1 block w-full border-gray-300 focus:border-pu-navy-500 focus:ring-pu-navy-500 rounded-md shadow-sm text-sm"></textarea>
                        <x-input-error :messages="$errors->get('responseText')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="responseStatus" value="Status" />
                        <x-select-input wire:model="responseStatus" id="responseStatus" class="mt-1 block w-full">
                            <option value="selesai">Selesai</option>
                            <option value="diproses">Masih diproses</option>
                        </x-select-input>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" wire:click="cancelRespond" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">Batal</button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-pu-navy border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-pu-navy-700">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
