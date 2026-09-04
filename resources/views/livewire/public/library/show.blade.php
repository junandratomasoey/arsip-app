<?php

use App\Models\Document;
use App\Models\Loan;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.public')] class extends Component
{
    public Document $document;

    public bool $showBorrowForm = false;

    public string $borrowerName = '';
    public string $borrowerInstansi = '';
    public string $borrowerContact = '';
    public string $purpose = '';

    // Honeypot - kolom tersembunyi dari pengguna asli, hanya diisi oleh bot.
    public string $website = '';

    public function mount(Document $document): void
    {
        abort_unless($document->visibility === Document::VISIBILITY_PUBLIC, 404);

        $this->document = $document;
    }

    public function with(): array
    {
        $this->document->loadMissing(['work.workType', 'phase', 'files.currentVersion']);

        return [];
    }

    public function formatBytes(?int $bytes): string
    {
        if (! $bytes) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, 1).' '.$units[$i];
    }

    public function startBorrow(): void
    {
        $this->resetValidation();
        $this->showBorrowForm = true;
        $this->borrowerName = '';
        $this->borrowerInstansi = '';
        $this->borrowerContact = '';
        $this->purpose = '';
        $this->website = '';
    }

    public function cancelBorrow(): void
    {
        $this->showBorrowForm = false;
    }

    public function saveBorrow(): void
    {
        // Honeypot terisi - diam-diam abaikan tanpa memberi tahu bot bahwa
        // pengajuannya ditolak.
        if ($this->website !== '') {
            $this->showBorrowForm = false;
            session()->flash('status', 'Pengajuan peminjaman berhasil dikirim, menunggu persetujuan Petugas Arsip.');

            return;
        }

        $this->validate([
            'borrowerName' => 'required|string|max:255',
            'borrowerInstansi' => 'nullable|string|max:255',
            'borrowerContact' => 'required|string|max:255',
            'purpose' => 'required|string|max:2000',
        ]);

        Loan::create([
            'document_id' => $this->document->id,
            'borrower_name' => $this->borrowerName,
            'borrower_instansi' => $this->borrowerInstansi !== '' ? $this->borrowerInstansi : null,
            'borrower_contact' => $this->borrowerContact,
            'purpose' => $this->purpose,
            'status' => Loan::STATUS_PENDING,
            'requested_by' => auth()->id(),
        ]);

        $this->showBorrowForm = false;
        session()->flash('status', 'Pengajuan peminjaman berhasil dikirim, menunggu persetujuan Petugas Arsip. Kami akan menghubungi Anda melalui kontak yang diberikan.');
    }
}; ?>

<div class="space-y-6">
    <div>
        <a href="{{ route('public.library.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali ke Perpustakaan</a>
    </div>

    @if (session('status'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-md px-4 py-3">
            {{ session('status') }}
        </div>
    @endif

    <div class="bg-white shadow-sm rounded-lg p-6">
        <div class="flex items-start justify-between gap-4">
            <h1 class="text-lg font-semibold text-gray-900">{{ $document->title }}</h1>
            <span class="shrink-0 text-[10px] font-semibold uppercase tracking-wide rounded px-1.5 py-0.5 bg-emerald-100 text-emerald-700">Publik</span>
        </div>

        @if ($document->description)
            <p class="mt-2 text-sm text-gray-600">{{ $document->description }}</p>
        @endif

        <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
            <div>
                <dt class="text-gray-400">Pekerjaan</dt>
                <dd class="text-gray-800">{{ $document->work->code }} - {{ $document->work->name }}</dd>
            </div>
            @if ($document->work->workType)
                <div>
                    <dt class="text-gray-400">Jenis Pekerjaan</dt>
                    <dd class="text-gray-800">{{ $document->work->workType->name }}</dd>
                </div>
            @endif
            @if ($document->phase)
                <div>
                    <dt class="text-gray-400">Fase</dt>
                    <dd class="text-gray-800">{{ $document->phase->name }}</dd>
                </div>
            @endif
            @if ($document->work->regency)
                <div>
                    <dt class="text-gray-400">Lokasi</dt>
                    <dd class="text-gray-800">{{ $document->work->regency }}, {{ $document->work->province }}</dd>
                </div>
            @endif
        </dl>
    </div>

    <div class="bg-white shadow-sm rounded-lg p-6">
        <h2 class="font-medium text-gray-900 mb-3">Berkas</h2>

        @if ($document->files->isEmpty())
            <p class="text-sm text-gray-400 italic">Belum ada berkas untuk dokumen ini.</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($document->files as $file)
                    <li class="py-3 flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $file->label }}</p>
                            @if ($file->currentVersion)
                                <p class="text-xs text-gray-400">
                                    {{ $file->currentVersion->original_filename }} - {{ $this->formatBytes($file->currentVersion->size_bytes) }}
                                </p>
                            @else
                                <p class="text-xs text-gray-400 italic">Belum ada berkas diunggah</p>
                            @endif
                        </div>
                        @if ($file->currentVersion)
                            <a href="{{ route('public.library.download', ['document' => $document, 'version' => $file->currentVersion->id]) }}" class="text-sm text-indigo-600 hover:underline whitespace-nowrap">unduh</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="bg-white shadow-sm rounded-lg p-6">
        <div class="flex items-center justify-between">
            <h2 class="font-medium text-gray-900">Peminjaman Dokumen</h2>
            @unless ($showBorrowForm)
                <button type="button" wire:click="startBorrow" class="text-sm text-indigo-600 hover:underline">Ajukan Peminjaman</button>
            @endunless
        </div>

        @if ($showBorrowForm)
            <form wire:submit="saveBorrow" class="mt-4 space-y-4">
                <div class="absolute -left-[9999px]" aria-hidden="true">
                    <label for="website">Website</label>
                    <input type="text" wire:model="website" id="website" tabindex="-1" autocomplete="off">
                </div>

                <div>
                    <x-input-label for="borrowerName" value="Nama Peminjam" />
                    <x-text-input wire:model="borrowerName" id="borrowerName" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('borrowerName')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="borrowerInstansi" value="Instansi (opsional)" />
                    <x-text-input wire:model="borrowerInstansi" id="borrowerInstansi" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('borrowerInstansi')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="borrowerContact" value="Kontak (telepon/email)" />
                    <x-text-input wire:model="borrowerContact" id="borrowerContact" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('borrowerContact')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="purpose" value="Keperluan" />
                    <textarea wire:model="purpose" id="purpose" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                    <x-input-error :messages="$errors->get('purpose')" class="mt-1" />
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" wire:click="cancelBorrow" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                        Kirim Pengajuan
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
