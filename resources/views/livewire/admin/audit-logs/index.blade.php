<?php

use App\Models\AuditLog;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $filterUserId = null;
    public string $filterAction = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    public array $userOptions = [];
    public array $actionOptions = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('audit.view'), 403);

        $this->userOptions = User::orderBy('name')->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all();

        $this->actionOptions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->all();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterUserId(): void
    {
        $this->resetPage();
    }

    public function updatingFilterAction(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filterUserId', 'filterAction', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function with(): array
    {
        $logs = AuditLog::query()
            ->with('user')
            ->when($this->search, fn ($q) => $q->where('description', 'ilike', "%{$this->search}%"))
            ->when($this->filterUserId, fn ($q) => $q->where('user_id', $this->filterUserId))
            ->when($this->filterAction, fn ($q) => $q->where('action', $this->filterAction))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->orderByDesc('created_at')
            ->paginate(25);

        return ['logs' => $logs];
    }
}; ?>

<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Log Audit
    </h2>
</x-slot>

<div class="py-12">
    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

        <div class="bg-white shadow-sm sm:rounded-lg p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <div>
                    <x-input-label for="search" value="Cari deskripsi" />
                    <x-text-input wire:model.live.debounce.400ms="search" id="search" class="mt-1 block w-full" placeholder="mis. nama dokumen..." />
                </div>
                <div>
                    <x-input-label for="filterUserId" value="Pengguna" />
                    <x-select-input wire:model.live="filterUserId" id="filterUserId" class="mt-1 block w-full">
                        <option value="">Semua</option>
                        @foreach ($userOptions as $user)
                            <option value="{{ $user['id'] }}">{{ $user['name'] }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label for="filterAction" value="Aksi" />
                    <x-select-input wire:model.live="filterAction" id="filterAction" class="mt-1 block w-full">
                        <option value="">Semua</option>
                        @foreach ($actionOptions as $action)
                            <option value="{{ $action }}">{{ $action }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label for="dateFrom" value="Dari tanggal" />
                    <x-text-input wire:model.live="dateFrom" id="dateFrom" type="date" class="mt-1 block w-full" />
                </div>
                <div>
                    <x-input-label for="dateTo" value="Sampai tanggal" />
                    <x-text-input wire:model.live="dateTo" id="dateTo" type="date" class="mt-1 block w-full" />
                </div>
            </div>
            <div class="mt-3">
                <button type="button" wire:click="resetFilters" class="text-xs text-gray-500 hover:underline">Bersihkan filter</button>
            </div>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Waktu</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Pengguna</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Aksi</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Deskripsi</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="px-4 py-2 text-gray-500 whitespace-nowrap align-top">{{ $log->created_at->format('d-m-Y H:i') }}</td>
                            <td class="px-4 py-2 text-gray-800 align-top whitespace-nowrap">{{ $log->user?->name ?? 'Publik/Tamu' }}</td>
                            <td class="px-4 py-2 align-top whitespace-nowrap">
                                <span class="text-[10px] font-semibold uppercase tracking-wide rounded px-1.5 py-0.5 bg-gray-100 text-gray-600">{{ $log->action }}</span>
                            </td>
                            <td class="px-4 py-2 text-gray-700 align-top">
                                {{ $log->description }}
                                @if ($log->properties)
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        @foreach ($log->properties as $key => $value)
                                            {{ $key }}: {{ is_scalar($value) ? $value : json_encode($value) }}@if (! $loop->last), @endif
                                        @endforeach
                                    </p>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-400 align-top whitespace-nowrap">{{ $log->ip_address ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-400 italic">Tidak ada entri log audit yang cocok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $logs->links() }}
        </div>
    </div>
</div>
