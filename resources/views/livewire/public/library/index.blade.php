<?php

use App\Models\Work;
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
 */
new #[Layout('layouts.public')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $search = trim($this->search);

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
            ->orderByDesc('fiscal_year')
            ->orderBy('name')
            ->paginate(12);

        return ['works' => $works, 'searching' => $search !== ''];
    }
}; ?>

<x-slot name="header">
    <h1 class="text-xl font-semibold text-gray-800">Perpustakaan Digital Arsip Pekerjaan</h1>
    <p class="mt-1 text-sm text-gray-500">Pilih pekerjaan untuk melihat dokumen yang telah dipublikasikan untuk umum.</p>

    <div class="mt-4 max-w-lg">
        <input
            type="search"
            wire:model.live.debounce.400ms="search"
            placeholder="Cari nama pekerjaan, kode, atau judul dokumen..."
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-pu-navy-500 focus:ring-pu-navy-500 text-sm"
        >
    </div>
</x-slot>

<div class="space-y-6">
    @if ($works->isEmpty())
        <div class="bg-white shadow-sm rounded-lg p-8 text-center text-gray-400 italic">
            @if ($searching)
                Tidak ada pekerjaan atau dokumen publik yang cocok dengan pencarian "{{ $search }}".
            @else
                Belum ada pekerjaan yang dipublikasikan untuk umum.
            @endif
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ($works as $work)
                <div wire:key="work-{{ $work->id }}" class="bg-white shadow-sm rounded-lg overflow-hidden flex flex-col hover:shadow-md transition-shadow">
                    <a href="{{ route('public.library.work', $work) }}" wire:navigate class="block">
                        @if ($work->coverUrl())
                            <img src="{{ $work->coverUrl() }}" alt="" class="w-full h-40 object-cover">
                        @else
                            <div class="w-full h-40 bg-pu-navy-50 flex items-center justify-center text-pu-navy-200 text-sm">Tanpa cover</div>
                        @endif
                    </a>

                    <div class="p-4 flex-1 flex flex-col">
                        <p class="text-xs text-gray-400">
                            {{ $work->code }}@if ($work->fiscal_year) &middot; TA {{ $work->fiscal_year }}@endif
                        </p>
                        <h2 class="mt-1 font-medium text-gray-900 leading-snug">
                            <a href="{{ route('public.library.work', $work) }}" wire:navigate class="hover:text-pu-navy-600">{{ $work->name }}</a>
                        </h2>

                        @if ($work->workType || $work->regency)
                            <p class="mt-1 text-xs text-gray-500">
                                {{ collect([$work->workType?->name, $work->regency])->filter()->implode(' · ') }}
                            </p>
                        @endif

                        @if ($searching && $work->publicDocuments->isNotEmpty())
                            <ul class="mt-3 space-y-1 border-t border-gray-100 pt-3">
                                @foreach ($work->publicDocuments->take(3) as $document)
                                    <li class="text-xs">
                                        <a href="{{ route('public.library.show', $document) }}" wire:navigate class="text-pu-navy-600 hover:underline">{{ $document->title }}</a>
                                    </li>
                                @endforeach
                                @if ($work->publicDocuments->count() > 3)
                                    <li class="text-xs text-gray-400">+{{ $work->publicDocuments->count() - 3 }} dokumen lain yang cocok</li>
                                @endif
                            </ul>
                        @endif

                        <div class="mt-auto pt-4 flex items-center justify-between">
                            <span @class(['text-xs font-medium rounded px-2 py-0.5', 'bg-emerald-100 text-emerald-700' => $work->public_documents_count > 0, 'bg-gray-100 text-gray-500' => $work->public_documents_count === 0])>{{ $work->public_documents_count }} dokumen publik</span>
                            <a href="{{ route('public.library.work', $work) }}" wire:navigate class="text-sm text-pu-navy-600 hover:underline">Lihat dokumen &rarr;</a>
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
