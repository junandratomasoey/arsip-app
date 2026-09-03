{{--
    Partial UI generik untuk master data sederhana berbentuk daftar datar
    (bukan tree): kode + nama + aktif + urutan. Dipakai oleh komponen Volt
    Jenis Pekerjaan, Fase, dan Status Pekerjaan - method & property yang
    dipanggil di sini (createNew, edit, save, dst.) harus ada persis dengan
    nama yang sama di komponen Volt yang meng-include partial ini.
--}}

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $title }}</h2>
            <button type="button" wire:click="createNew" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                + Tambah
            </button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Kode</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Nama</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Urutan</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Status</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500 uppercase text-xs">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($items as $item)
                            <tr>
                                <td class="px-4 py-2 text-gray-500">{{ $item['code'] }}</td>
                                <td class="px-4 py-2 {{ $item['is_active'] ? 'text-gray-900' : 'text-gray-400 line-through' }}">{{ $item['name'] }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $item['order_column'] }}</td>
                                <td class="px-4 py-2">
                                    <span class="text-[10px] font-semibold uppercase tracking-wide rounded px-1.5 py-0.5 {{ $item['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-500' }}">
                                        {{ $item['is_active'] ? 'aktif' : 'nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right space-x-3 text-xs">
                                    <button type="button" wire:click="edit({{ $item['id'] }})" class="text-indigo-600 hover:underline">ubah</button>
                                    <button type="button" wire:click="toggleActive({{ $item['id'] }})" class="text-amber-600 hover:underline">{{ $item['is_active'] ? 'nonaktifkan' : 'aktifkan' }}</button>
                                    <button type="button" wire:click="confirmDelete({{ $item['id'] }})" class="text-red-600 hover:underline">hapus</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-400 italic">Belum ada data.</td>
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

            <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-md sm:mx-auto">
                <form wire:submit="save" class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        {{ $editingId ? 'Ubah ' . $title : $title . ' Baru' }}
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <x-input-label for="name" value="Nama" />
                            <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('name')" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="code" value="Kode" />
                                <x-text-input wire:model="code" id="code" type="text" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('code')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="orderColumn" value="Urutan" />
                                <x-text-input wire:model="orderColumn" id="orderColumn" type="number" min="0" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('orderColumn')" class="mt-1" />
                            </div>
                        </div>

                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" wire:model="isActive" class="rounded border-gray-300 text-indigo-600 shadow-sm">
                            <span class="text-sm text-gray-700">Aktif</span>
                        </label>
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
                <h3 class="text-lg font-medium text-gray-900">Hapus data ini?</h3>
                <p class="mt-2 text-sm text-gray-500">Tindakan ini tidak bisa dibatalkan.</p>

                @if ($deleteError)
                    <p class="mt-3 text-sm text-red-600">{{ $deleteError }}</p>
                @endif

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
