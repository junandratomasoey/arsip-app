<?php

use App\Models\Work;
use App\Models\WorkLocation;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Work $work;

    public array $locations = [];

    public ?string $editingId = null;
    public string $name = '';
    public string $type = 'lainnya';
    public string $description = '';
    public ?float $lat = null;
    public ?float $lng = null;

    public bool $showForm = false;
    public ?string $confirmingDeleteId = null;

    public array $typeOptions = [
        'bendungan' => 'Bendungan',
        'intake' => 'Intake',
        'outlet' => 'Outlet',
        'camp' => 'Camp',
        'akses' => 'Akses',
        'lainnya' => 'Lainnya',
    ];

    public function mount(Work $work): void
    {
        abort_unless(auth()->user()->can('work.view'), 403);

        $this->work = $work;
        $this->loadLocations();
    }

    public function loadLocations(): void
    {
        $this->locations = $this->work->locations()
            ->withLatLng()
            ->orderBy('name')
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'type' => $l->type,
                'description' => $l->description,
                'lat' => (float) $l->lat,
                'lng' => (float) $l->lng,
            ])->all();
    }

    public function createNew(): void
    {
        abort_unless(auth()->user()->can('work.update'), 403);

        $this->reset(['editingId', 'name', 'description']);
        $this->type = 'lainnya';
        $this->lat = -10.1772;
        $this->lng = 123.6070;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(string $id): void
    {
        abort_unless(auth()->user()->can('work.update'), 403);

        $location = collect($this->locations)->firstWhere('id', $id);
        abort_if(! $location, 404);

        $this->editingId = $location['id'];
        $this->name = $location['name'];
        $this->type = $location['type'] ?? 'lainnya';
        $this->description = (string) $location['description'];
        $this->lat = $location['lat'];
        $this->lng = $location['lng'];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('work.update'), 403);

        $this->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:30',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        if ($this->editingId) {
            $location = WorkLocation::where('work_id', $this->work->id)->findOrFail($this->editingId);
            $location->update([
                'name' => $this->name,
                'type' => $this->type,
                'description' => $this->description !== '' ? $this->description : null,
            ]);
            $location->updateLatLng($this->lat, $this->lng);
        } else {
            WorkLocation::createFromLatLng([
                'work_id' => $this->work->id,
                'name' => $this->name,
                'type' => $this->type,
                'description' => $this->description !== '' ? $this->description : null,
            ], $this->lat, $this->lng);
        }

        $this->showForm = false;
        $this->loadLocations();

        $this->dispatch('locations-updated', locations: $this->locations);
    }

    public function confirmDelete(string $id): void
    {
        abort_unless(auth()->user()->can('work.update'), 403);
        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        abort_unless(auth()->user()->can('work.update'), 403);

        $location = WorkLocation::where('work_id', $this->work->id)->findOrFail($this->confirmingDeleteId);
        $location->delete();

        $this->confirmingDeleteId = null;
        $this->loadLocations();

        $this->dispatch('locations-updated', locations: $this->locations);
    }
}; ?>

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
@endpush

