<?php

use App\Models\AuditLog;
use App\Models\OrganizationalUnit;
use App\Models\Tag;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkStatus;
use App\Models\WorkType;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public ?Work $work = null;

    public string $code = '';
    public string $name = '';
    public string $packageName = '';
    public $coverUpload = null;
    public ?string $existingCoverPath = null;
    public bool $removeCover = false;
    public ?int $workTypeId = null;
    public ?int $fiscalYear = null;
    public string $fundingSource = '';
    public string $province = '';
    public string $regency = '';
    public string $district = '';
    public string $village = '';
    public string $locationDescription = '';

    public string $contractNumber = '';
    public ?string $contractDate = null;
    public ?float $contractValue = null;
    public string $spmkNumber = '';
    public ?string $spmkDate = null;
    public string $bastNumber = '';
    public ?string $bastDate = null;

    public ?string $startDate = null;
    public ?string $endDate = null;

    public string $providerName = '';
    public string $providerAddress = '';
    public string $providerNpwp = '';
    public string $providerLeaderName = '';
    public string $providerRole = '';

    public ?int $satkerUnitId = null;
    public ?int $ppkUnitId = null;
    public ?int $bidangUnitId = null;
    public ?int $personInChargeId = null;
    public ?int $workStatusId = null;

    public array $selectedTagIds = [];

    public array $workTypeOptions = [];
    public array $workStatusOptions = [];
    public array $satkerOptions = [];
    public array $ppkOptions = [];
    public array $bidangOptions = [];
    public array $userOptions = [];
    public array $tagOptions = [];

    public function mount(?Work $work = null): void
    {
        $this->work = $work;

        if ($this->work) {
            abort_unless(auth()->user()->can('work.update'), 403);

            $this->code = $this->work->code;
            $this->name = $this->work->name;
            $this->packageName = (string) $this->work->package_name;
            $this->existingCoverPath = $this->work->cover_path;
            $this->workTypeId = $this->work->work_type_id;
            $this->fiscalYear = $this->work->fiscal_year;
            $this->fundingSource = (string) $this->work->funding_source;
            $this->province = (string) $this->work->province;
            $this->regency = (string) $this->work->regency;
            $this->district = (string) $this->work->district;
            $this->village = (string) $this->work->village;
            $this->locationDescription = (string) $this->work->location_description;

            $this->contractNumber = (string) $this->work->contract_number;
            $this->contractDate = $this->work->contract_date?->format('Y-m-d');
            $this->contractValue = $this->work->contract_value !== null ? (float) $this->work->contract_value : null;
            $this->spmkNumber = (string) $this->work->spmk_number;
            $this->spmkDate = $this->work->spmk_date?->format('Y-m-d');
            $this->bastNumber = (string) $this->work->bast_number;
            $this->bastDate = $this->work->bast_date?->format('Y-m-d');

            $this->startDate = $this->work->start_date?->format('Y-m-d');
            $this->endDate = $this->work->end_date?->format('Y-m-d');

            $this->providerName = (string) $this->work->provider_name;
            $this->providerAddress = (string) $this->work->provider_address;
            $this->providerNpwp = (string) $this->work->provider_npwp;
            $this->providerLeaderName = (string) $this->work->provider_leader_name;
            $this->providerRole = (string) $this->work->provider_role;

            $this->satkerUnitId = $this->work->satker_unit_id;
            $this->ppkUnitId = $this->work->ppk_unit_id;
            $this->bidangUnitId = $this->work->bidang_unit_id;
            $this->personInChargeId = $this->work->person_in_charge_id;
            $this->workStatusId = $this->work->work_status_id;

            $this->selectedTagIds = $this->work->tags()->pluck('tags.id')->all();
        } else {
            abort_unless(auth()->user()->can('work.create'), 403);
        }

        $this->workTypeOptions = WorkType::where('is_active', true)->orderBy('order_column')->get()
            ->map(fn ($w) => ['id' => $w->id, 'name' => $w->name])->all();

        $this->workStatusOptions = WorkStatus::where('is_active', true)->orderBy('order_column')->get()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->all();

        $this->satkerOptions = OrganizationalUnit::where('type', 'satker')->where('is_active', true)->orderBy('name')->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all();

        $this->ppkOptions = OrganizationalUnit::where('type', 'ppk')->where('is_active', true)->orderBy('name')->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all();

        $this->bidangOptions = OrganizationalUnit::where('type', 'bidang')->where('is_active', true)->orderBy('name')->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all();

        $this->userOptions = User::orderBy('name')->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all();

        $this->tagOptions = Tag::orderBy('name')->get()
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->all();
    }

    public function markRemoveCover(): void
    {
        $this->coverUpload = null;
        $this->removeCover = true;
    }

    public function undoRemoveCover(): void
    {
        $this->removeCover = false;
    }

    public function save(): void
    {
        $this->validate([
            'code' => 'required|string|max:255|unique:works,code,' . ($this->work?->id ?? 'NULL') . ',id',
            'name' => 'required|string|max:255',
            'coverUpload' => 'nullable|image|max:2048',
            'workTypeId' => 'nullable|exists:work_types,id',
            'fiscalYear' => 'nullable|integer|min:2000|max:2100',
            'contractDate' => 'nullable|date',
            'contractValue' => 'nullable|numeric|min:0',
            'spmkDate' => 'nullable|date',
            'bastDate' => 'nullable|date',
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date|after_or_equal:startDate',
            'providerRole' => 'nullable|in:konsultan,kontraktor,konsultan_dan_kontraktor',
            'satkerUnitId' => 'nullable|exists:organizational_units,id',
            'ppkUnitId' => 'nullable|exists:organizational_units,id',
            'bidangUnitId' => 'nullable|exists:organizational_units,id',
            'personInChargeId' => 'nullable|exists:users,id',
            'workStatusId' => 'nullable|exists:work_statuses,id',
        ]);

        $coverPath = $this->existingCoverPath;

        if ($this->coverUpload) {
            if ($coverPath) {
                Storage::disk('public')->delete($coverPath);
            }

            $coverPath = $this->coverUpload->store('covers', 'public');
        } elseif ($this->removeCover && $coverPath) {
            Storage::disk('public')->delete($coverPath);
            $coverPath = null;
        }

        $data = [
            'code' => $this->code,
            'name' => $this->name,
            'package_name' => $this->packageName !== '' ? $this->packageName : null,
            'cover_path' => $coverPath,
            'work_type_id' => $this->workTypeId,
            'fiscal_year' => $this->fiscalYear,
            'funding_source' => $this->fundingSource !== '' ? $this->fundingSource : null,
            'province' => $this->province !== '' ? $this->province : null,
            'regency' => $this->regency !== '' ? $this->regency : null,
            'district' => $this->district !== '' ? $this->district : null,
            'village' => $this->village !== '' ? $this->village : null,
            'location_description' => $this->locationDescription !== '' ? $this->locationDescription : null,

            'contract_number' => $this->contractNumber !== '' ? $this->contractNumber : null,
            'contract_date' => $this->contractDate ?: null,
            'contract_value' => $this->contractValue,
            'spmk_number' => $this->spmkNumber !== '' ? $this->spmkNumber : null,
            'spmk_date' => $this->spmkDate ?: null,
            'bast_number' => $this->bastNumber !== '' ? $this->bastNumber : null,
            'bast_date' => $this->bastDate ?: null,

            'start_date' => $this->startDate ?: null,
            'end_date' => $this->endDate ?: null,

            'provider_name' => $this->providerName !== '' ? $this->providerName : null,
            'provider_address' => $this->providerAddress !== '' ? $this->providerAddress : null,
            'provider_npwp' => $this->providerNpwp !== '' ? $this->providerNpwp : null,
            'provider_leader_name' => $this->providerLeaderName !== '' ? $this->providerLeaderName : null,
            'provider_role' => $this->providerRole !== '' ? $this->providerRole : null,

            'satker_unit_id' => $this->satkerUnitId,
            'ppk_unit_id' => $this->ppkUnitId,
            'bidang_unit_id' => $this->bidangUnitId,
            'person_in_charge_id' => $this->personInChargeId,
            'work_status_id' => $this->workStatusId,
        ];

        $isNew = ! $this->work;

        if ($this->work) {
            $this->work->update($data);
            $work = $this->work;
        } else {
            $data['created_by'] = auth()->id();
            $work = Work::create($data);
        }

        $work->tags()->sync($this->selectedTagIds);

        AuditLog::record(
            $isNew ? 'work.created' : 'work.updated',
            $work,
            ($isNew ? 'Buat pekerjaan: ' : 'Ubah pekerjaan: ') . $work->code . ' - ' . $work->name
        );

        session()->flash('status', 'Data pekerjaan berhasil disimpan.');

        $this->redirect(route('admin.works.index'), navigate: true);
    }
}; ?>

