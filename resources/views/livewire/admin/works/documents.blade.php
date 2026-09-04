<?php

use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentFileVersion;
use App\Models\Loan;
use App\Models\Phase;
use App\Models\Work;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public Work $work;

    public array $phaseOptions = [];

    public bool $showDocumentForm = false;
    public string $title = '';
    public string $description = '';
    public ?int $phaseId = null;
    public string $visibility = 'internal';

    public ?string $confirmingDeleteDocumentId = null;

    public ?string $addFileToDocumentId = null;
    public string $newFileLabel = '';

    public ?string $uploadingForFileId = null;
    public $uploadFile = null;
    public string $uploadNotes = '';

    public array $expandedFileIds = [];

    public ?string $borrowingDocumentId = null;
    public string $borrowerName = '';
    public string $borrowerInstansi = '';
    public string $borrowerContact = '';
    public string $purpose = '';

    public function mount(Work $work): void
    {
        abort_unless(auth()->user()->can('work.view'), 403);

        $this->work = $work;

        $this->phaseOptions = Phase::where('is_active', true)->orderBy('order_column')->get()
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->all();
    }

    public function with(): array
    {
        $documents = $this->work->documents()
            ->with(['phase', 'files.currentVersion', 'files.versions.uploader'])
            ->with(['loans' => fn ($q) => $q
                ->where('requested_by', auth()->id())
                ->whereIn('status', [Loan::STATUS_PENDING, Loan::STATUS_APPROVED])
                ->latest()])
            ->orderByDesc('created_at')
            ->get();

        return ['documents' => $documents];
    }

    public function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '-';
        }

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / (1024 * 1024), 1) . ' MB';
    }

    public function createDocument(): void
    {
        abort_unless(auth()->user()->can('document.create'), 403);

        $this->reset(['title', 'description', 'phaseId']);
        $this->visibility = 'internal';
        $this->resetErrorBag();
        $this->showDocumentForm = true;
    }

    public function closeDocumentForm(): void
    {
        $this->showDocumentForm = false;
    }

    public function saveDocument(): void
    {
        abort_unless(auth()->user()->can('document.create'), 403);

        $this->validate([
            'title' => 'required|string|max:255',
            'phaseId' => 'nullable|exists:phases,id',
            'visibility' => 'required|in:public,internal,restricted,confidential',
        ]);

        $this->work->documents()->create([
            'phase_id' => $this->phaseId,
            'title' => $this->title,
            'description' => $this->description !== '' ? $this->description : null,
            'visibility' => $this->visibility,
            'created_by' => auth()->id(),
        ]);

        $this->showDocumentForm = false;
    }

    public function confirmDeleteDocument(string $id): void
    {
        abort_unless(auth()->user()->can('document.delete'), 403);
        $this->confirmingDeleteDocumentId = $id;
    }

    public function cancelDeleteDocument(): void
    {
        $this->confirmingDeleteDocumentId = null;
    }

    public function deleteDocument(): void
    {
        abort_unless(auth()->user()->can('document.delete'), 403);

        $document = Document::where('work_id', $this->work->id)->findOrFail($this->confirmingDeleteDocumentId);
        $document->delete();

        $this->confirmingDeleteDocumentId = null;
    }

    public function startAddFile(string $documentId): void
    {
        abort_unless(auth()->user()->can('document.update'), 403);

        $this->addFileToDocumentId = $documentId;
        $this->newFileLabel = '';
        $this->resetErrorBag();
    }

    public function cancelAddFile(): void
    {
        $this->addFileToDocumentId = null;
    }

    public function saveNewFile(): void
    {
        abort_unless(auth()->user()->can('document.update'), 403);

        $this->validate(['newFileLabel' => 'required|string|max:255']);

        $document = Document::where('work_id', $this->work->id)->findOrFail($this->addFileToDocumentId);
        $document->files()->create(['label' => $this->newFileLabel]);

        $this->addFileToDocumentId = null;
    }

    public function startUpload(string $documentFileId): void
    {
        abort_unless(auth()->user()->can('document.update'), 403);

        $this->uploadingForFileId = $documentFileId;
        $this->uploadFile = null;
        $this->uploadNotes = '';
        $this->resetErrorBag();
    }

    public function cancelUpload(): void
    {
        $this->uploadingForFileId = null;
        $this->uploadFile = null;
    }

    public function saveUpload(): void
    {
        abort_unless(auth()->user()->can('document.update'), 403);

        $this->validate([
            'uploadFile' => 'required|file|max:102400',
        ]);

        $documentFile = DocumentFile::whereHas('document', fn ($q) => $q->where('work_id', $this->work->id))
            ->findOrFail($this->uploadingForFileId);

        $disk = config('filesystems.document_files_disk', 'local');
        $storedPath = $this->uploadFile->store('documents/' . $documentFile->document_id, $disk);
        $absolutePath = Storage::disk($disk)->path($storedPath);
        $checksum = hash_file('sha256', $absolutePath);

        $documentFile->addVersion([
            'disk' => $disk,
            'path' => $storedPath,
            'original_filename' => $this->uploadFile->getClientOriginalName(),
            'mime_type' => $this->uploadFile->getMimeType(),
            'size_bytes' => $this->uploadFile->getSize(),
            'checksum_sha256' => $checksum,
            'notes' => $this->uploadNotes !== '' ? $this->uploadNotes : null,
            'uploaded_by' => auth()->id(),
        ]);

        $this->uploadingForFileId = null;
        $this->uploadFile = null;

        session()->flash('status', 'Versi baru berhasil diunggah.');
    }

    public function toggleFileHistory(string $documentFileId): void
    {
        if (in_array($documentFileId, $this->expandedFileIds, true)) {
            $this->expandedFileIds = array_values(array_diff($this->expandedFileIds, [$documentFileId]));
        } else {
            $this->expandedFileIds[] = $documentFileId;
        }
    }

    public function downloadVersion(string $versionId)
    {
        abort_unless(auth()->user()->can('document.download'), 403);

        $version = DocumentFileVersion::whereHas('documentFile.document', fn ($q) => $q->where('work_id', $this->work->id))
            ->findOrFail($versionId);

        return Storage::disk($version->disk)->download($version->path, $version->original_filename);
    }

    public function startBorrow(string $documentId): void
    {
        $this->resetValidation();
        $this->borrowingDocumentId = $documentId;
        $this->borrowerName = auth()->user()->name;
        $this->borrowerInstansi = '';
        $this->borrowerContact = '';
        $this->purpose = '';
    }

    public function cancelBorrow(): void
    {
        $this->borrowingDocumentId = null;
    }

    public function saveBorrow(): void
    {
        $this->validate([
            'borrowerName' => 'required|string|max:255',
            'borrowerInstansi' => 'nullable|string|max:255',
            'borrowerContact' => 'required|string|max:255',
            'purpose' => 'required|string|max:2000',
        ]);

        Loan::create([
            'document_id' => $this->borrowingDocumentId,
            'borrower_name' => $this->borrowerName,
            'borrower_instansi' => $this->borrowerInstansi !== '' ? $this->borrowerInstansi : null,
            'borrower_contact' => $this->borrowerContact,
            'purpose' => $this->purpose,
            'status' => Loan::STATUS_PENDING,
            'requested_by' => auth()->id(),
        ]);

        $this->borrowingDocumentId = null;

        session()->flash('status', 'Pengajuan peminjaman berhasil dikirim, menunggu persetujuan Petugas Arsip.');
    }

    public function cancelLoanRequest(string $loanId): void
    {
        $loan = Loan::where('requested_by', auth()->id())
            ->where('status', Loan::STATUS_PENDING)
            ->findOrFail($loanId);

        $loan->delete();

        session()->flash('status', 'Pengajuan peminjaman dibatalkan.');
    }
}; ?>

