<?php

use App\Models\AuditLog;
use App\Models\OrganizationalUnit;
use App\Models\Work;
use App\Models\WorkType;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Impor massal Pekerjaan dari file Excel. Sengaja hanya mengisi field
 * utama (kode, nama, nama paket, jenis pekerjaan, tahun anggaran,
 * satker, ppk) - field lain (kontrak, masa pelaksanaan, penyedia jasa,
 * dll) dilengkapi manual lewat form Ubah Pekerjaan setelah data masuk,
 * supaya template tetap ringkas dan resiko salah input kecil.
 *
 * Alur dua langkah (baca dulu, baru impor) supaya pengguna bisa
 * meninjau hasil pembacaan/pencocokan sebelum data benar-benar masuk -
 * tidak ada baris yang langsung tersimpan hanya dari mengunggah file.
 */
new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public $file = null;

    public array $preview = [];
    public bool $hasParsed = false;
    public bool $imported = false;
    public ?string $globalError = null;

    public int $importedCount = 0;
    public int $skippedCount = 0;

    // Alias nama kolom header yang diterima (dibandingkan setelah
    // dinormalisasi: huruf kecil, spasi/tanda baca dibuang).
    private const HEADER_ALIASES = [
        'code' => ['kode', 'kodepekerjaan', 'code'],
        'name' => ['nama', 'namapekerjaan', 'name'],
        'package_name' => ['namapaket', 'paket', 'packagename'],
        'work_type' => ['jenispekerjaan', 'jenis', 'worktype'],
        'fiscal_year' => ['tahunanggaran', 'tahun', 'fiscalyear'],
        'satker' => ['satker'],
        'ppk' => ['ppk'],
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('work.create'), 403);
    }

    private function normalizeHeader(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim($value))) ?? '';
    }

    public function parse(): void
    {
        abort_unless(auth()->user()->can('work.create'), 403);

        $this->globalError = null;
        $this->preview = [];
        $this->hasParsed = false;

        $this->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:5120',
        ]);

        try {
            $spreadsheet = IOFactory::load($this->file->getRealPath());
        } catch (\Throwable $e) {
            $this->globalError = 'File tidak bisa dibaca. Pastikan file adalah Excel (.xlsx/.xls) yang valid dan tidak rusak.';

            return;
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if (count($rows) < 1) {
            $this->globalError = 'File kosong.';

            return;
        }

        $headerRow = array_shift($rows);
        $columnMap = [];

        foreach ($headerRow as $index => $cell) {
            $normalized = $this->normalizeHeader((string) $cell);

            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (! isset($columnMap[$field]) && in_array($normalized, $aliases, true)) {
                    $columnMap[$field] = $index;
                }
            }
        }

        if (! isset($columnMap['code']) || ! isset($columnMap['name'])) {
            $this->globalError = 'Kolom "Kode Pekerjaan" dan/atau "Nama Pekerjaan" tidak ditemukan di baris pertama file. Gunakan template yang disediakan supaya nama kolom cocok.';

            return;
        }

        // Data master dimuat sekali di awal (bukan per baris) supaya
        // pencocokan jenis pekerjaan/satker/ppk tidak query berulang.
        $workTypeLookup = [];

        foreach (WorkType::where('is_active', true)->get() as $workType) {
            $workTypeLookup[mb_strtolower(trim($workType->name))] = $workType->id;
            $workTypeLookup[mb_strtolower(trim($workType->code))] = $workType->id;
        }

        $satkerLookup = [];

        foreach (OrganizationalUnit::where('type', 'satker')->where('is_active', true)->get() as $unit) {
            $satkerLookup[mb_strtolower(trim($unit->name))] = $unit->id;

            if ($unit->code) {
                $satkerLookup[mb_strtolower(trim($unit->code))] = $unit->id;
            }
        }

        $ppkLookup = [];

        foreach (OrganizationalUnit::where('type', 'ppk')->where('is_active', true)->get() as $unit) {
            $ppkLookup[mb_strtolower(trim($unit->name))] = $unit->id;

            if ($unit->code) {
                $ppkLookup[mb_strtolower(trim($unit->code))] = $unit->id;
            }
        }

        // Kumpulkan semua kode dari file dulu supaya cek duplikat ke
        // database cukup satu query, bukan satu query per baris.
        $codesInFile = [];

        foreach ($rows as $row) {
            $code = trim((string) ($row[$columnMap['code']] ?? ''));

            if ($code !== '') {
                $codesInFile[] = $code;
            }
        }

        $existingCodes = Work::whereIn('code', $codesInFile)->pluck('name', 'code')->all();

        $seenInFile = [];
        $preview = [];
        $rowNumber = 1; // baris 1 = judul kolom

        foreach ($rows as $row) {
            $rowNumber++;

            $code = trim((string) ($row[$columnMap['code']] ?? ''));
            $name = trim((string) ($row[$columnMap['name']] ?? ''));

            // Lewati baris yang memang kosong total (mis. baris kosong di akhir file).
            if ($code === '' && $name === '') {
                continue;
            }

            $packageName = isset($columnMap['package_name'])
                ? trim((string) ($row[$columnMap['package_name']] ?? ''))
                : '';

            $workTypeRaw = isset($columnMap['work_type'])
                ? trim((string) ($row[$columnMap['work_type']] ?? ''))
                : '';
            $workTypeId = $workTypeRaw !== '' ? ($workTypeLookup[mb_strtolower($workTypeRaw)] ?? null) : null;

            $fiscalYearRaw = isset($columnMap['fiscal_year'])
                ? trim((string) ($row[$columnMap['fiscal_year']] ?? ''))
                : '';
            $fiscalYearDigits = preg_replace('/[^0-9]/', '', $fiscalYearRaw);
            $fiscalYear = $fiscalYearDigits !== '' ? (int) $fiscalYearDigits : null;

            $satkerRaw = isset($columnMap['satker'])
                ? trim((string) ($row[$columnMap['satker']] ?? ''))
                : '';
            $satkerUnitId = $satkerRaw !== '' ? ($satkerLookup[mb_strtolower($satkerRaw)] ?? null) : null;

            $ppkRaw = isset($columnMap['ppk'])
                ? trim((string) ($row[$columnMap['ppk']] ?? ''))
                : '';
            $ppkUnitId = $ppkRaw !== '' ? ($ppkLookup[mb_strtolower($ppkRaw)] ?? null) : null;

            $notes = [];
            $status = 'valid';

            if ($code === '') {
                $status = 'error';
                $notes[] = 'Kode pekerjaan kosong.';
            } elseif (mb_strlen($code) > 255) {
                $status = 'error';
                $notes[] = 'Kode pekerjaan terlalu panjang.';
            }

            if ($name === '') {
                $status = 'error';
                $notes[] = 'Nama pekerjaan kosong.';
            }

            if ($status !== 'error' && isset($existingCodes[$code])) {
                $status = 'duplicate';
                $notes[] = 'Kode sudah dipakai pekerjaan: ' . $existingCodes[$code] . ' (dilewati).';
            } elseif ($status !== 'error' && isset($seenInFile[$code])) {
                $status = 'duplicate';
                $notes[] = 'Kode duplikat dengan baris ' . $seenInFile[$code] . ' di file yang sama (dilewati).';
            }

            if ($workTypeRaw !== '' && $workTypeId === null) {
                $notes[] = 'Jenis pekerjaan "' . $workTypeRaw . '" tidak dikenali, dikosongkan.';
            }

            if ($fiscalYearRaw !== '' && $fiscalYear === null) {
                $notes[] = 'Tahun anggaran "' . $fiscalYearRaw . '" tidak valid, dikosongkan.';
            }

            if ($satkerRaw !== '' && $satkerUnitId === null) {
                $notes[] = 'Satker "' . $satkerRaw . '" tidak dikenali, dikosongkan.';
            }

            if ($ppkRaw !== '' && $ppkUnitId === null) {
                $notes[] = 'PPK "' . $ppkRaw . '" tidak dikenali, dikosongkan.';
            }

            if ($status === 'valid') {
                $seenInFile[$code] = $rowNumber;
            }

            $preview[] = [
                'row' => $rowNumber,
                'code' => $code,
                'name' => $name,
                'package_name' => $packageName !== '' ? $packageName : null,
                'work_type_id' => $workTypeId,
                'work_type_label' => $workTypeRaw !== '' ? $workTypeRaw : null,
                'fiscal_year' => $fiscalYear,
                'satker_unit_id' => $satkerUnitId,
                'satker_label' => $satkerRaw !== '' ? $satkerRaw : null,
                'ppk_unit_id' => $ppkUnitId,
                'ppk_label' => $ppkRaw !== '' ? $ppkRaw : null,
                'status' => $status,
                'notes' => $notes,
            ];
        }

        if (count($preview) === 0) {
            $this->globalError = 'Tidak ada baris data yang bisa dibaca dari file (selain baris judul kolom).';

            return;
        }

        $this->preview = $preview;
        $this->hasParsed = true;
    }

    public function import(): void
    {
        abort_unless(auth()->user()->can('work.create'), 403);

        $validRows = collect($this->preview)->filter(fn ($row) => $row['status'] === 'valid');

        if ($validRows->isEmpty()) {
            $this->globalError = 'Tidak ada baris valid untuk diimpor.';

            return;
        }

        $created = 0;

        DB::transaction(function () use ($validRows, &$created) {
            foreach ($validRows as $row) {
                $work = Work::create([
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'package_name' => $row['package_name'],
                    'work_type_id' => $row['work_type_id'],
                    'fiscal_year' => $row['fiscal_year'],
                    'satker_unit_id' => $row['satker_unit_id'],
                    'ppk_unit_id' => $row['ppk_unit_id'],
                    'created_by' => auth()->id(),
                ]);

                AuditLog::record(
                    'work.created',
                    $work,
                    'Buat pekerjaan (impor Excel): ' . $work->code . ' - ' . $work->name
                );

                $created++;
            }
        });

        $skipped = count($this->preview) - $created;

        AuditLog::record(
            'work.imported',
            null,
            "Impor Excel pekerjaan: {$created} dibuat, {$skipped} dilewati.",
            ['created' => $created, 'skipped' => $skipped]
        );

        $this->importedCount = $created;
        $this->skippedCount = $skipped;
        $this->imported = true;
        $this->preview = [];
        $this->hasParsed = false;
        $this->file = null;

        session()->flash('status', "Impor selesai: {$created} pekerjaan berhasil dibuat.");
    }

    public function startOver(): void
    {
        $this->file = null;
        $this->preview = [];
        $this->hasParsed = false;
        $this->globalError = null;
        $this->imported = false;
        $this->resetValidation();
    }
}; ?>

