<?php

use App\Livewire\Concerns\ReviewsReports;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentFileVersion;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

/**
 * Laporan & Revisi: semua berkas laporan lintas pekerjaan, dikelompokkan
 * menurut status review versi terkininya. Reviewer (report.review)
 * menyetujui / meminta revisi langsung dari sini.
 */
new #[Layout('layouts.app')] class extends Component
{
    use WithPagination, ReviewsReports;

    #[Url(as: 'status')]
    public string $statusFilter = 'menunggu_review';

    #[Url(as: 'q')]
    public string $search = '';

    public string $reportTypeFilter = '';

    public array $expandedFileIds = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('document.view'), 403);
    }

    public function updating($property): void
    {
        if (in_array($property, ['statusFilter', 'search', 'reportTypeFilter'], true)) {
            $this->resetPage();
        }
    }

    private function baseQuery()
    {
        return DocumentFile::query()
            ->whereHas('document', fn ($q) => $q->where('category', Document::CATEGORY_REPORT))
            ->whereNotNull('current_version_id');
    }

    public function with(): array
    {
        $files = $this->baseQuery()
            ->with(['document.work', 'currentVersion.uploader', 'currentVersion.reviewer', 'versions.reviews.user'])
            ->when($this->statusFilter, fn ($q) => $q->whereHas('currentVersion', fn ($v) => $v->where('review_status', $this->statusFilter)))
            ->when($this->reportTypeFilter, fn ($q) => $q->whereHas('document', fn ($d) => $d->where('report_type', $this->reportTypeFilter)))
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('label', 'like', "%{$this->search}%")
                ->orWhereHas('document', fn ($d) => $d->where('title', 'like', "%{$this->search}%")
                    ->orWhereHas('work', fn ($k) => $k->where('code', 'like', "%{$this->search}%")->orWhere('name', 'like', "%{$this->search}%")))))
            ->orderByDesc(
                DocumentFileVersion::select('created_at')->whereColumn('document_file_versions.id', 'document_files.current_version_id')
            )
            ->paginate(20);

        $counts = DocumentFileVersion::query()
            ->whereIn('id', $this->baseQuery()->select('current_version_id'))
            ->selectRaw('review_status, count(*) as total')
            ->groupBy('review_status')
            ->pluck('total', 'review_status');

        return ['files' => $files, 'counts' => $counts];
    }

    public function toggleHistory(string $fileId): void
    {
        if (in_array($fileId, $this->expandedFileIds, true)) {
            $this->expandedFileIds = array_values(array_diff($this->expandedFileIds, [$fileId]));
        } else {
            $this->expandedFileIds[] = $fileId;
        }
    }

    public function downloadVersion(string $versionId)
    {
        abort_unless(auth()->user()->can('document.download'), 403);

        $version = DocumentFileVersion::whereHas('documentFile.document', fn ($q) => $q->where('category', Document::CATEGORY_REPORT))
            ->with('documentFile.document')
            ->findOrFail($versionId);

        AuditLog::record('document.downloaded', $version->documentFile->document, 'Unduh berkas: ' . $version->original_filename, [
            'file_label' => $version->documentFile->label,
            'version' => $version->version_number,
        ]);

        return Storage::disk($version->disk)->download($version->path, $version->original_filename);
    }
}; ?>

<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Laporan &amp; Revisi</h2>
</x-slot>

