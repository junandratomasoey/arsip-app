<?php

use App\Models\AuditLog;
use App\Models\Letter;
use App\Models\LetterClassification;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    #[Url(as: 'arah')]
    public string $arah = 'masuk';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'tahun')]
    public string $year = '';

    public string $statusFilter = '';
    public string $classificationFilter = '';

    public ?string $confirmingDeleteId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('letter.view'), 403);

        if (! in_array($this->arah, ['masuk', 'keluar'], true)) {
            $this->arah = 'masuk';
        }
    }

    public function updating($property): void
    {
        if (in_array($property, ['search', 'year', 'statusFilter', 'classificationFilter', 'arah'], true)) {
            $this->resetPage();
        }
    }

    public function updatedArah(): void
    {
        $this->statusFilter = '';
    }

    private function direction(): string
    {
        return $this->arah === 'keluar' ? Letter::DIRECTION_OUTGOING : Letter::DIRECTION_INCOMING;
    }

    public function with(): array
    {
        $direction = $this->direction();

        $letters = Letter::query()
            ->visibleTo(auth()->user())
            ->with(['classification', 'unit'])
            ->withCount(['dispositions', 'dispositions as open_dispositions_count' => fn ($q) => $q->open()])
            ->where('direction', $direction)
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('letter_number', 'like', "%{$this->search}%")
                ->orWhere('subject', 'like', "%{$this->search}%")
                ->orWhere('correspondent', 'like', "%{$this->search}%")))
            ->when($this->year, fn ($q) => $q->where('agenda_year', (int) $this->year))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->classificationFilter, fn ($q) => $q->where('letter_classification_id', (int) $this->classificationFilter))
            ->orderByDesc('agenda_year')
            ->orderByDesc('agenda_number')
            ->paginate(20);

        $years = Letter::query()->where('direction', $direction)->distinct()->orderByDesc('agenda_year')->pluck('agenda_year');

        $statuses = $direction === Letter::DIRECTION_INCOMING
            ? [Letter::STATUS_NEW, Letter::STATUS_DISPOSED, Letter::STATUS_DONE]
            : [Letter::STATUS_DRAFT, Letter::STATUS_SENT];

        return [
            'letters' => $letters,
            'years' => $years,
            'statuses' => $statuses,
            'classifications' => LetterClassification::orderBy('order_column')->orderBy('code')->get(),
            'isIncoming' => $direction === Letter::DIRECTION_INCOMING,
        ];
    }

    public function confirmDelete(string $id): void
    {
        abort_unless(auth()->user()->can('letter.delete'), 403);
        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        abort_unless(auth()->user()->can('letter.delete'), 403);

        $letter = Letter::visibleTo(auth()->user())->findOrFail($this->confirmingDeleteId);
        $letter->delete();

        AuditLog::record('letter.deleted', $letter, 'Hapus ' . strtolower($letter->directionLabel()) . ': ' . ($letter->letter_number ?? '-') . ' - ' . $letter->subject);

        $this->confirmingDeleteId = null;
        session()->flash('status', 'Surat dihapus.');
    }
}; ?>

<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ $isIncoming ? 'Surat Masuk' : 'Surat Keluar' }}
    </h2>
</x-slot>