<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Impor Pekerjaan dari Excel</h2>
</x-slot>

<div>
<div class="py-12">
    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

        @if ($imported)
            <div class="bg-white shadow-sm rounded-lg p-6">
                <p class="text-sm text-gray-700">
                    Impor selesai: <span class="font-semibold text-green-700">{{ $importedCount }} pekerjaan berhasil dibuat</span>@if ($skippedCount > 0), <span class="font-semibold text-amber-700">{{ $skippedCount }} baris dilewati</span> (duplikat/error)@endif.
                </p>
                <div class="mt-4 flex gap-3">
                    <a href="{{ route('admin.works.index') }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                        Lihat Daftar Pekerjaan
                    </a>
                    <button type="button" wire:click="startOver" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                        Impor File Lain
                    </button>
                </div>
            </div>
        @else
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-900">1. Unduh template (disarankan)</h3>
                <p class="text-xs text-gray-500 mt-1">Kolom Kode dan Nama Pekerjaan wajib diisi. Kolom lain boleh dikosongkan dan dilengkapi manual lewat form nanti.</p>
                <a href="{{ route('admin.works.import.template') }}" class="mt-3 inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                    Unduh Template Excel
                </a>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-900">2. Unggah file Excel</h3>

                <div class="mt-3">
                    <input type="file" wire:model="file" accept=".xlsx,.xls" class="block w-full text-sm text-gray-600">
                    <div wire:loading wire:target="file" class="text-xs text-gray-400 mt-1">Mengunggah...</div>
                    <x-input-error :messages="$errors->get('file')" class="mt-1" />
                </div>

                @if ($globalError)
                    <p class="mt-3 text-sm text-red-600">{{ $globalError }}</p>
                @endif

                <div class="mt-4">
                    <button type="button" wire:click="parse" wire:loading.attr="disabled" wire:target="parse" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="parse">Baca File</span>
                        <span wire:loading wire:target="parse">Memproses...</span>
                    </button>
                </div>
            </div>

            @if ($hasParsed)
                @php
                    $validCount = collect($preview)->where('status', 'valid')->count();
                    $duplicateCount = collect($preview)->where('status', 'duplicate')->count();
                    $errorCount = collect($preview)->where('status', 'error')->count();
                @endphp

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-900">3. Tinjau hasil pembacaan file</h3>
                    <p class="text-xs text-gray-500 mt-1">
                        <span class="text-green-700 font-semibold">{{ $validCount }} siap diimpor</span> ·
                        <span class="text-amber-700 font-semibold">{{ $duplicateCount }} duplikat (dilewati)</span> ·
                        <span class="text-red-700 font-semibold">{{ $errorCount }} error (dilewati)</span>
                    </p>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500 uppercase tracking-wide">
                                    <th class="px-2 py-1">Baris</th>
                                    <th class="px-2 py-1">Kode</th>
                                    <th class="px-2 py-1">Nama</th>
                                    <th class="px-2 py-1">Jenis</th>
                                    <th class="px-2 py-1">Tahun</th>
                                    <th class="px-2 py-1">Satker</th>
                                    <th class="px-2 py-1">PPK</th>
                                    <th class="px-2 py-1">Status</th>
                                    <th class="px-2 py-1">Catatan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($preview as $row)
                                    <tr>
                                        <td class="px-2 py-1.5 text-gray-500 align-top">{{ $row['row'] }}</td>
                                        <td class="px-2 py-1.5 text-gray-900 align-top">{{ $row['code'] }}</td>
                                        <td class="px-2 py-1.5 text-gray-900 align-top">{{ $row['name'] }}</td>
                                        <td class="px-2 py-1.5 text-gray-600 align-top">{{ $row['work_type_label'] ?? '-' }}</td>
                                        <td class="px-2 py-1.5 text-gray-600 align-top">{{ $row['fiscal_year'] ?? '-' }}</td>
                                        <td class="px-2 py-1.5 text-gray-600 align-top">{{ $row['satker_label'] ?? '-' }}</td>
                                        <td class="px-2 py-1.5 text-gray-600 align-top">{{ $row['ppk_label'] ?? '-' }}</td>
                                        <td class="px-2 py-1.5 align-top">
                                            <span @class([
                                                'px-2 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide',
                                                'bg-green-100 text-green-700' => $row['status'] === 'valid',
                                                'bg-amber-100 text-amber-700' => $row['status'] === 'duplicate',
                                                'bg-red-100 text-red-700' => $row['status'] === 'error',
                                            ])>
                                                {{ $row['status'] === 'valid' ? 'Siap' : ($row['status'] === 'duplicate' ? 'Duplikat' : 'Error') }}
                                            </span>
                                        </td>
                                        <td class="px-2 py-1.5 text-gray-500 align-top">
                                            @foreach ($row['notes'] as $note)
                                                <div>{{ $note }}</div>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 flex gap-3">
                        <button type="button" wire:click="import" wire:loading.attr="disabled" wire:target="import" @disabled($validCount === 0) class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="import">Impor {{ $validCount }} Pekerjaan</span>
                            <span wire:loading wire:target="import">Mengimpor...</span>
                        </button>
                        <button type="button" wire:click="startOver" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                            Batal
                        </button>
                    </div>
                </div>
            @endif
        @endif

    </div>
</div>
</div>
