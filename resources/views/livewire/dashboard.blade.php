<?php

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentFileVersion;
use App\Models\Letter;
use App\Models\LetterDisposition;
use App\Models\Loan;
use App\Models\Work;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Dashboard ringkas: setiap blok hanya tampil bila pengguna punya
 * permission untuk data di dalamnya, sehingga staf yang hanya menerima
 * disposisi tetap mendapat halaman yang relevan (kotak tugasnya sendiri).
 */
new #[Layout('layouts.app')] class extends Component
{
    public int $year;

    public function mount(): void
    {
        $this->year = (int) now()->format('Y');
    }

    public function with(): array
    {
        $user = auth()->user();
        $now = Carbon::now()->locale('id');

        $hour = (int) $now->format('G');
        $greeting = match (true) {
            $hour < 11 => 'Selamat pagi',
            $hour < 15 => 'Selamat siang',
            $hour < 19 => 'Selamat sore',
            default => 'Selamat malam',
        };

        $stats = [];

        if ($user->can('work.view')) {
            $stats[] = [
                'label' => 'Pekerjaan', 'value' => Work::count(),
                'hint' => Work::where('fiscal_year', $this->year)->count() . ' di TA ' . $this->year,
                'url' => route('admin.works.index'), 'icon' => 'work',
            ];
        }

        if ($user->can('document.view')) {
            $stats[] = [
                'label' => 'Dokumen', 'value' => Document::count(),
                'hint' => DocumentFileVersion::count() . ' berkas tersimpan',
                'url' => route('admin.reports.index'), 'icon' => 'doc',
            ];
        }

        if ($user->can('letter.view')) {
            $incoming = Letter::visibleTo($user)->where('direction', Letter::DIRECTION_INCOMING)->where('agenda_year', $this->year);
            $stats[] = [
                'label' => 'Surat Masuk ' . $this->year, 'value' => (clone $incoming)->count(),
                'hint' => (clone $incoming)->where('status', '!=', Letter::STATUS_DONE)->count() . ' belum selesai',
                'url' => route('admin.letters.index', ['arah' => 'masuk']), 'icon' => 'in',
            ];
            $stats[] = [
                'label' => 'Surat Keluar ' . $this->year,
                'value' => Letter::visibleTo($user)->where('direction', Letter::DIRECTION_OUTGOING)->where('agenda_year', $this->year)->count(),
                'hint' => Letter::visibleTo($user)->where('direction', Letter::DIRECTION_OUTGOING)->where('status', Letter::STATUS_DRAFT)->count() . ' masih draf',
                'url' => route('admin.letters.index', ['arah' => 'keluar']), 'icon' => 'out',
            ];
        }

        // --- Kelengkapan arsip (softfile & hardcopy) ------------------------
        // Softfile = dokumen punya berkas terunggah; hardcopy = dokumen
        // sudah ditempatkan di lokasi Arsip Fisik (lihat scope di Document).
        $completeness = null;
        if ($user->can('document.view')) {
            $canOpenWorks = $user->can('work.view');
            $totalDocuments = Document::count();
            $tile = fn (string $label, $documents, $works, ?string $filter, string $tone) => [
                'label' => $label,
                'documents' => (clone $documents)->count(),
                'works' => $works->count(),
                'url' => $canOpenWorks && $filter ? route('admin.works.index', ['kelengkapan' => $filter]) : null,
                'tone' => $tone,
                'fullWorks' => $tone === 'emerald',
            ];

            $completeness = [
                'total' => $totalDocuments,
                'tiles' => [
                    $tile('Tanpa softfile', Document::withoutSoftfile(), Work::whereHas('documents', fn ($d) => $d->withoutSoftfile()), 'softfile', 'amber'),
                    $tile('Tanpa hardcopy', Document::withoutHardcopy(), Work::whereHas('documents', fn ($d) => $d->withoutHardcopy()), 'hardcopy', 'amber'),
                    $tile('Tanpa keduanya', Document::withoutSoftfile()->withoutHardcopy(), Work::whereHas('documents', fn ($d) => $d->withoutSoftfile()->withoutHardcopy()), null, 'red'),
                    $tile('Lengkap (softfile + hardcopy)', Document::withSoftfile()->withHardcopy(), Work::whereHas('documents')->whereDoesntHave('documents', fn ($d) => $d->where(fn ($x) => $x->withoutSoftfile()->orWhere(fn ($y) => $y->withoutHardcopy()))), null, 'emerald'),
                ],
                'worksWithoutDocuments' => Work::doesntHave('documents')->count(),
                'emptyUrl' => $canOpenWorks ? route('admin.works.index', ['kelengkapan' => 'kosong']) : null,
            ];
        }

        // --- Kotak "Perlu perhatian Anda" ---------------------------------
        $myDispositions = LetterDisposition::query()
            ->with(['letter', 'fromUser'])
            ->where('to_user_id', $user->id)
            ->open()
            ->orderByRaw('due_date IS NULL, due_date')
            ->latest()
            ->limit(5)
            ->get();
        $myDispositionCount = LetterDisposition::where('to_user_id', $user->id)->open()->count();

        $currentReportVersions = fn () => DocumentFileVersion::query()
            ->whereIn('id', DocumentFile::select('current_version_id'))
            ->with('documentFile.document.work');

        $pendingReviews = $user->can('report.review')
            ? $currentReportVersions()->where('review_status', DocumentFileVersion::REVIEW_PENDING)->oldest('submitted_at')->limit(5)->get()
            : collect();

        $myRevisions = $user->can('document.update')
            ? $currentReportVersions()->where('review_status', DocumentFileVersion::REVIEW_REVISION)->where('uploaded_by', $user->id)->latest('reviewed_at')->limit(5)->get()
            : collect();

        $pendingLoans = $user->can('loan.view') ? Loan::where('status', Loan::STATUS_PENDING)->count() : 0;

        // --- Grafik surat per bulan --------------------------------------
        $monthly = null;
        if ($user->can('letter.view')) {
            $rows = Letter::visibleTo($user)
                ->where('agenda_year', $this->year)
                ->get(['direction', 'letter_date'])
                ->groupBy(fn ($l) => $l->direction . '-' . (int) $l->letter_date->format('n'))
                ->map->count();

            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            $monthly = collect(range(1, 12))->map(fn ($m) => [
                'label' => $months[$m - 1],
                'in' => $rows['incoming-' . $m] ?? 0,
                'out' => $rows['outgoing-' . $m] ?? 0,
                'future' => $m > (int) now()->format('n') && $this->year === (int) now()->format('Y'),
            ]);
        }

        // --- Ringkasan status laporan ------------------------------------
        $reportSummary = null;
        if ($user->can('document.view')) {
            $counts = $currentReportVersions()->whereNotNull('review_status')
                ->selectRaw('review_status, count(*) as total')->groupBy('review_status')
                ->pluck('total', 'review_status');
            $reportSummary = collect(DocumentFileVersion::REVIEW_LABELS)->map(fn ($label, $key) => [
                'key' => $key, 'label' => $label, 'total' => (int) ($counts[$key] ?? 0),
            ])->values();
        }

        return [
            'greeting' => $greeting,
            'today' => $now->translatedFormat('l, d F Y'),
            'stats' => $stats,
            'myDispositions' => $myDispositions,
            'myDispositionCount' => $myDispositionCount,
            'pendingReviews' => $pendingReviews,
            'myRevisions' => $myRevisions,
            'pendingLoans' => $pendingLoans,
            'monthly' => $monthly,
            'monthlyMax' => $monthly ? max(1, $monthly->max(fn ($m) => max($m['in'], $m['out']))) : 1,
            'reportSummary' => $reportSummary,
            'completeness' => $completeness,
            'recentWorks' => $user->can('work.view') ? Work::with('workStatus')->latest()->limit(5)->get() : collect(),
            'activities' => $user->can('audit.view') ? AuditLog::with('user')->latest()->limit(8)->get() : collect(),
        ];
    }
}; ?>

