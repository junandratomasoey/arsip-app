<?php

use App\Models\AuditLog;
use App\Models\OrganizationalUnit;
use App\Models\Work;
use App\Models\WorkStatus;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $filterSatkerId = null;
    public ?int $filterStatusId = null;
    public ?int $filterFiscalYear = null;

    public array $satkerOptions = [];
    public array $statusOptions = [];
    public array $fiscalYearOptions = [];

    public ?string $confirmingDeleteId = null;
    public string $deleteError = '';

    public function mount(): void
    {
        $this->satkerOptions = OrganizationalUnit::where('type', 'satker')->orderBy('name')->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all();

        $this->statusOptions = WorkStatus::orderBy('order_column')->get()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->all();

        $this->fiscalYearOptions = Work::query()->whereNotNull('fiscal_year')
            ->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year')->all();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSatkerId(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatusId(): void
    {
        $this->resetPage();
    }

    public function updatingFilterFiscalYear(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $works = Work::query()
            ->with(['workType', 'workStatus', 'satkerUnit', 'ppkUnit'])
            ->when($this->search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('code', 'ilike', "%{$this->search}%")
                ->orWhere('name', 'ilike', "%{$this->search}%")))
            ->when($this->filterSatkerId, fn ($q) => $q->where('satker_unit_id', $this->filterSatkerId))
            ->when($this->filterStatusId, fn ($q) => $q->where('work_status_id', $this->filterStatusId))
            ->when($this->filterFiscalYear, fn ($q) => $q->where('fiscal_year', $this->filterFiscalYear))
            ->orderByDesc('created_at')
            ->paginate(15);

        return ['works' => $works];
    }

    public function confirmDelete(string $id): void
    {
        abort_unless(auth()->user()->can('work.delete'), 403);
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
        abort_unless(auth()->user()->can('work.delete'), 403);

        $work = Work::findOrFail($this->confirmingDeleteId);

        if ($work->documents()->exists()) {
            $this->deleteError = 'Tidak bisa menghapus pekerjaan yang masih memiliki dokumen.';

            return;
        }

        AuditLog::record('work.deleted', $work, 'Hapus pekerjaan: ' . $work->code . ' - ' . $work->name);

        $work->delete();
        $this->confirmingDeleteId = null;
    }
}; ?>

<x-slot name="header">
    <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pekerjaan</h2>
        @can('work.create')
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.works.import') }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                    Impor Excel
                </a>
                <a href="{{ route('admin.works.create') }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    + Tambah Pekerjaan
                </a>
            </div>
        @endcan
    </div>
</x-slot>

<div>
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

        @if (session('status'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-md px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg p-4 grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div class="sm:col-span-2">
                <x-input-label for="search" value="Cari kode / nama pekerjaan" />
                <x-text-input wire:model.live.debounce.400ms="search" id="search" type="text" class="mt-1 block w-full" placeholder="Ketik untuk mencari..." />
            </div>

            <div>
                <x-input-label for="filterSatkerId" value="Satker" />
                <x-select-input wire:model.live="filterSatkerId" id="filterSatkerId" class="mt-1 block w-full">
                    <option value="">Semua Satker</option>
                    @foreach ($satkerOptions as $opt)
                        <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                    @endforeach
                </x-select-input>
            </div>

            <div>
                <x-input-label for="filterStatusId" value="Status" />
                <x-select-input wire:model.live="filterStatusId" id="filterStatusId" class="mt-1 block w-full">
                    <option value="">Semua Status</option>
                    @foreach ($statusOptions as $opt)
                        <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                    @endforeach
                </x-select-input>
            </div>

            <div>
                <x-input-label for="filterFiscalYear" value="Tahun Anggaran" />
                <x-select-input wire:model.live="filterFiscalYear" id="filterFiscalYear" class="mt-1 block w-full">
                    <option value="">Semua Tahun</option>
                    @foreach ($fiscalYearOptions as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </x-select-input>
            </div>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Kode</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Nama Pekerjaan</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Jenis</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Satker</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">PPK</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Tahun</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Status</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-500 uppercase text-xs">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($works as $work)
                        <tr>
                            <td class="px-4 py-2 text-gray-500 whitespace-nowrap">{{ $work->code }}</td>
                            <td class="px-4 py-2 text-gray-900">{{ $work->name }}</td>
                            <td class="px-4 py-2 text-gray-500 whitespace-nowrap">{{ $work->workType?->name ?? '-' }}</td>
                            <td class="px-4 py-2 text-gray-500 whitespace-nowrap">{{ $work->satkerUnit?->name ?? '-' }}</td>
                            <td class="px-4 py-2 text-gray-500 whitespace-nowrap">{{ $work->ppkUnit?->name ?? '-' }}</td>
                            <td class="px-4 py-2 text-gray-500 whitespace-nowrap">{{ $work->fiscal_year ?? '-' }}</td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                @if ($work->workStatus)
                                    <span class="text-[10px] font-semibold uppercase tracking-wide rounded px-1.5 py-0.5 bg-indigo-100 text-indigo-700">
                                        {{ $work->workStatus->name }}
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right space-x-3 text-xs whitespace-nowrap">
                                @can('document.view')
                                    <a href="{{ route('admin.works.documents', $work) }}" wire:navigate class="text-indigo-600 hover:underline">dokumen</a>
                                @endcan
                                <a href="{{ route('admin.works.locations', $work) }}" wire:navigate class="text-indigo-600 hover:underline">lokasi</a>
                                @can('work.update')
                                    <a href="{{ route('admin.works.edit', $work) }}" wire:navigate class="text-indigo-600 hover:underline">ubah</a>
                                @endcan
                                @can('work.delete')
                                    <button type="button" wire:click="confirmDelete('{{ $work->id }}')" class="text-red-600 hover:underline">hapus</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-6 text-center text-gray-400 italic">Belum ada data pekerjaan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $works->links() }}
        </div>
    </div>
</div>

@if ($confirmingDeleteId)
    <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
        <div class="fixed inset-0 bg-gray-500/75" wire:click="cancelDelete"></div>

        <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-md sm:mx-auto p-6">
            <h3 class="text-lg font-medium text-gray-900">Hapus pekerjaan ini?</h3>
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

