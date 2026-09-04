<?php

use App\Models\Document;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

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
        $documents = Document::query()
            ->public()
            ->with('work')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('title', 'ilike', "%{$this->search}%")
                        ->orWhereHas('work', function ($w) {
                            $w->where('code', 'ilike', "%{$this->search}%")
                                ->orWhere('name', 'ilike', "%{$this->search}%");
                        });
                });
            })
            ->orderByDesc('created_at')
            ->paginate(12);

        return ['documents' => $documents];
    }
}; ?>

<x-slot name="header">
    <h1 class="text-xl font-semibold text-gray-800">Perpustakaan Digital Arsip Pekerjaan</h1>
    <p class="mt-1 text-sm text-gray-500">Cari dan lihat dokumen pekerjaan yang telah dipublikasikan untuk umum.</p>

    <div class="mt-4 max-w-lg">
        <input
            type="search"
            wire:model.live.debounce.400ms="search"
            placeholder="Cari judul dokumen, kode, atau nama pekerjaan..."
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
        >
    </div>
</x-slot>

<div class="space-y-4">
    @forelse ($documents as $document)
        <a href="{{ route('public.library.show', $document) }}" wire:navigate class="block bg-white shadow-sm rounded-lg p-5 hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-medium text-gray-900">{{ $document->title }}</h2>
                    <p class="mt-1 text-xs text-gray-400">{{ $document->work->code }} - {{ $document->work->name }}</p>
                    @if ($document->description)
                        <p class="mt-2 text-sm text-gray-500 line-clamp-2">{{ $document->description }}</p>
                    @endif
                </div>
                <span class="shrink-0 text-[10px] font-semibold uppercase tracking-wide rounded px-1.5 py-0.5 bg-emerald-100 text-emerald-700">Publik</span>
            </div>
        </a>
    @empty
        <div class="bg-white shadow-sm rounded-lg p-8 text-center text-gray-400 italic">
            @if ($search)
                Tidak ada dokumen publik yang cocok dengan pencarian "{{ $search }}".
            @else
                Belum ada dokumen yang dipublikasikan untuk umum.
            @endif
        </div>
    @endforelse

    <div>
        {{ $documents->links() }}
    </div>
</div>