<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
</x-slot>

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        {{-- Sambutan + aksi cepat --}}
        <section class="relative overflow-hidden rounded-xl bg-pu-navy text-white shadow-sm">
            <div class="absolute inset-x-0 bottom-0 h-full opacity-[0.07] pointer-events-none" aria-hidden="true">
                <svg viewBox="0 0 400 200" class="h-full w-full" preserveAspectRatio="none">
                    <path d="M0 120 C 60 80, 120 160, 200 120 S 340 80, 400 120 V200 H0 Z" fill="currentColor"/>
                    <path d="M0 150 C 80 110, 160 190, 240 150 S 360 120, 400 150 V200 H0 Z" fill="currentColor"/>
                </svg>
            </div>
            <div class="relative px-6 py-7 sm:px-8 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
                <div>
                    <p class="text-sm text-white/60">{{ $today }}</p>
                    <h1 class="mt-1 text-2xl sm:text-3xl font-semibold">{{ $greeting }}, {{ auth()->user()->name }}</h1>
                    <p class="mt-2 text-sm text-white/70 max-w-xl">
                        @if ($myDispositionCount > 0)
                            Ada <span class="font-semibold text-pu-gold">{{ $myDispositionCount }} disposisi</span> yang menunggu tindak lanjut Anda.
                        @else
                            Tidak ada disposisi yang menunggu Anda. Semua beres.
                        @endif
                        @if ($pendingReviews->isNotEmpty())
                            <span class="font-semibold text-pu-gold">{{ $pendingReviews->count() }}{{ $pendingReviews->count() >= 5 ? '+' : '' }} laporan</span> menunggu review.
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @can('letter.create')
                        <a href="{{ route('admin.letters.create', ['arah' => 'masuk']) }}" wire:navigate class="inline-flex items-center gap-2 rounded-md bg-pu-gold px-4 py-2 text-xs font-semibold uppercase tracking-widest text-pu-navy-900 hover:bg-pu-gold-300">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                            Surat Masuk
                        </a>
                        <a href="{{ route('admin.letters.create', ['arah' => 'keluar']) }}" wire:navigate class="inline-flex items-center gap-2 rounded-md bg-white/10 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white ring-1 ring-white/20 hover:bg-white/20">
                            Surat Keluar
                        </a>
                    @endcan
                    @can('work.create')
                        <a href="{{ route('admin.works.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-md bg-white/10 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white ring-1 ring-white/20 hover:bg-white/20">
                            Pekerjaan
                        </a>
                    @endcan
                </div>
            </div>
        </section>

        {{-- Kartu statistik --}}
        @if (count($stats) > 0)
            <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ($stats as $stat)
                    <a href="{{ $stat['url'] }}" wire:navigate class="group rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 hover:ring-pu-navy-200 transition">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $stat['label'] }}</p>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-pu-navy-50 text-pu-navy-500 group-hover:bg-pu-gold-100">
                                @switch($stat['icon'])
                                    @case('work')
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 20h18M5 20V9l7-5 7 5v11M9 20v-6h6v6"/></svg>
                                        @break
                                    @case('doc')
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13H7zM14 3v5h5M10 13h6M10 17h6"/></svg>
                                        @break
                                    @case('in')
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v12H4zM4 7l8 6 8-6"/></svg>
                                        @break
                                    @default
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 12l16-8-6 16-2-6z"/></svg>
                                @endswitch
                            </span>
                        </div>
                        <p class="mt-3 text-3xl font-semibold text-gray-900 tabular-nums">{{ number_format($stat['value'], 0, ',', '.') }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ $stat['hint'] }}</p>
                    </a>
                @endforeach
            </section>
        @endif

        {{-- Kelengkapan arsip: softfile & hardcopy --}}
        @if ($completeness)
            <section class="rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-4">
                    <div>
                        <h3 class="font-semibold text-gray-900">Kelengkapan Arsip</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Dari {{ number_format($completeness['total'], 0, ',', '.') }} dokumen. Softfile = berkas sudah diunggah, hardcopy = sudah ditempatkan di Arsip Fisik.</p>
                    </div>
                    @if ($completeness['worksWithoutDocuments'] > 0)
                        @if ($completeness['emptyUrl'])
                            <a href="{{ $completeness['emptyUrl'] }}" wire:navigate class="text-xs text-pu-navy-600 hover:underline">{{ $completeness['worksWithoutDocuments'] }} pekerjaan belum punya dokumen &rarr;</a>
                        @else
                            <span class="text-xs text-gray-500">{{ $completeness['worksWithoutDocuments'] }} pekerjaan belum punya dokumen</span>
                        @endif
                    @endif
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-4 divide-x divide-y lg:divide-y-0 divide-gray-100">
                    @foreach ($completeness['tiles'] as $t)
                        <{{ $t['url'] ? 'a' : 'div' }} @if ($t['url']) href="{{ $t['url'] }}" wire:navigate @endif class="block px-5 py-4 {{ $t['url'] ? 'hover:bg-gray-50' : '' }}">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $t['label'] }}</p>
                            <p @class([
                                'mt-2 text-3xl font-semibold tabular-nums',
                                'text-amber-600' => $t['tone'] === 'amber' && $t['documents'] > 0,
                                'text-red-600' => $t['tone'] === 'red' && $t['documents'] > 0,
                                'text-emerald-600' => $t['tone'] === 'emerald' && $t['documents'] > 0,
                                'text-gray-400' => $t['documents'] === 0,
                            ])>{{ number_format($t['documents'], 0, ',', '.') }}</p>
                            <p class="mt-1 text-xs text-gray-500">
                                @if ($t['fullWorks'])
                                    dokumen; {{ $t['works'] }} pekerjaan lengkap seluruhnya
                                @else
                                    dokumen, di {{ $t['works'] }} pekerjaan
                                @endif
                            </p>
                        </{{ $t['url'] ? 'a' : 'div' }}>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Kolom kiri: tugas --}}
            <section class="lg:col-span-2 rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="font-semibold text-gray-900">Perlu Perhatian Anda</h3>
                    <a href="{{ route('admin.dispositions.index') }}" wire:navigate class="text-xs text-pu-navy-600 hover:underline">Semua disposisi &rarr;</a>
                </div>

                <div class="divide-y divide-gray-100">
                    @foreach ($myDispositions as $d)
                        <a href="{{ route('admin.letters.show', $d->letter_id) }}" wire:navigate class="flex items-start gap-3 px-5 py-3 hover:bg-gray-50">
                            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-700 text-[10px] font-bold">DIS</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm {{ $d->status === 'baru' ? 'font-semibold text-gray-900' : 'text-gray-800' }}">{{ $d->letter?->subject ?? '(surat dihapus)' }}</p>
                                <p class="truncate text-xs text-gray-500">{{ $d->fromUser?->name ?? '-' }}: {{ $d->instruction }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                @if ($d->isOverdue())
                                    <span class="text-[10px] font-semibold uppercase tracking-wide rounded px-1.5 py-0.5 bg-red-100 text-red-700">Lewat batas</span>
                                @elseif ($d->due_date)
                                    <span class="text-xs text-gray-500">s.d. {{ $d->due_date->format('d/m') }}</span>
                                @else
                                    <span class="text-xs text-gray-400">{{ $d->created_at->locale('id')->diffForHumans() }}</span>
                                @endif
                            </div>
                        </a>
                    @endforeach

                    @foreach ($pendingReviews as $v)
                        <a href="{{ route('admin.reports.index', ['status' => 'menunggu_review']) }}" wire:navigate class="flex items-start gap-3 px-5 py-3 hover:bg-gray-50">
                            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-pu-gold-100 text-pu-navy-800 text-[10px] font-bold">REV</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm text-gray-900">Review: {{ $v->documentFile->document->title }} <span class="text-gray-400">v{{ $v->version_number }}</span></p>
                                <p class="truncate text-xs text-gray-500">{{ $v->documentFile->document->work?->name }}</p>
                            </div>
                            <span class="shrink-0 text-xs text-gray-400">{{ $v->submitted_at?->locale('id')->diffForHumans() }}</span>
                        </a>
                    @endforeach

                    @foreach ($myRevisions as $v)
                        <a href="{{ route('admin.works.documents', $v->documentFile->document->work_id) }}" wire:navigate class="flex items-start gap-3 px-5 py-3 hover:bg-gray-50">
                            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700 text-[10px] font-bold">FIX</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm text-gray-900">Perbaiki: {{ $v->documentFile->document->title }} <span class="text-gray-400">v{{ $v->version_number }}</span></p>
                                <p class="truncate text-xs text-gray-500">{{ $v->review_notes }}</p>
                            </div>
                            <span class="shrink-0 text-xs text-gray-400">{{ $v->reviewed_at?->locale('id')->diffForHumans() }}</span>
                        </a>
                    @endforeach

                    @if ($pendingLoans > 0)
                        <a href="{{ route('admin.loans.index') }}" wire:navigate class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold">PJM</span>
                            <p class="flex-1 text-sm text-gray-900">{{ $pendingLoans }} pengajuan peminjaman menunggu persetujuan</p>
                        </a>
                    @endif

                    @if ($myDispositions->isEmpty() && $pendingReviews->isEmpty() && $myRevisions->isEmpty() && $pendingLoans === 0)
                        <div class="px-5 py-10 text-center">
                            <p class="text-sm font-medium text-gray-700">Tidak ada yang menunggu Anda.</p>
                            <p class="text-xs text-gray-400 mt-1">Disposisi, review laporan, dan peminjaman yang butuh tindakan akan muncul di sini.</p>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Kolom kanan: status laporan --}}
            @if ($reportSummary)
                @php($reportTotal = $reportSummary->sum('total'))
                <section class="rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                        <h3 class="font-semibold text-gray-900">Status Laporan</h3>
                        <a href="{{ route('admin.reports.index', ['status' => '']) }}" wire:navigate class="text-xs text-pu-navy-600 hover:underline">Buka &rarr;</a>
                    </div>
                    <div class="p-5">
                        @if ($reportTotal === 0)
                            <p class="text-sm text-gray-400 italic">Belum ada laporan. Buat dokumen berkategori "Laporan" di halaman Dokumen pekerjaan.</p>
                        @else
                            <p class="text-3xl font-semibold text-gray-900 tabular-nums">{{ $reportTotal }} <span class="text-sm font-normal text-gray-500">berkas laporan</span></p>
                            <ul class="mt-4 space-y-3">
                                @foreach ($reportSummary as $row)
                                    <li>
                                        <a href="{{ route('admin.reports.index', ['status' => $row['key']]) }}" wire:navigate class="block group">
                                            <div class="flex items-center justify-between text-sm">
                                                <span class="text-gray-700 group-hover:text-pu-navy-600">{{ $row['label'] }}</span>
                                                <span class="tabular-nums font-medium text-gray-900">{{ $row['total'] }}</span>
                                            </div>
                                            <div class="mt-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                                                <div @class([
                                                    'h-full rounded-full',
                                                    'bg-gray-400' => $row['key'] === 'draf',
                                                    'bg-sky-500' => $row['key'] === 'menunggu_review',
                                                    'bg-amber-500' => $row['key'] === 'revisi',
                                                    'bg-emerald-500' => $row['key'] === 'disetujui',
                                                ]) style="width: {{ $reportTotal ? round($row['total'] / $reportTotal * 100) : 0 }}%"></div>
                                            </div>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </section>
            @endif
        </div>

        {{-- Grafik surat per bulan --}}
        @if ($monthly)
            <section class="rounded-xl bg-white shadow-sm ring-1 ring-gray-100" x-data="{ table: false }">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
                    <div>
                        <h3 class="font-semibold text-gray-900">Surat per Bulan · {{ $year }}</h3>
                        <p class="text-xs text-gray-500">{{ $monthly->sum('in') }} surat masuk, {{ $monthly->sum('out') }} surat keluar</p>
                    </div>
                    <div class="flex items-center gap-4 text-xs text-gray-600">
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background:#3D5BA9"></span>Surat masuk</span>
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background:#C78D0D"></span>Surat keluar</span>
                        <button type="button" x-on:click="table = !table" class="text-pu-navy-600 hover:underline" x-text="table ? 'Lihat grafik' : 'Lihat tabel'"></button>
                    </div>
                </div>

                <div class="p-5" x-show="!table">
                    <div class="relative h-48">
                        {{-- garis bantu --}}
                        <div class="absolute inset-0 flex flex-col justify-between pointer-events-none" aria-hidden="true">
                            @foreach ([1, 0.5, 0] as $tick)
                                <div class="flex items-center gap-2">
                                    <span class="w-6 text-right text-[10px] text-gray-400 tabular-nums">{{ (int) round($monthlyMax * $tick) }}</span>
                                    <div class="flex-1 border-t {{ $tick == 0 ? 'border-gray-300' : 'border-dashed border-gray-100' }}"></div>
                                </div>
                            @endforeach
                        </div>
                        <div class="absolute inset-y-0 left-8 right-0 flex items-end gap-1 sm:gap-2">
                            @foreach ($monthly as $m)
                                <div class="group relative flex h-full flex-1 items-end justify-center gap-[2px] {{ $m['future'] ? 'opacity-40' : '' }}">
                                    <div class="w-full max-w-[14px] rounded-t" style="height: {{ $m['in'] / $monthlyMax * 100 }}%; background:#3D5BA9; min-height: {{ $m['in'] > 0 ? '2px' : '0' }}"></div>
                                    <div class="w-full max-w-[14px] rounded-t" style="height: {{ $m['out'] / $monthlyMax * 100 }}%; background:#C78D0D; min-height: {{ $m['out'] > 0 ? '2px' : '0' }}"></div>
                                    <div class="pointer-events-none absolute bottom-full mb-1 hidden whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[11px] text-white shadow group-hover:block z-10">
                                        {{ $m['label'] }}: {{ $m['in'] }} masuk · {{ $m['out'] }} keluar
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="ml-8 mt-2 flex gap-1 sm:gap-2">
                        @foreach ($monthly as $m)
                            <span class="flex-1 text-center text-[10px] text-gray-500">{{ $m['label'] }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="p-5 overflow-x-auto" x-show="table" x-cloak>
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr class="text-gray-500">
                                <th class="py-1 pr-3 text-left font-medium">Bulan</th>
                                @foreach ($monthly as $m)<th class="px-2 py-1 text-right font-medium">{{ $m['label'] }}</th>@endforeach
                                <th class="pl-3 py-1 text-right font-semibold">Total</th>
                            </tr>
                        </thead>
                        <tbody class="tabular-nums text-gray-800">
                            <tr><td class="py-1 pr-3">Masuk</td>@foreach ($monthly as $m)<td class="px-2 py-1 text-right">{{ $m['in'] }}</td>@endforeach<td class="pl-3 py-1 text-right font-semibold">{{ $monthly->sum('in') }}</td></tr>
                            <tr><td class="py-1 pr-3">Keluar</td>@foreach ($monthly as $m)<td class="px-2 py-1 text-right">{{ $m['out'] }}</td>@endforeach<td class="pl-3 py-1 text-right font-semibold">{{ $monthly->sum('out') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @if ($recentWorks->isNotEmpty())
                <section class="rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                        <h3 class="font-semibold text-gray-900">Pekerjaan Terbaru</h3>
                        <a href="{{ route('admin.works.index') }}" wire:navigate class="text-xs text-pu-navy-600 hover:underline">Semua &rarr;</a>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($recentWorks as $w)
                            <li>
                                <a href="{{ route('admin.works.documents', $w) }}" wire:navigate class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-gray-50">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm text-gray-900">{{ $w->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $w->code }}@if ($w->fiscal_year) · TA {{ $w->fiscal_year }}@endif</p>
                                    </div>
                                    @if ($w->workStatus)
                                        <span class="shrink-0 text-[10px] font-semibold uppercase tracking-wide rounded px-1.5 py-0.5 bg-gray-100 text-gray-600">{{ $w->workStatus->name }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($activities->isNotEmpty())
                <section class="rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                        <h3 class="font-semibold text-gray-900">Aktivitas Terakhir</h3>
                        <a href="{{ route('admin.audit-logs.index') }}" wire:navigate class="text-xs text-pu-navy-600 hover:underline">Log audit &rarr;</a>
                    </div>
                    <ol class="px-5 py-4 space-y-3">
                        @foreach ($activities as $a)
                            <li class="flex gap-3">
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-pu-navy-300"></span>
                                <div class="min-w-0">
                                    <p class="text-sm text-gray-800 truncate">{{ $a->description }}</p>
                                    <p class="text-xs text-gray-400">{{ $a->user?->name ?? 'Pengunjung' }} · {{ $a->created_at->locale('id')->diffForHumans() }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif
        </div>
    </div>
</div>
