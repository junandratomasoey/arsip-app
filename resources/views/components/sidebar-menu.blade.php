{{--
    Daftar menu sidebar, dikelompokkan per kategori. Sengaja dipisah jadi
    partial sendiri (bukan ditulis langsung di navigation.blade.php) karena
    dipakai dua kali: panel sidebar tetap di layar besar, dan laci
    (drawer) yang muncul di layar kecil - supaya daftar menunya cukup
    ditulis satu kali saja.
--}}
@php
    $itemClasses = fn (bool $active) => $active
        ? 'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-semibold bg-pu-gold text-pu-navy-900'
        : 'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-white/80 hover:bg-pu-navy-600 hover:text-white transition-colors';

    $groupLabelClasses = 'px-3 mt-6 mb-2 text-[11px] font-semibold uppercase tracking-wider text-white/40';
@endphp

<nav class="flex-1 px-3 pb-6 space-y-1 overflow-y-auto">
    <a href="{{ route('dashboard') }}" wire:navigate class="{{ $itemClasses(request()->routeIs('dashboard')) }}">
        {{ __('Dashboard') }}
    </a>

    @can('work.view')
        <p class="{{ $groupLabelClasses }}">{{ __('Pekerjaan') }}</p>

        <a href="{{ route('admin.works.index') }}" wire:navigate class="{{ $itemClasses(request()->routeIs('admin.works.*') && ! request()->routeIs('admin.works.import')) }}">
            {{ __('Pekerjaan') }}
        </a>

        @can('work.create')
            <a href="{{ route('admin.works.import') }}" wire:navigate class="{{ $itemClasses(request()->routeIs('admin.works.import')) }}">
                {{ __('Impor Excel') }}
            </a>
        @endcan
    @endcan

    @canany(['organization.manage', 'settings.manage'])
        <p class="{{ $groupLabelClasses }}">{{ __('Data Master') }}</p>

        @can('organization.manage')
            <a href="{{ route('admin.organizational-units.index') }}" wire:navigate class="{{ $itemClasses(request()->routeIs('admin.organizational-units.index')) }}">
                {{ __('Struktur Organisasi') }}
            </a>
        @endcan

        @can('settings.manage')
            <a href="{{ route('admin.work-types.index') }}" wire:navigate class="{{ $itemClasses(request()->routeIs('admin.work-types.index')) }}">
                {{ __('Jenis Pekerjaan') }}
            </a>

            <a href="{{ route('admin.phases.index') }}" wire:navigate class="{{ $itemClasses(request()->routeIs('admin.phases.index')) }}">
                {{ __('Fase') }}
            </a>

            <a href="{{ route('admin.work-statuses.index') }}" wire:navigate class="{{ $itemClasses(request()->routeIs('admin.work-statuses.index')) }}">
                {{ __('Status Pekerjaan') }}
            </a>

            <a href="{{ route('admin.tags.index') }}" wire:navigate class="{{ $itemClasses(request()->routeIs('admin.tags.index')) }}">
                {{ __('Tags') }}
            </a>
        @endcan
    @endcanany

    @can('archive.view')
        <p class="{{ $groupLabelClasses }}">{{ __('Arsip') }}</p>

        <a href="{{ route('admin.physical-locations.index') }}" wire:navigate class="{{ $itemClasses(request()->routeIs('admin.physical-locations.*')) }}">
            {{ __('Lokasi Fisik') }}
        </a>

        <a href="{{ route('admin.archive.index') }}" wire:navigate class="{{ $itemClasses(request()->routeIs('admin.archive.index')) }}">
            {{ __('Penempatan Arsip') }}
        </a>
    @endcan

    <p class="{{ $groupLabelClasses }}">{{ __('Layanan') }}</p>

    @can('loan.view')
        <a href="{{ route('admin.loans.index') }}" wire:navigate class="{{ $itemClasses(request()->routeIs('admin.loans.index')) }}">
            {{ __('Peminjaman') }}
        </a>
    @endcan

    <a href="{{ route('public.library.index') }}" wire:navigate class="{{ $itemClasses(request()->routeIs('public.library.*')) }}">
        {{ __('Perpustakaan Publik') }}
    </a>

    @can('audit.view')
        <p class="{{ $groupLabelClasses }}">{{ __('Sistem') }}</p>

        <a href="{{ route('admin.audit-logs.index') }}" wire:navigate class="{{ $itemClasses(request()->routeIs('admin.audit-logs.index')) }}">
            {{ __('Log Audit') }}
        </a>
    @endcan
</nav>
