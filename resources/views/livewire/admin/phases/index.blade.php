<?php

use App\Models\Phase;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public array $items = [];

    public ?int $editingId = null;
    public string $code = '';
    public string $name = '';
    public bool $isActive = true;
    public int $orderColumn = 0;

    public bool $showForm = false;
    public ?int $confirmingDeleteId = null;
    public string $deleteError = '';

    public function mount(): void
    {
        $this->loadItems();
    }

    public function loadItems(): void
    {
        $this->items = Phase::orderBy('order_column')->get()
            ->map(fn ($p) => [
                'id' => $p->id, 'code' => $p->code, 'name' => $p->name,
                'is_active' => $p->is_active, 'order_column' => $p->order_column,
            ])->all();
    }

    public function createNew(): void
    {
        $this->reset(['editingId', 'code', 'name', 'deleteError']);
        $this->isActive = true;
        $this->orderColumn = 0;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $item = Phase::findOrFail($id);
        $this->editingId = $item->id;
        $this->code = $item->code;
        $this->name = $item->name;
        $this->isActive = $item->is_active;
        $this->orderColumn = $item->order_column;
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
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:30|unique:phases,code,' . ($this->editingId ?? 'NULL') . ',id',
            'orderColumn' => 'integer|min:0',
        ]);

        $data = [
            'code' => $this->code,
            'name' => $this->name,
            'is_active' => $this->isActive,
            'order_column' => $this->orderColumn,
        ];

        if ($this->editingId) {
            Phase::findOrFail($this->editingId)->update($data);
        } else {
            Phase::create($data);
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
        $item = Phase::findOrFail($this->confirmingDeleteId);

        if ($item->documents()->exists()) {
            $this->deleteError = 'Tidak bisa menghapus fase yang masih dipakai oleh data dokumen. Nonaktifkan saja kalau tidak dipakai lagi.';

            return;
        }

        $item->delete();
        $this->confirmingDeleteId = null;
        $this->loadItems();
    }

    public function toggleActive(int $id): void
    {
        $item = Phase::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
        $this->loadItems();
    }
}; ?>

@include('components.simple-master-crud', ['title' => 'Fase', 'items' => $items])
