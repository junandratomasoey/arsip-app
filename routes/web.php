<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'can:organization.manage'])->prefix('admin')->name('admin.')->group(function () {
    Volt::route('organizational-units', 'admin.organizational-units.index')
        ->name('organizational-units.index');
});

Route::middleware(['auth', 'can:settings.manage'])->prefix('admin')->name('admin.')->group(function () {
    Volt::route('work-types', 'admin.work-types.index')
        ->name('work-types.index');

    Volt::route('phases', 'admin.phases.index')
        ->name('phases.index');

    Volt::route('work-statuses', 'admin.work-statuses.index')
        ->name('work-statuses.index');

    Volt::route('tags', 'admin.tags.index')
        ->name('tags.index');
});

Route::middleware(['auth', 'can:work.view'])->prefix('admin')->name('admin.')->group(function () {
    Volt::route('works', 'admin.works.index')
        ->name('works.index');

    Volt::route('works/create', 'admin.works.form')
        ->name('works.create');

    Volt::route('works/{work}/edit', 'admin.works.form')
        ->name('works.edit');

    Volt::route('works/{work}/locations', 'admin.works.locations')
        ->name('works.locations');

    Volt::route('works-import', 'admin.works.import')
        ->name('works.import');

    // Template Excel untuk impor pekerjaan - closure (bukan Volt) karena
    // cuma perlu men-stream satu file, sama seperti pola unduh dokumen
    // publik. Dibatasi work.create karena mengunduh template hanya
    // relevan untuk yang boleh membuat pekerjaan (import = bulk create).
    Route::get('works-import/template', function () {
        abort_unless(auth()->user()->can('work.create'), 403);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Kode Pekerjaan*', 'Nama Pekerjaan*', 'Nama Paket', 'Jenis Pekerjaan', 'Tahun Anggaran', 'Satker', 'PPK'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            '2342',
            'Embung Serbaguna Kalimabu',
            'Paket Pembangunan Embung Serbaguna',
            'Embung',
            2026,
            'Satker BBWS Nusa Tenggara II',
            'PPK OP I',
        ], null, 'A2');

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->getStyle('A1:G1')->getFont()->setBold(true);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'template-impor-pekerjaan.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    })->name('works.import.template');
});

Route::middleware(['auth', 'can:document.view'])->prefix('admin')->name('admin.')->group(function () {
    Volt::route('works/{work}/documents', 'admin.works.documents')
        ->name('works.documents');
});

Route::middleware(['auth', 'can:archive.view'])->prefix('admin')->name('admin.')->group(function () {
    Volt::route('physical-locations', 'admin.physical-locations.index')
        ->name('physical-locations.index');

    Volt::route('physical-locations/{location}', 'admin.physical-locations.show')
        ->name('physical-locations.show');

    Volt::route('archive', 'admin.archive.index')
        ->name('archive.index');
});

Route::middleware(['auth', 'can:loan.view'])->prefix('admin')->name('admin.')->group(function () {
    Volt::route('loans', 'admin.loans.index')
        ->name('loans.index');
});

Route::middleware(['auth', 'can:audit.view'])->prefix('admin')->name('admin.')->group(function () {
    Volt::route('audit-logs', 'admin.audit-logs.index')
        ->name('audit-logs.index');
});

Route::middleware('throttle:120,1')->group(function () {
    Volt::route('pustaka', 'public.library.index')
        ->name('public.library.index');

    Volt::route('pustaka/{document}', 'public.library.show')
        ->name('public.library.show');

    Route::get('pustaka/{document}/berkas/{version}', function (\App\Models\Document $document, \App\Models\DocumentFileVersion $version) {
        abort_unless($document->visibility === \App\Models\Document::VISIBILITY_PUBLIC, 404);
        abort_unless($version->documentFile->document_id === $document->id, 404);

        return \Illuminate\Support\Facades\Storage::disk($version->disk)->download($version->path, $version->original_filename);
    })->name('public.library.download');
});

require __DIR__.'/auth.php';
