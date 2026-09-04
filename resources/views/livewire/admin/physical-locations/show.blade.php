<?php

use App\Models\PhysicalLocation;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public PhysicalLocation $location;

    public function mount(PhysicalLocation $location): void
    {
        abort_unless(auth()->user()->can('archive.view'), 403);

        $this->location = $location;
    }

    public function with(): array
    {
        $documents = $this->location->documents()
            ->with('work')
            ->orderByDesc('physical_stored_at')
            ->get();

        return ['documents' => $documents];
    }
}; ?>

<x-slot name="header">
    <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Lokasi: {{ $location->name }}
        </h2>
        <a href="{{ route('admin.physical-locations.index') }}" wire:navigate class="text-sm text-gray-500 hover:underline">
            &larr; Kembali ke daftar lokasi
        </a>
    </div>
</x-slot>

<div>
    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow-sm sm:rounded-lg p-6" id="qr-print-area">
                <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">{{ \App\Models\PhysicalLocation::TYPE_LABELS[$location->type] ?? $location->type }}</p>
                <p class="text-sm text-gray-600 mb-4">{{ $location->breadcrumbLabel() }}</p>

                @if ($location->description)
                    <p class="text-sm text-gray-500 mb-4">{{ $location->description }}</p>
                @endif

                <div class="flex items-center gap-6">
                    <div class="border border-gray-200 rounded-lg p-3 inline-block">
                        {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(180)->generate(url()->current()) !!}
                    </div>
                    <div class="text-sm text-gray-500">
                        <p>Pindai QR ini untuk membuka halaman lokasi ini dan melihat daftar dokumen yang tersimpan di sini.</p>
                        @if ($location->code)
                            <p class="mt-2">Kode: <span class="font-semibold text-gray-700">{{ $location->code }}</span></p>
                        @endif
                    </div>
                </div>

                <div class="mt-4 print:hidden">
                    <button type="button" onclick="window.print()" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                        Cetak label
                    </button>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden print:hidden">
                <div class="p-4 border-b border-gray-100">
                    <h3 class="font-medium text-gray-900">Dokumen di lokasi ini ({{ $documents->count() }})</h3>
                </div>

                @forelse ($documents as $document)
                    <div class="p-4 border-b border-gray-100 last:border-b-0">
                        <p class="text-sm text-gray-900">{{ $document->title }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $document->work->code }} - {{ $document->work->name }}
                            @if ($document->physical_code)
                                &middot; kode fisik: {{ $document->physical_code }}
                            @endif
                            @if ($document->physical_stored_at)
                                &middot; disimpan: {{ $document->physical_stored_at->format('d-m-Y') }}
                            @endif
                        </p>
                    </div>
                @empty
                    <div class="p-4 text-sm text-gray-400 italic">Belum ada dokumen di lokasi ini.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    @media print {
        body * { visibility: hidden; }
        #qr-print-area, #qr-print-area * { visibility: visible; }
        #qr-print-area { position: absolute; top: 0; left: 0; }
    }
</style>
@endpush
