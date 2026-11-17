<?php

use App\Models\Work;
use App\Models\WorkType;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

/**
 * Beranda Perpustakaan Digital Publik: daftar PEKERJAAN yang visibilitasnya
 * "Publik" (dipilih di form Pekerjaan). Dokumennya sendiri ada di halaman
 * pekerjaan (public.library.work) dan hanya yang bervisibilitas publik.
 * Hanya data umum pekerjaan (nama, jenis, tahun, lokasi) yang dimunculkan
 * - bukan kontrak/penyedia jasa.
 *
 * Kotak cari & filter sengaja berada di badan halaman, BUKAN di
 * <x-slot name="header">: isi slot header dirender di luar elemen akar
 * komponen Livewire sehingga wire:model di sana tidak pernah bekerja.
 */
new #[Layout('layouts.public')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'jenis', history: true)]
    public string $workTypeId = '';

    #[Url(as: 'tahun', history: true)]
    public string $fiscalYear = '';

    #[Url(as: 'lokasi', history: true)]
    public string $regency = '';

    #[Url(as: 'urut', history: true)]
    public string $sort = 'terbaru';

    public function updated($property): void
    {
        if (in_array($property, ['search', 'workTypeId', 'fiscalYear', 'regency', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'workTypeId', 'fiscalYear', 'regency', 'sort']);
        $this->resetPage();
    }

    public function with(): array
    {
        $search = trim($this->search);

        // Pilihan filter hanya diambil dari pekerjaan publik, supaya nilai
        // milik pekerjaan internal (tahun, lokasi, jenis) tidak ikut terlihat.
        $workTypeOptions = WorkType::whereIn('id', Work::public()->whereNotNull('work_type_id')->select('work_type_id'))
            ->orderBy('name')->pluck('name', 'id');
        $fiscalYearOptions = Work::public()->whereNotNull('fiscal_year')
            ->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year');
        $regencyOptions = Work::public()->whereNotNull('regency')->where('regency', '!=', '')
            ->distinct()->orderBy('regency')->pluck('regency');

        $works = Work::query()
            ->public()
            ->with('workType')
            ->withCount('publicDocuments')
            ->when($search !== '', function ($query) use ($search) {
                // Cocokkan nama/kode pekerjaan ATAU judul dokumen publiknya,
                // supaya pencarian "DED" tetap menemukan pekerjaannya.
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhereHas('publicDocuments', fn ($d) => $d->where('title', 'like', "%{$search}%"));
                })->with(['publicDocuments' => fn ($d) => $d->where('title', 'like', "%{$search}%")->orderBy('title')]);
            })
            ->when($this->workTypeId !== '', fn ($q) => $q->where('work_type_id', (int) $this->workTypeId))
            ->when($this->fiscalYear !== '', fn ($q) => $q->where('fiscal_year', (int) $this->fiscalYear))
            ->when($this->regency !== '', fn ($q) => $q->where('regency', $this->regency))
            ->when($this->sort === 'nama', fn ($q) => $q->orderBy('name'))
            ->when($this->sort === 'dokumen', fn ($q) => $q->orderByDesc('public_documents_count')->orderBy('name'))
            ->when(! in_array($this->sort, ['nama', 'dokumen'], true), fn ($q) => $q->orderByDesc('fiscal_year')->orderBy('name'))
            ->paginate(20);

        return [
            'works' => $works,
            'searching' => $search !== '',
            'filtering' => $search !== '' || $this->workTypeId !== '' || $this->fiscalYear !== '' || $this->regency !== '',
            'workTypeOptions' => $workTypeOptions,
            'fiscalYearOptions' => $fiscalYearOptions,
            'regencyOptions' => $regencyOptions,
        ];
    }
}; ?>

<x-slot name="header">
    <h1 class="text-xl font-semibold text-gray-800">Perpustakaan Digital Arsip Pekerjaan</h1>
    <p class="mt-1 text-sm text-gray-500">Pilih pekerjaan untuk melihat dokumen yang telah dipublikasikan untuk umum.</p>
</x-slot>

