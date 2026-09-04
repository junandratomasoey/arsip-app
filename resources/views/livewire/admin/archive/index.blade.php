<?php

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\PhysicalLocation;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public ?string $assigningDocumentId = null;

    public ?int $physicalLocationId = null;

    public string $physicalCode = '';

    public string $physicalStoredAt = '';

    public array $locationOptions = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('archive.view'), 403);

        $this->locationOptions = PhysicalLocation::orderBy('parent_id')->orderBy('order_column')->get()
            ->map(fn (PhysicalLocation $location) => [
                'id' => $location->id,
                'label' => $location->breadcrumbLabel(),
            ])
            ->sortBy('label')
            ->values()
            ->all();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $documents = Document::query()
            ->with(['work', 'physicalLocation'])
            ->when($this->search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('title', 'ilike', "%{$this->search}%")
                ->orWhereHas('work', fn ($q3) => $q3
                    ->where('code', 'ilike', "%{$this->search}%")
                    ->orWhere('name', 'ilike', "%{$this->search}%"))))
            ->orderByDesc('created_at')
            ->paginate(15);

        return ['documents' => $documents];
    }

    public function startAssign(string $documentId): void
    {
        abort_unless(auth()->user()->can('archive.create') || auth()->user()->can('archive.update'), 403);

        $document = Document::findOrFail($documentId);

        $this->resetValidation();
        $this->assigningDocumentId = $document->id;
        $this->physicalLocationId = $document->physical_location_id;
        $this->physicalCode = (string) $document->physical_code;
        $this->physicalStoredAt = $document->physical_stored_at?->format('Y-m-d') ?? '';
    }

    public function cancelAssign(): void
    {
        $this->assigningDocumentId = null;
    }

    public function saveAssign(): void
    {
        abort_unless(auth()->user()->can('archive.create') || auth()->user()->can('archive.update'), 403);

        $this->validate([
            'physicalLocationId' => 'required|exists:physical_locations,id',
            'physicalCode' => 'nullable|string|max:100',
            'physicalStoredAt' => 'nullable|date',
        ]);

        $document = Document::findOrFail($this->assigningDocumentId);
        $document->update([
            'physical_location_id' => $this->physicalLocationId,
            'physical_code' => $this->physicalCode !== '' ? $this->physicalCode : null,
            'physical_stored_at' => $this->physicalStoredAt !== '' ? $this->physicalStoredAt : null,
        ]);

        AuditLog::record('archive.assigned', $document, 'Tempatkan dokumen: ' . $document->title, [
            'physical_location' => PhysicalLocation::find($this->physicalLocationId)?->breadcrumbLabel(),
        ]);

        $this->assigningDocumentId = null;
        session()->flash('status', 'Lokasi fisik dokumen berhasil disimpan.');
    }

    public function unassign(string $documentId): void
    {
        abort_unless(auth()->user()->can('archive.update'), 403);

        $document = Document::findOrFail($documentId);
        $document->update([
            'physical_location_id' => null,
            'physical_code' => null,
            'physical_stored_at' => null,
        ]);

        AuditLog::record('archive.unassigned', $document, 'Lepas dokumen dari lokasi fisik: ' . $document->title);

        session()->flash('status', 'Dokumen dilepas dari lokasi fisik.');
    }
}; ?>

<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Penempatan Arsip Fisik
    </h2>
</x-slot>

<div>
    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-md px-4 py-3">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <x-input-label for="search" value="Cari judul dokumen / kode / nama pekerjaan" />
                <x-text-input wire:model.live.debounce.400ms="search" id="search" type="text" class="mt-1 block w-full" placeholder="Ketik untuk mencari..." />
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Dokumen</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Pekerjaan</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Lokasi Fisik</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500 uppercase text-xs">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($documents as $document)
                            <tr>
                                <td class="px-4 py-2 text-gray-900">{{ $document->title }}</td>
                                <td class="px-4 py-2 text-gray-500 whitespace-nowrap">{{ $document->work->code }} - {{ $document->work->name }}</td>
                                <td class="px-4 py-2 text-gray-500">
                                    @if ($document->physicalLocation)
                                        <a href="{{ route('admin.physical-locations.show', $document->physicalLocation->id) }}" wire:navigate class="text-indigo-600 hover:underline">
                                            {{ $document->physicalLocation->breadcrumbLabel() }}
                                        </a>
                                        @if ($document->physical_code)
                                            <span class="text-xs text-gray-400">({{ $document->physical_code }})</span>
                                        @endif
                                    @else
                                        <span class="text-gray-400 italic">belum ditempatkan</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right space-x-3 text-xs whitespace-nowrap">
                                    @can('archive.create')
                                        <button type="button" wire:click="startAssign('{{ $document->id }}')" class="text-indigo-600 hover:underline">
                                            {{ $document->physicalLocation ? 'ubah lokasi' : 'tempatkan' }}
                                        </button>
                                    @endcan
                                    @if ($document->physicalLocation)
                                        @can('archive.update')
                                            <button type="button" wire:click="unassign('{{ $document->id }}')" class="text-red-600 hover:underline">
                                                lepas
                                            </button>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-gray-400 italic">Tidak ada dokumen ditemukan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>
                {{ $documents->links() }}
            </div>
        </div>
    </div>

    @if ($assigningDocumentId)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-500/75" wire:click="cancelAssign"></div>

            <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-md sm:mx-auto">
                <form wire:submit="saveAssign" class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Tempatkan Dokumen</h3>

                    <div class="space-y-4">
                        <div>
                            <x-input-label for="physicalLocationId" value="Lokasi Fisik" />
                            <x-select-input wire:model="physicalLocationId" id="physicalLocationId" class="mt-1 block w-full">
                                <option value="">- Pilih lokasi -</option>
                                @foreach ($locationOptions as $opt)
                                    <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('physicalLocationId')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="physicalCode" value="Kode fisik (opsional)" />
                            <x-text-input wire:model="physicalCode" id="physicalCode" type="text" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('physicalCode')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="physicalStoredAt" value="Tanggal disimpan (opsional)" />
                            <x-text-input wire:model="physicalStoredAt" id="physicalStoredAt" type="date" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('physicalStoredAt')" class="mt-1" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" wire:click="cancelAssign" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