<div>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-md px-4 py-3">
                    {{ session('status') }}
                </div>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="inline-flex rounded-md shadow-sm">
                    <button type="button" wire:click="$set('arah', 'masuk')" @class([
                        'px-4 py-2 text-xs font-semibold uppercase tracking-widest rounded-l-md border',
                        'bg-pu-navy text-white border-pu-navy' => $isIncoming,
                        'bg-white text-gray-600 border-gray-300' => ! $isIncoming,
                    ])>Surat Masuk</button>
                    <button type="button" wire:click="$set('arah', 'keluar')" @class([
                        'px-4 py-2 text-xs font-semibold uppercase tracking-widest rounded-r-md border -ml-px',
                        'bg-pu-navy text-white border-pu-navy' => ! $isIncoming,
                        'bg-white text-gray-600 border-gray-300' => $isIncoming,
                    ])>Surat Keluar</button>
                </div>

                @can('letter.create')
                    <a href="{{ route('admin.letters.create', ['arah' => $arah]) }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-pu-navy border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-pu-navy-700">
                        + {{ $isIncoming ? 'Catat Surat Masuk' : 'Buat Surat Keluar' }}
                    </a>
                @endcan
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
                <x-text-input wire:model.live.debounce.400ms="search" type="search" placeholder="Cari nomor, perihal, {{ $isIncoming ? 'pengirim' : 'tujuan' }}..." class="sm:col-span-2 w-full text-sm" />
                <x-select-input wire:model.live="year" class="w-full text-sm">
                    <option value="">Semua tahun</option>
                    @foreach ($years as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </x-select-input>
                <x-select-input wire:model.live="statusFilter" class="w-full text-sm">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s }}">{{ \App\Models\Letter::STATUS_LABELS[$s] }}</option>
                    @endforeach
                </x-select-input>
                @if ($classifications->isNotEmpty())
                    <x-select-input wire:model.live="classificationFilter" class="w-full text-sm sm:col-span-2">
                        <option value="">Semua klasifikasi</option>
                        @foreach ($classifications as $c)
                            <option value="{{ $c->id }}">{{ $c->code }} - {{ $c->name }}</option>
                        @endforeach
                    </x-select-input>
                @endif
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Agenda</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Nomor &amp; Tanggal</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">{{ $isIncoming ? 'Pengirim' : 'Tujuan' }}</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Perihal</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Status</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500 uppercase text-xs">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($letters as $letter)
                            <tr wire:key="letter-{{ $letter->id }}">
                                <td class="px-4 py-2 text-gray-500 align-top whitespace-nowrap">{{ $letter->agendaLabel() }}</td>
                                <td class="px-4 py-2 align-top">
                                    <span class="text-gray-900">{{ $letter->letter_number ?? '-' }}</span>
                                    <p class="text-xs text-gray-400">{{ $letter->letter_date->format('d-m-Y') }}
                                        @if ($letter->classification) · {{ $letter->classification->code }} @endif
                                    </p>
                                </td>
                                <td class="px-4 py-2 text-gray-600 align-top">{{ $letter->correspondent }}</td>
                                <td class="px-4 py-2 text-gray-900 align-top max-w-sm">
                                    {{ $letter->subject }}
                                    @if ($letter->nature !== 'biasa')
                                        <span @class([
                                            'ml-1 text-[10px] font-semibold uppercase tracking-wide rounded px-1.5 py-0.5',
                                            'bg-red-100 text-red-700' => $letter->nature === 'rahasia',
                                            'bg-amber-100 text-amber-700' => $letter->nature !== 'rahasia',
                                        ])>{{ $letter->natureLabel() }}</span>
                                    @endif
                                    @if (! $letter->file_path)
                                        <p class="text-xs text-gray-400 italic">belum ada berkas</p>
                                    @endif
                                </td>
                                <td class="px-4 py-2 align-top whitespace-nowrap">
                                    <span @class([
                                        'text-[10px] font-semibold uppercase tracking-wide rounded px-1.5 py-0.5',
                                        'bg-sky-100 text-sky-700' => in_array($letter->status, ['baru', 'draf']),
                                        'bg-amber-100 text-amber-700' => $letter->status === 'didisposisi',
                                        'bg-emerald-100 text-emerald-700' => in_array($letter->status, ['selesai', 'terkirim']),
                                    ])>{{ $letter->statusLabel() }}</span>
                                    @if ($letter->dispositions_count > 0)
                                        <p class="text-xs text-gray-400 mt-1">{{ $letter->dispositions_count - $letter->open_dispositions_count }}/{{ $letter->dispositions_count }} disposisi selesai</p>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right space-x-3 text-xs whitespace-nowrap align-top">
                                    <a href="{{ route('admin.letters.show', $letter) }}" wire:navigate class="text-pu-navy-600 hover:underline">detail</a>
                                    @can('letter.update')
                                        <a href="{{ route('admin.letters.edit', $letter) }}" wire:navigate class="text-pu-navy-600 hover:underline">ubah</a>
                                    @endcan
                                    @can('letter.delete')
                                        <button type="button" wire:click="confirmDelete('{{ $letter->id }}')" class="text-red-600 hover:underline">hapus</button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-400 italic">Belum ada {{ $isIncoming ? 'surat masuk' : 'surat keluar' }}.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $letters->links() }}</div>
        </div>
    </div>

    @if ($confirmingDeleteId)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-500/75" wire:click="cancelDelete"></div>
            <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-md sm:mx-auto p-6">
                <h3 class="text-lg font-medium text-gray-900">Hapus surat ini?</h3>
                <p class="mt-2 text-sm text-gray-500">Surat dan riwayat disposisinya disembunyikan dari daftar (soft delete). Nomor agendanya tidak dipakai ulang.</p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="cancelDelete" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">Batal</button>
                    <button type="button" wire:click="delete" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">Hapus</button>
                </div>
            </div>
        </div>
    @endif
</div>
