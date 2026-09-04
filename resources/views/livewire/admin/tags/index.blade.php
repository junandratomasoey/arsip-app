<?php

use App\Models\Tag;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public array $items = [];

    public ?int $editingId = null;
    public string $name = '';

    public bool $showForm = false;
    public ?int $confirmingDeleteId = null;
    public string $deleteError = '';

    public function mount(): void
    {
        $this->loadItems();
    }

    public function loadItems(): void
    {
        $this->items = Tag::orderBy('name')->get()
            ->map(fn ($t) => [
                'id' => $t->id, 'name' => $t->name, 'slug' => $t->slug,
                'works_count' => $t->works()->count(),
                'documents_count' => $t->documents()->count(),
            ])->all();
    }

    public function createNew(): void
    {
        $this->reset(['editingId', 'name', 'deleteError']);
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $item = Tag::findOrFail($id);
        $this->editingId = $item->id;
        $this->name = $item->name;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:100|unique:tags,name,' . ($this->editingId ?? 'NULL') . ',id',
        ]);

        if ($this->editingId) {
            Tag::findOrFail($this->editingId)->update(['name' => $this->name]);
        } else {
            Tag::create(['name' => $this->name]);
        }

        $this->showForm = false;
        $this->loadItems();
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
        $this->deleteError = '';
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
        $this->deleteError = '';
    }

    public function delete(): void
    {
        $item = Tag::findOrFail($this->confirmingDeleteId);

        if ($item->works()->exists() || $item->documents()->exists()) {
            $this->deleteError = 'Tidak bisa menghapus tag yang masih dipakai oleh data pekerjaan atau dokumen.';

            return;
        }

        $item->delete();
        $this->confirmingDeleteId = null;
        $this->loadItems();
    }
}; ?>

<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tags</h2>
</x-slot>

<div>
<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <div class="flex justify-end">
            <button type="button" wire:click="createNew" class="inline-flex items-center px-4 py-2 bg-pu-navy border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-pu-navy-700">
                + Tambah
            </button>
        </div>
        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Nama</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Slug</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Dipakai di</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-500 uppercase text-xs">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($items as $item)
                        <tr>
                            <td class="px-4 py-2 text-gray-900">{{ $item['name'] }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $item['slug'] }}</td>
                            <td class="px-4 py-2 text-gray-500">
                                {{ $item['works_count'] }} pekerjaan, {{ $item['documents_count'] }} dokumen
                            </td>
                            <td class="px-4 py-2 text-right space-x-3 text-xs">
                                <button type="button" wire:click="edit({{ $item['id'] }})" class="text-pu-navy-600 hover:underline">ubah</button>
                                <button type="button" wire:click="confirmDelete({{ $item['id'] }})" class="text-red-600 hover:underline">hapus</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-400 italic">Belum ada tag.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if ($showForm)
    <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
        <div class="fixed inset-0 bg-gray-500/75" wire:click="closeForm"></div>

        <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-md sm:mx-auto">
            <form wire:submit="save" class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    {{ $editingId ? 'Ubah Tag' : 'Tag Baru' }}
                </h3>

                <div class="space-y-4">
                    <div>
                        <x-input-label for="name" value="Nama" />
                        <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="closeForm" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-pu-navy border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-pu-navy-700">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

@if ($confirmingDeleteId)
    <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
        <div class="fixed inset-0 bg-gray-500/75" wire:click="cancelDelete"></div>

        <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-md sm:mx-auto p-6">
            <h3 class="text-lg font-medium text-gray-900">Hapus tag ini?</h3>
            <p class="mt-2 text-sm text-gray-500">Tindakan ini tidak bisa dibatalkan.</p>

            @if ($deleteError)
                <p class="mt-3 text-sm text-red-600">{{ $deleteError }}</p>
            @endif

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" wire:click="cancelDelete" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                    Batal
                </button>
                <button type="button" wire:click="delete" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">
                    Hapus
                </button>
            </div>
        </div>
    </div>
@endif
</div>