<x-slot name="header">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Titik Lokasi</h2>
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

        @can('work.update')
            <div class="flex justify-end">
                <button type="button" wire:click="createNew" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    + Tambah Titik
                </button>
            </div>
        @endcan

        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            @if (count($locations))
                <div wire:ignore x-data="overviewMap(@js($locations))" id="overview-map" style="height: 400px"></div>
            @else
                <div class="p-10 text-center text-gray-400 italic">
                    Belum ada titik lokasi. Tambahkan titik pertama untuk melihat peta.
                </div>
            @endif
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Nama</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Jenis</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Koordinat</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Keterangan</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-500 uppercase text-xs">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($locations as $location)
                        <tr>
                            <td class="px-4 py-2 text-gray-900">{{ $location['name'] }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $typeOptions[$location['type']] ?? $location['type'] }}</td>
                            <td class="px-4 py-2 text-gray-500 whitespace-nowrap">{{ number_format($location['lat'], 6) }}, {{ number_format($location['lng'], 6) }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $location['description'] ?: '-' }}</td>
                            <td class="px-4 py-2 text-right space-x-3 text-xs whitespace-nowrap">
                                @can('work.update')
                                    <button type="button" wire:click="edit('{{ $location['id'] }}')" class="text-indigo-600 hover:underline">ubah</button>
                                    <button type="button" wire:click="confirmDelete('{{ $location['id'] }}')" class="text-red-600 hover:underline">hapus</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-400 italic">Belum ada titik lokasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if ($showForm)
    <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
        <div class="fixed inset-0 bg-gray-500/75" wire:click="closeForm"></div>

        <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-2xl sm:mx-auto">
            <form wire:submit="save" class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    {{ $editingId ? 'Ubah Titik Lokasi' : 'Titik Lokasi Baru' }}
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="name" value="Nama Titik" />
                        <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="type" value="Jenis" />
                        <x-select-input wire:model="type" id="type" class="mt-1 block w-full">
                            @foreach ($typeOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->get('type')" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="description" value="Keterangan" />
                        <textarea wire:model="description" id="description" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                    </div>
                </div>

                <div class="mt-4">
                    <x-input-label value="Klik di peta atau geser penanda untuk menentukan koordinat" />
                    <div wire:ignore x-data="locationPicker({{ $lat ?? -10.1772 }}, {{ $lng ?? 123.6070 }})" class="mt-1 rounded-md border border-gray-200" style="height: 350px"></div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="lat" value="Latitude" />
                        <x-text-input wire:model="lat" id="lat" type="number" step="0.000001" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('lat')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="lng" value="Longitude" />
                        <x-text-input wire:model="lng" id="lng" type="number" step="0.000001" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('lng')" class="mt-1" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="closeForm" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
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

@if ($confirmingDeleteId)
    <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
        <div class="fixed inset-0 bg-gray-500/75" wire:click="cancelDelete"></div>

        <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-md sm:mx-auto p-6">
            <h3 class="text-lg font-medium text-gray-900">Hapus titik lokasi ini?</h3>
            <p class="mt-2 text-sm text-gray-500">Tindakan ini tidak bisa dibatalkan.</p>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" wire:click="cancelDelete" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                    Batal
                </button>
                <button type="button" wire:click="delete" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">
                    Hapus
                </button>
            </div>
        </div>
    </div>
@endif
</div>

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('overviewMap', (initialLocations) => ({
                map: null,
                markers: [],
                init() {
                    this.map = L.map(this.$el).setView([-10.1772, 123.6070], 9);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors',
                        maxZoom: 19,
                    }).addTo(this.map);

                    this.renderMarkers(initialLocations);

                    window.addEventListener('locations-updated', (event) => {
                        this.renderMarkers(event.detail.locations);
                    });

                    setTimeout(() => this.map.invalidateSize(), 100);
                },
                renderMarkers(locations) {
                    this.markers.forEach((marker) => this.map.removeLayer(marker));
                    this.markers = [];

                    if (!locations.length) {
                        return;
                    }

                    const bounds = [];

                    locations.forEach((loc) => {
                        const marker = L.marker([loc.lat, loc.lng]).addTo(this.map);
                        marker.bindPopup('<strong>' + loc.name + '</strong><br>' + loc.type);
                        this.markers.push(marker);
                        bounds.push([loc.lat, loc.lng]);
                    });

                    this.map.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 });
                },
            }));

            Alpine.data('locationPicker', (lat, lng) => ({
                map: null,
                marker: null,
                init() {
                    this.map = L.map(this.$el).setView([lat, lng], 13);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors',
                        maxZoom: 19,
                    }).addTo(this.map);

                    this.marker = L.marker([lat, lng], { draggable: true }).addTo(this.map);

                    this.marker.on('dragend', () => {
                        const pos = this.marker.getLatLng();
                        this.$wire.set('lat', pos.lat);
                        this.$wire.set('lng', pos.lng);
                    });

                    this.map.on('click', (e) => {
                        this.marker.setLatLng(e.latlng);
                        this.$wire.set('lat', e.latlng.lat);
                        this.$wire.set('lng', e.latlng.lng);
                    });

                    setTimeout(() => this.map.invalidateSize(), 100);
                },
            }));
        });
    </script>
@endpush