<x-slot name="header">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dokumen Pekerjaan</h2>
            <p class="text-sm text-gray-500">{{ $work->code }} - {{ $work->name }}</p>
        </div>
        <a href="{{ route('admin.works.index') }}" wire:navigate class="text-sm text-gray-500 hover:underline">
            &larr; Kembali ke daftar pekerjaan
        </a>
    </div>
</x-slot>

<div>
<div class="py-12">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

        @can('document.create')
            <div class="flex justify-end">
                <button type="button" wire:click="createDocument" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    + Tambah Dokumen
                </button>
            </div>
        @endcan

        @if (session('status'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-md px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        @forelse ($documents as $document)
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-100 flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-semibold text-gray-900">{{ $document->title }}</h3>
                            @if ($document->phase)
                                <span class="text-[10px] font-semibold uppercase tracking-wide rounded px-1.5 py-0.5 bg-gray-200 text-gray-600">
                                    {{ $document->phase->name }}
                                </span>
                            @endif
                            <span @class([
                                'text-[10px] font-semibold uppercase tracking-wide rounded px-1.5 py-0.5',
                                'bg-emerald-100 text-emerald-700' => $document->visibility === 'public',
                                'bg-blue-100 text-blue-700' => $document->visibility === 'internal',
                                'bg-amber-100 text-amber-700' => $document->visibility === 'restricted',
                                'bg-red-100 text-red-700' => $document->visibility === 'confidential',
                            ])>
                                {{ $document->visibility }}
                            </span>
                        </div>
                        @if ($document->description)
                            <p class="text-sm text-gray-500 mt-1">{{ $document->description }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-3 text-xs shrink-0">
                        @if ($document->loans->isNotEmpty())
                            @php($myLoan = $document->loans->first())
                            <span @class([
                                'text-[10px] font-semibold uppercase tracking-wide rounded px-1.5 py-0.5',
                                'bg-amber-100 text-amber-700' => $myLoan->status === 'pending',
                                'bg-emerald-100 text-emerald-700' => $myLoan->status === 'approved',
                            ])>
                                {{ $myLoan->statusLabel() }}
                            </span>
                            @if ($myLoan->status === 'pending')
                                <button type="button" wire:click="cancelLoanRequest('{{ $myLoan->id }}')" class="text-red-600 hover:underline">batalkan</button>
                            @endif
                        @else
                            <button type="button" wire:click="startBorrow('{{ $document->id }}')" class="text-indigo-600 hover:underline">pinjam</button>
                        @endif
                        @can('document.update')
                            <button type="button" wire:click="startAddFile('{{ $document->id }}')" class="text-indigo-600 hover:underline">+ file</button>
                        @endcan
                        @can('document.delete')
                            <button type="button" wire:click="confirmDeleteDocument('{{ $document->id }}')" class="text-red-600 hover:underline">hapus</button>
                        @endcan
                    </div>
                </div>

                <div class="divide-y divide-gray-100">
                    @forelse ($document->files as $file)
                        <div class="p-4">
                            <div class="flex items-center justify-between gap-4 flex-wrap">
                                <div>
                                    <span class="font-medium text-gray-800 text-sm">{{ $file->label }}</span>
                                    @if ($file->currentVersion)
                                        <span class="text-xs text-gray-500 ms-2">
                                            v{{ $file->currentVersion->version_number }} &middot;
                                            {{ $file->currentVersion->original_filename }} &middot;
                                            {{ $this->formatBytes($file->currentVersion->size_bytes) }}
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400 italic ms-2">belum ada versi diunggah</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-3 text-xs">
                                    @if ($file->currentVersion)
                                        @can('document.download')
                                            <button type="button" wire:click="downloadVersion('{{ $file->currentVersion->id }}')" class="text-indigo-600 hover:underline">unduh</button>
                                        @endcan
                                    @endif
                                    @can('document.update')
                                        <button type="button" wire:click="startUpload('{{ $file->id }}')" class="text-indigo-600 hover:underline">unggah versi baru</button>
                                    @endcan
                                    @if ($file->versions->count() > 1)
                                        <button type="button" wire:click="toggleFileHistory('{{ $file->id }}')" class="text-gray-500 hover:underline">
                                            {{ in_array($file->id, $expandedFileIds) ? 'sembunyikan riwayat' : 'riwayat (' . $file->versions->count() . ')' }}
                                        </button>
                                    @endif
                                </div>
                            </div>

                            @if (in_array($file->id, $expandedFileIds))
                                <table class="mt-3 min-w-full text-xs border border-gray-100 rounded">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-1.5 text-left font-medium text-gray-500 uppercase">Versi</th>
                                            <th class="px-3 py-1.5 text-left font-medium text-gray-500 uppercase">Berkas</th>
                                            <th class="px-3 py-1.5 text-left font-medium text-gray-500 uppercase">Ukuran</th>
                                            <th class="px-3 py-1.5 text-left font-medium text-gray-500 uppercase">Diunggah</th>
                                            <th class="px-3 py-1.5 text-right font-medium text-gray-500 uppercase">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($file->versions as $version)
                                            <tr>
                                                <td class="px-3 py-1.5">v{{ $version->version_number }}</td>
                                                <td class="px-3 py-1.5">{{ $version->original_filename }}</td>
                                                <td class="px-3 py-1.5">{{ $this->formatBytes($version->size_bytes) }}</td>
                                                <td class="px-3 py-1.5">
                                                    {{ $version->created_at->format('d/m/Y H:i') }}
                                                    @if ($version->uploader)
                                                        &middot; {{ $version->uploader->name }}
                                                    @endif
                                                </td>
                                                <td class="px-3 py-1.5 text-right">
                                                    @can('document.download')
                                                        <button type="button" wire:click="downloadVersion('{{ $version->id }}')" class="text-indigo-600 hover:underline">unduh</button>
                                                    @endcan
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    @empty
                        <div class="p-4 text-sm text-gray-400 italic">Belum ada file pada dokumen ini.</div>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="bg-white shadow-sm sm:rounded-lg p-6 text-center text-gray-400 italic">
                Belum ada dokumen untuk pekerjaan ini.
            </div>
        @endforelse
    </div>
</div>

@if ($showDocumentForm)
    <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
        <div class="fixed inset-0 bg-gray-500/75" wire:click="closeDocumentForm"></div>

        <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-lg sm:mx-auto">
            <form wire:submit="saveDocument" class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Dokumen Baru</h3>

                <div class="space-y-4">
                    <div>
                        <x-input-label for="title" value="Judul Dokumen" />
                        <x-text-input wire:model="title" id="title" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('title')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="description" value="Deskripsi" />
                        <textarea wire:model="description" id="description" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="phaseId" value="Fase" />
                            <x-select-input wire:model="phaseId" id="phaseId" class="mt-1 block w-full">
                                <option value="">- Pilih -</option>
                                @foreach ($phaseOptions as $opt)
                                    <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('phaseId')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="visibility" value="Visibilitas" />
                            <x-select-input wire:model="visibility" id="visibility" class="mt-1 block w-full">
                                <option value="internal">Internal</option>
                                <option value="public">Public</option>
                                <option value="restricted">Restricted</option>
                                <option value="confidential">Confidential</option>
                            </x-select-input>
                            <x-input-error :messages="$errors->get('visibility')" class="mt-1" />
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="closeDocumentForm" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

@if ($addFileToDocumentId)
    <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
        <div class="fixed inset-0 bg-gray-500/75" wire:click="cancelAddFile"></div>

        <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-md sm:mx-auto">
            <form wire:submit="saveNewFile" class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">File Baru</h3>

                <div>
                    <x-input-label for="newFileLabel" value="Label (mis. DED, Kontrak, Laporan Akhir)" />
                    <x-text-input wire:model="newFileLabel" id="newFileLabel" type="text" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('newFileLabel')" class="mt-1" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="cancelAddFile" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

@if ($uploadingForFileId)
    <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
        <div class="fixed inset-0 bg-gray-500/75" wire:click="cancelUpload"></div>

        <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-md sm:mx-auto">
            <form wire:submit="saveUpload" class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Unggah Versi Baru</h3>

                <div class="space-y-4">
                    <div>
                        <x-input-label for="uploadFile" value="Berkas (maks. 100MB)" />
                        <input wire:model="uploadFile" id="uploadFile" type="file" class="mt-1 block w-full text-sm text-gray-700">
                        <div wire:loading wire:target="uploadFile" class="text-xs text-gray-400 mt-1">Mengunggah berkas...</div>
                        <x-input-error :messages="$errors->get('uploadFile')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="uploadNotes" value="Catatan Versi (opsional)" />
                        <textarea wire:model="uploadNotes" id="uploadNotes" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="cancelUpload" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveUpload" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 disabled:opacity-50">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

@if ($confirmingDeleteDocumentId)
    <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
        <div class="fixed inset-0 bg-gray-500/75" wire:click="cancelDeleteDocument"></div>

        <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-md sm:mx-auto p-6">
            <h3 class="text-lg font-medium text-gray-900">Hapus dokumen ini?</h3>
            <p class="mt-2 text-sm text-gray-500">Dokumen akan dipindahkan ke sampah (soft delete) dan bisa dipulihkan lewat database bila diperlukan.</p>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" wire:click="cancelDeleteDocument" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                    Batal
                </button>
                <button type="button" wire:click="deleteDocument" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">
                    Hapus
                </button>
            </div>
        </div>
    </div>
@endif

@if ($borrowingDocumentId)
    <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
        <div class="fixed inset-0 bg-gray-500/75" wire:click="cancelBorrow"></div>

        <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-md sm:mx-auto">
            <form wire:submit="saveBorrow" class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Ajukan Peminjaman</h3>

                <div class="space-y-4">
                    <div>
                        <x-input-label for="borrowerName" value="Nama Peminjam" />
                        <x-text-input wire:model="borrowerName" id="borrowerName" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('borrowerName')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="borrowerInstansi" value="Instansi (opsional)" />
                        <x-text-input wire:model="borrowerInstansi" id="borrowerInstansi" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('borrowerInstansi')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="borrowerContact" value="Kontak (telepon/email)" />
                        <x-text-input wire:model="borrowerContact" id="borrowerContact" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('borrowerContact')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="purpose" value="Keperluan Peminjaman" />
                        <textarea wire:model="purpose" id="purpose" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                        <x-input-error :messages="$errors->get('purpose')" class="mt-1" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="cancelBorrow" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                        Kirim Pengajuan
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
</div>
