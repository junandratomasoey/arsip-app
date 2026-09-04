{{--
    Blok pengguna di bagian bawah sidebar (profil singkat + keluar).
    Sama seperti components/sidebar-menu.blade.php, dipakai baik di panel
    sidebar layar besar maupun laci menu layar kecil.
--}}
<div class="mt-auto border-t border-white/10 px-4 py-4">
    <div
        class="text-sm font-medium text-white"
        x-data="{{ json_encode(['name' => auth()->user()->name]) }}"
        x-text="name"
        x-on:profile-updated.window="name = $event.detail.name"
    ></div>
    <div class="text-xs text-white/50 truncate">{{ auth()->user()->email }}</div>

    <div class="mt-3 flex items-center gap-4 text-xs font-medium">
        <a href="{{ route('profile') }}" wire:navigate class="text-white/70 hover:text-white">
            {{ __('Profil') }}
        </a>

        <button type="button" wire:click="logout" class="text-white/70 hover:text-white">
            {{ __('Keluar') }}
        </button>
    </div>
</div>
