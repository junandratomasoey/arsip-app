<?php

use App\Models\OrganizationalUnit;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public array $tree = [];

    public ?int $editingId = null;
    public ?int $parentId = null;
    public string $type = 'bidang';
    public string $code = '';
    public string $name = '';
    public bool $isActive = true;
    public int $orderColumn = 0;

    public bool $showForm = false;
    public ?int $confirmingDeleteId = null;
    public string $deleteError = '';

    public array $unitTypes = [
        'organisasi' => 'Organisasi',
        'bagian' => 'Bagian',
        'bidang' => 'Bidang',
        'satker' => 'Satker',
        'ppk' => 'PPK',
        'unit' => 'Lainnya',
    ];

    public function mount(): void
    {
        $this->loadTree();
    }

    public function loadTree(): void
    {
        $units = OrganizationalUnit::orderBy('order_column')->get();
        $this->tree = $this->buildTree($units, null);
    }

    protected function buildTree($units, ?int $parentId): array
    {
        return $units->where('parent_id', $parentId)->map(fn ($u) => [
            'id' => $u->id,
            'type' => $u->type,
            'code' => $u->code,
            'name' => $u->name,
            'is_active' => $u->is_active,
            'order_column' => $u->order_column,
            'children' => $this->buildTree($units, $u->id),
        ])->values()->all();
    }

    public function flatOptions(): array
    {
        return OrganizationalUnit::orderBy('name')->get(['id', 'name', 'type'])
            ->reject(fn ($u) => $u->id === $this->editingId)
            ->map(fn ($u) => ['id' => $u->id, 'label' => "{$u->name} ({$u->type})"])
            ->values()->all();
    }

    public function createNew(?int $parentId = null): void
    {
        $this->reset(['editingId', 'code', 'name', 'deleteError']);
        $this->parentId = $parentId;
        $this->type = 'bidang';
        $this->isActive = true;
        $this->orderColumn = 0;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $unit = OrganizationalUnit::findOrFail($id);
        $this->editingId = $unit->id;
        $this->parentId = $unit->parent_id;
        $this->type = $unit->type;
        $this->code = (string) $unit->code;
        $this->name = $unit->name;
        $this->isActive = $unit->is_active;
        $this->orderColumn = $unit->order_column;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
    }

    protected function isDescendant(int $candidateParentId, int $unitId): bool
    {
        $current = OrganizationalUnit::find($candidateParentId);

        while ($current) {
            if ($current->id === $unitId) {
                return true;
            }
            $current = $current->parent_id ? OrganizationalUnit::find($current->parent_id) : null;
        }

        return false;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:30',
            'code' => 'nullable|string|max:50',
            'parentId' => 'nullable|exists:organizational_units,id',
            'orderColumn' => 'integer|min:0',
        ]);

        if ($this->editingId && $this->parentId) {
            if ($this->parentId === $this->editingId || $this->isDescendant($this->parentId, $this->editingId)) {
                $this->addError('parentId', 'Tidak bisa memindahkan unit ke bawah dirinya sendiri atau anaknya sendiri.');

                return;
            }
        }

        $data = [
            'parent_id' => $this->parentId,
            'type' => $this->type,
            'code' => $this->code !== '' ? $this->code : null,
            'name' => $this->name,
            'is_active' => $this->isActive,
            'order_column' => $this->orderColumn,
        ];

        if ($this->editingId) {
            OrganizationalUnit::findOrFail($this->editingId)->update($data);
        } else {
            OrganizationalUnit::create($data);
        }

        $this->showForm = false;
        $this->loadTree();
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
        $unit = OrganizationalUnit::findOrFail($this->confirmingDeleteId);

        if ($unit->children()->exists()) {
            $this->deleteError = 'Tidak bisa menghapus unit yang masih memiliki sub-unit. Pindahkan atau hapus sub-unit terlebih dahulu.';

            return;
        }

        if (
            $unit->users()->exists()
            || $unit->worksAsSatker()->exists()
            || $unit->worksAsPpk()->exists()
            || $unit->worksAsBidang()->exists()
        ) {
            $this->deleteError = 'Tidak bisa menghapus unit yang masih dipakai oleh pengguna atau data pekerjaan.';

            return;
        }

        $unit->delete();
        $this->confirmingDeleteId = null;
        $this->loadTree();
    }

    public function toggleActive(int $id): void
    {
        $unit = OrganizationalUnit::findOrFail($id);
        $unit->update(['is_active' => ! $unit->is_active]);
        $this->loadTree();
    }
}; ?>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Struktur Organisasi
        </h2>
    </x-slot>

<div>
    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex justify-end">
                <button type="button" wire:click="createNew" class="inline-flex items-center px-4 py-2 bg-pu-navy border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-pu-navy-700">
                    + Unit Baru
                </button>
            </div>
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-500 mb-4">
                    Kelola struktur organisasi (organisasi, bagian, bidang, satker, PPK) secara berjenjang.
                    Arahkan kursor ke sebuah unit untuk melihat aksi (tambah sub-unit, ubah, aktif/nonaktif, hapus).
                </p>

                <x-organizational-unit-tree :nodes="$tree" />
            </div>
        </div>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-500/75" wire:click="closeForm"></div>

            <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-lg sm:mx-auto">
                <form wire:submit="save" class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        {{ $editingId ? 'Ubah Unit' : 'Unit Baru' }}
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <x-input-label for="name" value="Nama Unit" />
                            <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('name')" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="type" value="Tipe" />
                                <select wire:model="type" id="type" class="mt-1 block w-full border-gray-300 focus:border-pu-navy-500 focus:ring-pu-navy-500 rounded-md shadow-sm">
                                    @foreach ($unitTypes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('type')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label for="code" value="Kode (opsional)" />
                                <x-text-input wire:model="code" id="code" type="text" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('code')" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="parentId" value="Induk Unit" />
                            <select wire:model="parentId" id="parentId" class="mt-1 block w-full border-gray-300 focus:border-pu-navy-500 focus:ring-pu-navy-500 rounded-md shadow-sm">
                                <option value="">— Tidak ada (unit tingkat teratas) —</option>
                                @foreach ($this->flatOptions() as $option)
                                    <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('parentId')" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="orderColumn" value="Urutan" />
                                <x-text-input wire:model="orderColumn" id="orderColumn" type="number" min="0" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('orderColumn')" class="mt-1" />
                            </div>

                            <div class="flex items-end pb-1">
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" wire:model="isActive" class="rounded border-gray-300 text-pu-navy-600 shadow-sm">
                                    <span class="text-sm text-gray-700">Aktif</span>
                                </label>
                            </div>
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
                <h3 class="text-lg font-medium text-gray-900">Hapus unit ini?</h3>
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

