<?php

use App\Models\AuditLog;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $email = '';
    public ?int $unitId = null;
    public string $role = '';
    public string $password = '';

    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('user.manage'), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'users' => User::query()
                ->with(['roles', 'organizationalUnit'])
                ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")))
                ->orderBy('name')
                ->paginate(20),
            'roles' => Role::orderBy('name')->pluck('name'),
            'units' => OrganizationalUnit::where('is_active', true)->orderBy('order_column')->get(),
        ];
    }

    public function createNew(): void
    {
        $this->reset(['editingId', 'name', 'email', 'unitId', 'role', 'password']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $user = User::with('roles')->findOrFail($id);

        $this->resetValidation();
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->unitId = $user->organizational_unit_id;
        $this->role = $user->roles->first()?->name ?? '';
        $this->password = '';
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('user.manage'), 403);

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . ($this->editingId ?? 'NULL') . ',id',
            'unitId' => 'nullable|exists:organizational_units,id',
            'role' => 'required|exists:roles,name',
            'password' => [$this->editingId ? 'nullable' : 'required', Password::min(8)],
        ], [], ['name' => 'nama', 'role' => 'peran', 'password' => 'kata sandi', 'unitId' => 'unit']);

        // Jangan sampai admin mencabut akses kelola pengguna dari dirinya sendiri.
        if ($this->editingId === auth()->id() && ! Role::findByName($this->role, 'web')->hasPermissionTo('user.manage')) {
            $this->addError('role', 'Anda tidak bisa mengganti peran Anda sendiri ke peran tanpa akses kelola pengguna.');

            return;
        }

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'organizational_unit_id' => $this->unitId ?: null,
        ];

        if ($this->password !== '') {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $user->update($data);
        } else {
            $user = User::create($data);
            // Akun dibuat admin dianggap sudah terverifikasi (dashboard memakai middleware verified).
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->syncRoles([$this->role]);

        AuditLog::record($this->editingId ? 'user.updated' : 'user.created', $user, ($this->editingId ? 'Ubah pengguna: ' : 'Buat pengguna: ') . $user->name . ' (' . $this->role . ')');

        $this->showForm = false;
        session()->flash('status', 'Data pengguna disimpan.');
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        abort_unless(auth()->user()->can('user.manage'), 403);

        if ($this->confirmingDeleteId === auth()->id()) {
            $this->confirmingDeleteId = null;
            session()->flash('status', 'Anda tidak bisa menghapus akun Anda sendiri.');

            return;
        }

        $user = User::findOrFail($this->confirmingDeleteId);

        // Riwayat disposisi adalah bagian arsip surat; jangan ikut terhapus.
        if (\App\Models\LetterDisposition::where('to_user_id', $user->id)->exists()) {
            $this->confirmingDeleteId = null;
            session()->flash('status', 'Pengguna ini punya riwayat disposisi surat sehingga tidak bisa dihapus. Ganti kata sandinya atau ubah perannya ke Staf.');

            return;
        }

        AuditLog::record('user.deleted', $user, 'Hapus pengguna: ' . $user->name);
        $user->delete();

        $this->confirmingDeleteId = null;
        session()->flash('status', 'Pengguna dihapus.');
    }
}; ?>

<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengguna</h2>
</x-slot>

<div>
    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-md px-4 py-3">{{ session('status') }}</div>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3">
                <x-text-input wire:model.live.debounce.400ms="search" type="search" placeholder="Cari nama atau email..." class="w-full sm:w-72 text-sm" />
                <button type="button" wire:click="createNew" class="inline-flex items-center px-4 py-2 bg-pu-navy border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-pu-navy-700">+ Tambah Pengguna</button>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Nama</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Unit</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Peran</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500 uppercase text-xs">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($users as $user)
                            <tr wire:key="u-{{ $user->id }}">
                                <td class="px-4 py-2">
                                    <span class="text-gray-900">{{ $user->name }}</span>
                                    <p class="text-xs text-gray-400">{{ $user->email }}</p>
                                </td>
                                <td class="px-4 py-2 text-gray-600">{{ $user->organizationalUnit?->name ?? '-' }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $user->roles->pluck('name')->implode(', ') ?: '-' }}</td>
                                <td class="px-4 py-2 text-right space-x-3 text-xs whitespace-nowrap">
                                    <button type="button" wire:click="edit({{ $user->id }})" class="text-pu-navy-600 hover:underline">ubah</button>
                                    @if ($user->id !== auth()->id())
                                        <button type="button" wire:click="confirmDelete({{ $user->id }})" class="text-red-600 hover:underline">hapus</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div>{{ $users->links() }}</div>
        </div>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-500/75" wire:click="closeForm"></div>
            <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-lg sm:mx-auto">
                <form wire:submit="save" class="p-6 space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">{{ $editingId ? 'Ubah Pengguna' : 'Pengguna Baru' }}</h3>
                    <div>
                        <x-input-label for="name" value="Nama *" />
                        <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="email" value="Email *" />
                        <x-text-input wire:model="email" id="email" type="email" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="unitId" value="Unit Kerja" />
                        <x-select-input wire:model="unitId" id="unitId" class="mt-1 block w-full">
                            <option value="">-</option>
                            @foreach ($units as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </x-select-input>
                    </div>
                    <div>
                        <x-input-label for="role" value="Peran *" />
                        <x-select-input wire:model="role" id="role" class="mt-1 block w-full">
                            <option value="">-</option>
                            @foreach ($roles as $r)
                                <option value="{{ $r }}">{{ $r }}</option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->get('role')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="password" value="{{ $editingId ? 'Kata Sandi Baru (kosongkan jika tidak diganti)' : 'Kata Sandi *' }}" />
                        <x-text-input wire:model="password" id="password" type="password" autocomplete="new-password" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('password')" class="mt-1" />
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeForm" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">Batal</button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-pu-navy border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-pu-navy-700">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($confirmingDeleteId)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-500/75" wire:click="cancelDelete"></div>
            <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-md sm:mx-auto p-6">
                <h3 class="text-lg font-medium text-gray-900">Hapus pengguna ini?</h3>
                <p class="mt-2 text-sm text-gray-500">Pengguna yang punya riwayat disposisi surat tidak bisa dihapus.</p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="cancelDelete" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">Batal</button>
                    <button type="button" wire:click="delete" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">Hapus</button>
                </div>
            </div>
        </div>
    @endif
</div>
