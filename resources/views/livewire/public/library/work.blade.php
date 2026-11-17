<?php

use App\Models\Work;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Halaman publik satu pekerjaan: info umum + daftar dokumen PUBLIK-nya,
 * dikelompokkan per fase. Pekerjaan yang visibilitasnya bukan "Publik"
 * dijawab 404 (bukan 403) supaya keberadaannya tidak bisa ditebak dari luar.
 */
new #[Layout('layouts.public')] class extends Component
{
    public Work $work;

    public function mount(Work $work): void
    {
        abort_unless($work->isPublic(), 404);

        $this->work = $work;
    }

    public function with(): array
    {
        $this->work->loadMissing(['workType', 'satkerUnit']);

        $documents = $this->work->publicDocuments()
            ->with('phase')
            ->withCount('files')
            ->orderBy('title')
            ->get();

        // Urut menurut urutan fase di master data; dokumen tanpa fase di akhir.
        $groups = $documents
            ->groupBy(fn ($document) => $document->phase?->name ?? '')
            ->sortBy(fn ($items, $name) => $name === '' ? PHP_INT_MAX : ($items->first()->phase->order_column ?? 0))
            ->all();

        return ['groups' => $groups, 'total' => $documents->count()];
    }
}; ?>

<div class="space-y-6">
    <div>
        <a href="{{ route('public.library.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali ke daftar pekerjaan</a>
    </div>

    <div class="bg-white shadow-sm rounded-lg overflow-hidden sm:flex">
        @if ($work->coverUrl())
            <img src="{{ $work->coverUrl() }}" alt="" class="w-full h-48 sm:w-56 sm:h-auto object-cover shrink-0">
        @endif

        <div class="p-6 flex-1">
            <p class="text-xs text-gray-400">{{ $work->code }}</p>
            <h1 class="mt-1 text-lg font-semibold text-gray-900">{{ $work->name }}</h1>

            <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                @if ($work->workType)
                    <div>
                        <dt class="text-gray-400">Jenis Pekerjaan</dt>
                        <dd class="text-gray-800">{{ $work->workType->name }}</dd>
                    </div>
                @endif
                @if ($work->fiscal_year)
                    <div>
                        <dt class="text-gray-400">Tahun Anggaran</dt>
                        <dd class="text-gray-800">{{ $work->fiscal_year }}</dd>
                    </div>
                @endif
                @if ($work->regency || $work->province)
                    <div>
                        <dt class="text-gray-400">Lokasi</dt>
                        <dd class="text-gray-800">{{ collect([$work->regency, $work->province])->filter()->implode(', ') }}</dd>
                    </div>
                @endif
                @if ($work->satkerUnit)
                    <div>
                        <dt class="text-gray-400">Satuan Kerja</dt>
                        <dd class="text-gray-800">{{ $work->satkerUnit->name }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>

    <div class="bg-white shadow-sm rounded-lg p-6">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-medium text-gray-900">Dokumen Publik</h2>
            <span class="text-xs font-medium rounded px-2 py-0.5 bg-emerald-100 text-emerald-700">{{ $total }} dokumen</span>
        </div>

        @if ($total === 0)
            <p class="mt-4 text-sm text-gray-400 italic">Belum ada dokumen yang dipublikasikan untuk pekerjaan ini.</p>
        @endif

        @foreach ($groups as $phaseName => $documents)
            <div class="mt-5">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $phaseName !== '' ? $phaseName : 'Tanpa fase' }}</h3>

                <ul class="mt-2 divide-y divide-gray-100 border-t border-gray-100">
                    @foreach ($documents as $document)
                        <li wire:key="document-{{ $document->id }}">
                            <a href="{{ route('public.library.show', $document) }}" wire:navigate class="flex items-start justify-between gap-4 py-3 group">
                                <div>
                                    <p class="text-sm font-medium text-gray-800 group-hover:text-pu-navy-600">{{ $document->title }}</p>
                                    @if ($document->description)
                                        <p class="mt-1 text-sm text-gray-500 line-clamp-2">{{ $document->description }}</p>
                                    @endif
                                </div>
                                <span class="shrink-0 text-xs text-gray-400 whitespace-nowrap">{{ $document->files_count }} berkas</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</div>
