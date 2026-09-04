<?php

use App\Models\AuditLog;
use App\Models\PhysicalLocation;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public array $tree = [];

    public ?int $editingId = null;

    public ?int $parentId = null;

    public string $type = '';

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public bool $isActive = true;

    public int $orderColumn = 0;

    public bool $showForm = false;

    public string $parentLabel = '';

    public function mount(): void
    {
        $this->loadTree();
    }

    public function loadTree(): void
    {
        $locations = PhysicalLocation::orderBy('order_column')->get();
        $this->tree = $this->buildTree($locations, null);
    }

    protected function buildTree($locations, ?int $parentId): array
    {
        return $locations->where('parent_id', $parentId)->map(fn ($location) => [
            'id' => $location->id,
            'type' => $location->type,
            'typeLabel' => PhysicalLocation::TYPE_LABELS[$location->type] ?? $location->type,
            'code' => $location->code,
            'name' => $location->name,
            'is_active' => $location->is_active,
            'children' => $this->buildTree($locations, $location->id),
        ])->values()->all();
    }

    public function createNew(?int $parentId = null): void
    {
        abort_unless(auth()->user()->can('archive.create'), 403);

        $this->resetValidation();
        $this->editingId = null;
        $this->parentId = $parentId;
        $this->code = '';
        $this->name = '';
        $this->description = '';
        $this->isActive = true;
        $this->orderColumn = 0;

        $parent = $parentId ? PhysicalLocation::find($parentId) : null;
        $this->type = PhysicalLocation::nextType($parent?->type) ?? '';
        $this->parentLabel = $parent ? $parent->breadcrumbLabel() : '(Tingkat teratas - Gedung)';

        if ($this->type === '') {
            $this->addError('type', 'Folder tidak bisa memiliki sub-lokasi lagi.');

            return;
        }

        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        abort_unless(auth()->user()->can('archive.update'), 403);

        $location = PhysicalLocation::findOrFail($id);

        $this->resetValidation();
        $this->editingId = $location->id;
        $this->parentId = $location->parent_id;
        $this->type = $location->type;
        $this->code = (string) $location->code;
        $this->name = $location->name;
        $this->description = (string) $location->description;
        $this->isActive = $location->is_active;
        $this->orderColumn = $location->order_column;
        $this->parentLabel = $location->parent ? $location->parent->breadcrumbLabel() : '(Tingkat teratas - Gedung)';
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'orderColumn' => 'nullable|integer|min:0',
        ]);

        if ($this->editingId) {
            abort_unless(auth()->user()->can('archive.update'), 403);

            $location = PhysicalLocation::findOrFail($this->editingId);
            $location->update([
                'code' => $this->code !== '' ? $this->code : null,
                'name' => $this->name,
                'description' => $this->description !== '' ? $this->description : null,
                'is_active' => $this->isActive,
                'order_column' => $this->orderColumn ?: 0,
            ]);

            AuditLog::record('physical_location.updated', $location, 'Ubah lokasi fisik: ' . $location->breadcrumbLabel());
        } else {
            abort_unless(auth()->user()->can('archive.create'), 403);

            $location = PhysicalLocation::create([
                'parent_id' => $this->parentId,
                'type' => $this->type,
                'code' => $this->code !== '' ? $this->code : null,
                'name' => $this->name,
                'description' => $this->description !== '' ? $this->description : null,
                'is_active' => $this->isActive,
                'order_column' => $this->orderColumn ?: 0,
            ]);

            AuditLog::record('physical_location.created', $location, 'Buat lokasi fisik: ' . $location->breadcrumbLabel());
        }

        $this->showForm = false;
        $this->loadTree();

        session()->flash('status', 'Lokasi fisik berhasil disimpan.');
    }

    public function toggleActive(int $id): void
    {
        abort_unless(auth()->user()->can('archive.update'), 403);

        $location = PhysicalLocation::findOrFail($id);
        $location->update(['is_active' => ! $location->is_active]);
        $this->loadTree();
    }
}; ?>

<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Lokasi Arsip Fisik
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

            @can('archive.create')
                <div class="flex justify-end">
                    <button type="button" wire:click="createNew" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                        + Gedung Baru
                    </button>
                </div>
            @endcan

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-500 mb-4">
                    Struktur lokasi arsip fisik berjenjang: Gedung &rarr; Lantai &rarr; Ruangan &rarr; Rak &rarr; Box &rarr; Folder.
                    Arahkan kursor ke sebuah lokasi untuk melihat aksi (tambah sub-lokasi, ubah, lihat QR, aktif/nonaktif).
                </p>

                <x-physical-location-tree :nodes="$tree" />
            </div>
        </div>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-500/75" wire:click="closeForm"></div>

            <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-lg sm:mx-auto">
                <form wire:submit="save" class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-1">
                        {{ $editingId ? 'Ubah Lokasi' : 'Lokasi Baru' }}
                    </h3>
                    <p class="text-xs text-gray-500 mb-4">
                        Induk: {{ $parentLabel }}
                        @if ($type)
                            &middot; Jenis: <span class="font-semibold">{{ \App\Models\PhysicalLocation::TYPE_LABELS[$type] ?? $type }}</span>
                        @endif
                    </p>

                    @error('type')
                        <div class="mb-4 text-sm text-red-600">{{ $message }}</div>
                    @enderror

                    @if ($type)
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="name" value="Nama" />
                                <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('name')" class="mt-1" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="code" value="Kode (opsional)" />
                                    <x-text-input wire:model="code" id="code" type="text" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('code')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label for="orderColumn" value="Urutan" />
                                    <x-text-input wire:model="orderColumn" id="orderColumn" type="number" min="0" class="mt-1 block w-full" />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="description" value="Keterangan (opsional)" />
                                <textarea wire:model="description" id="description" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                            </div>

                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" wire:model="isActive" class="rounded border-gray-300 text-indigo-600 shadow-sm">
                                <span class="text-sm text-gray-700">Aktif</span>
                            </label>
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <button type="button" wire:click="closeForm" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                                Batal
                            </button>
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                                Simpan
                            </button>
                        </div>
                    @else
                        <div class="mt-6 flex justify-end">
                            <button type="button" wire:click="closeForm" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                                Tutup
                            </button>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    @endif
</div>
