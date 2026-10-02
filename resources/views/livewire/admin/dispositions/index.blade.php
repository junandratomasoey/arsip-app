<?php

use App\Models\LetterDisposition;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

/**
 * Kotak masuk disposisi milik pengguna yang sedang login. Tidak butuh
 * permission khusus: setiap pengguna hanya melihat disposisi untuk dirinya.
 */
new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $tab = 'open';

    public function updatingTab(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $base = LetterDisposition::query()->where('to_user_id', auth()->id());

        $dispositions = (clone $base)
            ->with(['letter', 'fromUser'])
            ->when($this->tab === 'open', fn ($q) => $q->open())
            ->when($this->tab === 'done', fn ($q) => $q->where('status', LetterDisposition::STATUS_DONE))
            ->orderByRaw("CASE WHEN status = 'baru' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->paginate(20);

        return [
            'dispositions' => $dispositions,
            'openCount' => (clone $base)->open()->count(),
        ];
    }
}; ?>

<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Disposisi Saya</h2>
</x-slot>

<div class="py-12">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <div class="flex gap-2">
            @foreach (['open' => 'Perlu Ditindaklanjuti', 'done' => 'Selesai', 'all' => 'Semua'] as $value => $label)
                <button type="button" wire:click="$set('tab', '{{ $value }}')" @class([
                    'px-3 py-1.5 rounded-md text-xs font-semibold uppercase tracking-widest',
                    'bg-pu-navy text-white' => $tab === $value,
                    'bg-white text-gray-600 border border-gray-300' => $tab !== $value,
                ])>
                    {{ $label }} @if ($value === 'open' && $openCount > 0) ({{ $openCount }}) @endif
                </button>
            @endforeach
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg divide-y divide-gray-100">
            @forelse ($dispositions as $d)
                <a href="{{ route('admin.letters.show', $d->letter_id) }}" wire:navigate wire:key="d-{{ $d->id }}" class="block px-5 py-4 hover:bg-gray-50">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm text-gray-900 {{ $d->status === 'baru' ? 'font-semibold' : '' }}">
                            {{ $d->letter?->subject ?? '(surat dihapus)' }}
                        </p>
                        <div class="flex items-center gap-2">
                            @if ($d->isOverdue())
                                <span class="text-[10px] font-semibold uppercase tracking-wide rounded px-1.5 py-0.5 bg-red-100 text-red-700">Lewat batas</span>
                            @endif
                            <span @class([
                                'text-[10px] font-semibold uppercase tracking-wide rounded px-1.5 py-0.5',
                                'bg-sky-100 text-sky-700' => $d->status === 'baru',
                                'bg-gray-100 text-gray-600' => $d->status === 'dibaca',
                                'bg-amber-100 text-amber-700' => $d->status === 'diproses',
                                'bg-emerald-100 text-emerald-700' => $d->status === 'selesai',
                            ])>{{ $d->statusLabel() }}</span>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ $d->letter?->letter_number ?? '-' }} · dari {{ $d->letter?->correspondent ?? '-' }}
                    </p>
                    <p class="text-sm text-gray-600 mt-2"><span class="text-gray-400">{{ $d->fromUser?->name ?? '-' }}:</span> {{ \Illuminate\Support\Str::limit($d->instruction, 160) }}</p>
                    <p class="text-xs text-gray-400 mt-1">
                        {{ $d->created_at->format('d-m-Y H:i') }}
                        @if ($d->due_date) · batas {{ $d->due_date->format('d-m-Y') }} @endif
                    </p>
                </a>
            @empty
                <p class="px-5 py-8 text-center text-sm text-gray-400 italic">Tidak ada disposisi.</p>
            @endforelse
        </div>

        <div>{{ $dispositions->links() }}</div>
    </div>
</div>