<div class="space-y-6">
    <div class="bg-white shadow-sm rounded-lg p-4">
        <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
            <div class="col-span-2">
                <label for="search" class="block text-xs font-medium text-gray-500">Cari</label>
                <input
                    type="search"
                    id="search"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Nama pekerjaan, kode, atau judul dokumen..."
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-pu-navy-500 focus:ring-pu-navy-500 text-sm"
                >
            </div>
            <div>
                <label for="workTypeId" class="block text-xs font-medium text-gray-500">Jenis Pekerjaan</label>
                <select id="workTypeId" wire:model.live="workTypeId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-pu-navy-500 focus:ring-pu-navy-500 text-sm">
                    <option value="">Semua jenis</option>
                    @foreach ($workTypeOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="fiscalYear" class="block text-xs font-medium text-gray-500">Tahun Anggaran</label>
                <select id="fiscalYear" wire:model.live="fiscalYear" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-pu-navy-500 focus:ring-pu-navy-500 text-sm">
                    <option value="">Semua tahun</option>
                    @foreach ($fiscalYearOptions as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="regency" class="block text-xs font-medium text-gray-500">Kabupaten/Kota</label>
                <select id="regency" wire:model.live="regency" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-pu-navy-500 focus:ring-pu-navy-500 text-sm">
                    <option value="">Semua lokasi</option>
                    @foreach ($regencyOptions as $name)
                        <option value="{{ $name }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="sort" class="block text-xs font-medium text-gray-500">Urutkan</label>
                <select id="sort" wire:model.live="sort" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-pu-navy-500 focus:ring-pu-navy-500 text-sm">
                    <option value="terbaru">Tahun terbaru</option>
                    <option value="nama">Nama A-Z</option>
                    <option value="dokumen">Dokumen terbanyak</option>
                </select>
            </div>
        </div>

        <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
            <span>{{ $works->total() }} pekerjaan</span>
            @if ($filtering)
                <button type="button" wire:click="resetFilters" class="text-pu-navy-600 hover:underline">Hapus filter</button>
            @endif
        </div>
    </div>

    @if ($works->isEmpty())
        <div class="bg-white shadow-sm rounded-lg p-8 text-center text-gray-400 italic">
            @if ($filtering)
                Tidak ada pekerjaan yang cocok dengan pencarian atau filter ini.
            @else
                Belum ada pekerjaan yang dipublikasikan untuk umum.
            @endif
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            @foreach ($works as $work)
                <div wire:key="work-{{ $work->id }}" class="bg-white shadow-sm rounded-lg overflow-hidden flex flex-col hover:shadow-md transition-shadow">
                    <a href="{{ route('public.library.work', $work) }}" wire:navigate class="relative block aspect-[3/4] overflow-hidden bg-pu-navy-50">
                        @if ($work->coverUrl())
                            <img src="{{ $work->coverUrl() }}" alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
                        @else
                            <div class="absolute inset-0 flex items-center justify-center text-pu-navy-200 text-xs">Tanpa cover</div>
                        @endif
                    </a>

                    <div class="p-3 flex-1 flex flex-col">
                        <p class="text-[11px] text-gray-400 truncate">
                            {{ $work->code }}@if ($work->fiscal_year) &middot; TA {{ $work->fiscal_year }}@endif
                        </p>
                        <h2 class="mt-1 text-sm font-medium text-gray-900 leading-snug line-clamp-3">
                            <a href="{{ route('public.library.work', $work) }}" wire:navigate class="hover:text-pu-navy-600">{{ $work->name }}</a>
                        </h2>

                        @if ($work->workType || $work->regency)
                            <p class="mt-1 text-[11px] text-gray-500 line-clamp-2">
                                {{ collect([$work->workType?->name, $work->regency])->filter()->implode(' · ') }}
                            </p>
                        @endif

                        @if ($searching && $work->publicDocuments->isNotEmpty())
                            <ul class="mt-2 space-y-1 border-t border-gray-100 pt-2">
                                @foreach ($work->publicDocuments->take(3) as $document)
                                    <li class="text-[11px] truncate">
                                        <a href="{{ route('public.library.show', $document) }}" wire:navigate class="text-pu-navy-600 hover:underline">{{ $document->title }}</a>
                                    </li>
                                @endforeach
                                @if ($work->publicDocuments->count() > 3)
                                    <li class="text-[11px] text-gray-400">+{{ $work->publicDocuments->count() - 3 }} dokumen lain</li>
                                @endif
                            </ul>
                        @endif

                        <div class="mt-auto pt-3">
                            <span @class(['text-[11px] font-medium rounded px-1.5 py-0.5', 'bg-emerald-100 text-emerald-700' => $work->public_documents_count > 0, 'bg-gray-100 text-gray-500' => $work->public_documents_count === 0])>{{ $work->public_documents_count }} dokumen publik</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div>
            {{ $works->links() }}
        </div>
    @endif
</div>