<div>
    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-md px-4 py-3">{{ session('status') }}</div>
            @endif

            <div class="flex flex-wrap gap-2">
                @foreach (['menunggu_review' => 'Menunggu Review', 'revisi' => 'Perlu Revisi', 'draf' => 'Draf', 'disetujui' => 'Disetujui', '' => 'Semua'] as $value => $label)
                    <button type="button" wire:click="$set('statusFilter', '{{ $value }}')" @class([
                        'px-3 py-1.5 rounded-md text-xs font-semibold uppercase tracking-widest',
                        'bg-pu-navy text-white' => $statusFilter === $value,
                        'bg-white text-gray-600 border border-gray-300' => $statusFilter !== $value,
                    ])>
                        {{ $label }} @if ($value !== '' && ($counts[$value] ?? 0) > 0) ({{ $counts[$value] }}) @endif
                    </button>
                @endforeach
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <x-text-input wire:model.live.debounce.400ms="search" type="search" placeholder="Cari judul laporan atau pekerjaan..." class="sm:col-span-2 w-full text-sm" />
                <x-select-input wire:model.live="reportTypeFilter" class="w-full text-sm">
                    <option value="">Semua jenis laporan</option>
                    @foreach (\App\Models\Document::REPORT_TYPES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-select-input>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg divide-y divide-gray-100">
                @forelse ($files as $file)
                    @php($version = $file->currentVersion)
                    <div class="p-4" wire:key="rf-{{ $file->id }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-gray-900">{{ $file->document->title }}</p>
                                    <span class="text-[10px] font-semibold uppercase tracking-wide rounded px-1.5 py-0.5 bg-pu-gold-100 text-pu-navy-800">{{ $file->document->reportTypeLabel() ?? 'Laporan' }}</span>
                                    @include('reports.status-badge', ['status' => $version->review_status])
                                </div>
                                <p class="text-sm text-gray-500">{{ $file->label }} · v{{ $version->version_number }} · {{ $version->original_filename }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    @can('work.view')
                                        <a href="{{ route('admin.works.documents', $file->document->work_id) }}" wire:navigate class="text-pu-navy-600 hover:underline">{{ $file->document->work?->code }} - {{ $file->document->work?->name }}</a>
                                    @else
                                        {{ $file->document->work?->code }} - {{ $file->document->work?->name }}
                                    @endcan
                                </p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    Diunggah {{ $version->created_at->format('d/m/Y H:i') }} oleh {{ $version->uploader?->name ?? '-' }}
                                    @if ($version->submitted_at) · diajukan {{ $version->submitted_at->format('d/m/Y H:i') }} @endif
                                    @if ($version->reviewed_at) · direview {{ $version->reviewed_at->format('d/m/Y H:i') }} oleh {{ $version->reviewer?->name ?? '-' }} @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-3 text-xs whitespace-nowrap">
                                @can('document.download')
                                    <button type="button" wire:click="downloadVersion('{{ $version->id }}')" class="text-pu-navy-600 hover:underline">unduh</button>
                                @endcan
                                @if ($version->review_status === 'draf')
                                    @can('document.update')
                                        <button type="button" wire:click="submitForReview('{{ $version->id }}')" class="text-sky-700 font-semibold hover:underline">ajukan review</button>
                                    @endcan
                                @elseif ($version->review_status === 'menunggu_review')
                                    @can('report.review')
                                        <button type="button" wire:click="startReview('{{ $version->id }}', 'disetujui')" class="text-emerald-700 font-semibold hover:underline">setujui</button>
                                        <button type="button" wire:click="startReview('{{ $version->id }}', 'revisi')" class="text-amber-700 font-semibold hover:underline">minta revisi</button>
                                    @endcan
                                @endif
                                <button type="button" wire:click="toggleHistory('{{ $file->id }}')" class="text-gray-500 hover:underline">
                                    {{ in_array($file->id, $expandedFileIds) ? 'tutup riwayat' : 'riwayat revisi (' . $file->versions->count() . ' versi)' }}
                                </button>
                            </div>
                        </div>

                        @if ($version->review_status === 'revisi' && $version->review_notes)
                            <div class="mt-3 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-sm">
                                <p class="text-xs text-amber-700 font-semibold">Catatan revisi</p>
                                <p class="text-gray-700 whitespace-pre-line mt-1">{{ $version->review_notes }}</p>
                            </div>
                        @endif

                        @if (in_array($file->id, $expandedFileIds))
                            <ol class="mt-4 ml-2 border-l-2 border-pu-navy-100 pl-4 space-y-4">
                                @foreach ($file->versions as $v)
                                    <li>
                                        <div class="flex flex-wrap items-center gap-2 text-sm">
                                            <span class="font-semibold text-gray-800">v{{ $v->version_number }}</span>
                                            <span class="text-gray-500">{{ $v->original_filename }}</span>
                                            @include('reports.status-badge', ['status' => $v->review_status])
                                            @can('document.download')
                                                <button type="button" wire:click="downloadVersion('{{ $v->id }}')" class="text-xs text-pu-navy-600 hover:underline">unduh</button>
                                            @endcan
                                        </div>
                                        <p class="text-xs text-gray-400">diunggah {{ $v->created_at->format('d/m/Y H:i') }}@if ($v->notes) · {{ $v->notes }}@endif</p>
                                        @foreach ($v->reviews as $review)
                                            <div class="mt-1 text-xs text-gray-600">
                                                {{ $review->created_at->format('d/m/Y H:i') }} · <span class="font-medium">{{ $review->actionLabel() }}</span> oleh {{ $review->user?->name ?? '-' }}
                                                @if ($review->notes)
                                                    <p class="mt-0.5 rounded bg-gray-50 px-2 py-1 text-gray-700 whitespace-pre-line">{{ $review->notes }}</p>
                                                @endif
                                            </div>
                                        @endforeach
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-gray-400 italic">Tidak ada laporan pada status ini. Laporan dibuat dari halaman Dokumen pekerjaan dengan kategori "Laporan".</p>
                @endforelse
            </div>

            <div>{{ $files->links() }}</div>
        </div>
    </div>

    @include('reports.review-modal')
</div>