<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ $work ? 'Ubah Pekerjaan' : 'Pekerjaan Baru' }}
    </h2>
</x-slot>

<div class="py-12">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
        <form wire:submit="save" class="space-y-6">

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Identitas</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="code" value="Kode Pekerjaan *" />
                        <x-text-input wire:model="code" id="code" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('code')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="name" value="Nama Pekerjaan *" />
                        <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="packageName" value="Nama Paket" />
                        <x-text-input wire:model="packageName" id="packageName" type="text" class="mt-1 block w-full" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="coverUpload" value="Cover / Sampul" />
                        <p class="text-xs text-gray-400 mb-2">Ditampilkan di Perpustakaan Digital Publik. Format gambar, maks 2MB.</p>

                        @if ($coverUpload)
                            <div class="mb-2 flex items-center gap-3">
                                <img src="{{ $coverUpload->temporaryUrl() }}" class="h-24 w-24 object-cover rounded-md border border-gray-200">
                                <span class="text-xs text-gray-500">Cover baru (belum disimpan)</span>
                            </div>
                        @elseif ($existingCoverPath && ! $removeCover)
                            <div class="mb-2 flex items-center gap-3">
                                <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($existingCoverPath) }}" class="h-24 w-24 object-cover rounded-md border border-gray-200">
                                <button type="button" wire:click="markRemoveCover" class="text-xs text-red-600 hover:underline">Hapus cover</button>
                            </div>
                        @elseif ($removeCover)
                            <p class="text-xs text-amber-600 mb-2">Cover akan dihapus saat disimpan. <button type="button" wire:click="undoRemoveCover" class="underline">Batalkan</button></p>
                        @endif

                        <input type="file" wire:model="coverUpload" id="coverUpload" accept="image/*" class="block w-full text-sm text-gray-600">
                        <div wire:loading wire:target="coverUpload" class="text-xs text-gray-400 mt-1">Mengunggah...</div>
                        <x-input-error :messages="$errors->get('coverUpload')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="workTypeId" value="Jenis Pekerjaan" />
                        <x-select-input wire:model="workTypeId" id="workTypeId" class="mt-1 block w-full">
                            <option value="">- Pilih -</option>
                            @foreach ($workTypeOptions as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->get('workTypeId')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="fiscalYear" value="Tahun Anggaran" />
                        <x-text-input wire:model="fiscalYear" id="fiscalYear" type="number" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('fiscalYear')" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="fundingSource" value="Sumber Dana" />
                        <x-text-input wire:model="fundingSource" id="fundingSource" type="text" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <x-input-label for="province" value="Provinsi" />
                        <x-text-input wire:model="province" id="province" type="text" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <x-input-label for="regency" value="Kabupaten/Kota" />
                        <x-text-input wire:model="regency" id="regency" type="text" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <x-input-label for="district" value="Kecamatan" />
                        <x-text-input wire:model="district" id="district" type="text" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <x-input-label for="village" value="Desa/Kelurahan" />
                        <x-text-input wire:model="village" id="village" type="text" class="mt-1 block w-full" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="locationDescription" value="Keterangan Lokasi" />
                        <textarea wire:model="locationDescription" id="locationDescription" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Kontrak</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="contractNumber" value="Nomor Kontrak" />
                        <x-text-input wire:model="contractNumber" id="contractNumber" type="text" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <x-input-label for="contractDate" value="Tanggal Kontrak" />
                        <x-text-input wire:model="contractDate" id="contractDate" type="date" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('contractDate')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="contractValue" value="Nilai Kontrak (Rp)" />
                        <x-text-input wire:model="contractValue" id="contractValue" type="number" step="0.01" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('contractValue')" class="mt-1" />
                    </div>
                    <div></div>
                    <div>
                        <x-input-label for="spmkNumber" value="Nomor SPMK" />
                        <x-text-input wire:model="spmkNumber" id="spmkNumber" type="text" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <x-input-label for="spmkDate" value="Tanggal SPMK" />
                        <x-text-input wire:model="spmkDate" id="spmkDate" type="date" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('spmkDate')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="bastNumber" value="Nomor BAST" />
                        <x-text-input wire:model="bastNumber" id="bastNumber" type="text" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <x-input-label for="bastDate" value="Tanggal BAST" />
                        <x-text-input wire:model="bastDate" id="bastDate" type="date" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('bastDate')" class="mt-1" />
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Masa Pelaksanaan</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="startDate" value="Tanggal Mulai" />
                        <x-text-input wire:model="startDate" id="startDate" type="date" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('startDate')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="endDate" value="Tanggal Selesai" />
                        <x-text-input wire:model="endDate" id="endDate" type="date" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('endDate')" class="mt-1" />
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Penyedia Jasa</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <x-input-label for="providerName" value="Nama Penyedia" />
                        <x-text-input wire:model="providerName" id="providerName" type="text" class="mt-1 block w-full" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="providerAddress" value="Alamat Penyedia" />
                        <textarea wire:model="providerAddress" id="providerAddress" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                    </div>
                    <div>
                        <x-input-label for="providerNpwp" value="NPWP Penyedia" />
                        <x-text-input wire:model="providerNpwp" id="providerNpwp" type="text" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <x-input-label for="providerLeaderName" value="Nama Pimpinan/Direktur" />
                        <x-text-input wire:model="providerLeaderName" id="providerLeaderName" type="text" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <x-input-label for="providerRole" value="Peran Penyedia" />
                        <x-select-input wire:model="providerRole" id="providerRole" class="mt-1 block w-full">
                            <option value="">- Pilih -</option>
                            <option value="konsultan">Konsultan</option>
                            <option value="kontraktor">Kontraktor</option>
                            <option value="konsultan_dan_kontraktor">Konsultan dan Kontraktor</option>
                        </x-select-input>
                        <x-input-error :messages="$errors->get('providerRole')" class="mt-1" />
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Internal</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="satkerUnitId" value="Satker" />
                        <x-select-input wire:model="satkerUnitId" id="satkerUnitId" class="mt-1 block w-full">
                            <option value="">- Pilih -</option>
                            @foreach ($satkerOptions as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->get('satkerUnitId')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="ppkUnitId" value="PPK" />
                        <x-select-input wire:model="ppkUnitId" id="ppkUnitId" class="mt-1 block w-full">
                            <option value="">- Pilih -</option>
                            @foreach ($ppkOptions as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->get('ppkUnitId')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="bidangUnitId" value="Bidang" />
                        <x-select-input wire:model="bidangUnitId" id="bidangUnitId" class="mt-1 block w-full">
                            <option value="">- Pilih -</option>
                            @foreach ($bidangOptions as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->get('bidangUnitId')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="personInChargeId" value="Penanggung Jawab" />
                        <x-select-input wire:model="personInChargeId" id="personInChargeId" class="mt-1 block w-full">
                            <option value="">- Pilih -</option>
                            @foreach ($userOptions as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->get('personInChargeId')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="workStatusId" value="Status Pekerjaan" />
                        <x-select-input wire:model="workStatusId" id="workStatusId" class="mt-1 block w-full">
                            <option value="">- Pilih -</option>
                            @foreach ($workStatusOptions as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->get('workStatusId')" class="mt-1" />
                    </div>
                </div>

                @if (count($tagOptions))
                    <div class="mt-4">
                        <x-input-label value="Tags" />
                        <div class="mt-2 flex flex-wrap gap-3">
                            @foreach ($tagOptions as $opt)
                                <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                                    <input type="checkbox" wire:model="selectedTagIds" value="{{ $opt['id'] }}" class="rounded border-gray-300 text-indigo-600 shadow-sm">
                                    {{ $opt['name'] }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.works.index') }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                    Batal
                </a>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>
