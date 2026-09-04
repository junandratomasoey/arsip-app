<?php

use App\Livewire\Actions\Logout;
use App\Models\AuditLog;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        AuditLog::record('auth.logout', auth()->user(), 'Logout: ' . auth()->user()->name);

        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div x-data="{ mobileOpen: false }">
    {{-- Bar atas untuk layar kecil: logo + tombol buka menu. Sidebar
         sesungguhnya di layar kecil muncul sebagai laci (drawer) di
         bawah ini, bukan panel tetap seperti di layar besar. --}}
    <div class="lg:hidden sticky top-0 z-30 flex items-center justify-between gap-3 bg-white px-4 py-3 shadow">
        <a href="{{ route('dashboard') }}" wire:navigate>
            <img src="{{ asset('images/logo-kemenpu-compact.png') }}" alt="Kementerian PU" class="h-7 w-auto">
        </a>

        <button type="button" @click="mobileOpen = true" class="p-2 -mr-2 text-pu-navy-500 hover:text-pu-navy-700">
            <span class="sr-only">{{ __('Buka menu') }}</span>
            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    {{-- Laci menu untuk layar kecil --}}
    <div x-show="mobileOpen" x-cloak class="lg:hidden fixed inset-0 z-40" role="dialog" aria-modal="true">
        <div x-show="mobileOpen" x-transition.opacity @click="mobileOpen = false" class="fixed inset-0 bg-black/50"></div>

        <div
            x-show="mobileOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="fixed inset-y-0 left-0 flex w-72 max-w-[85%] flex-col bg-pu-navy-500"
        >
            <div class="flex items-center justify-between bg-white px-4 py-4">
                <img src="{{ asset('images/logo-kemenpu-compact.png') }}" alt="Kementerian PU" class="h-8 w-auto">

                <button type="button" @click="mobileOpen = false" class="p-2 -mr-2 text-pu-navy-500 hover:text-pu-navy-700">
                    <span class="sr-only">{{ __('Tutup menu') }}</span>
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            @include('components.sidebar-menu')

            @include('components.sidebar-user')
        </div>
    </div>

    {{-- Panel sidebar tetap untuk layar besar --}}
    <div class="hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-30 lg:flex lg:w-64 lg:flex-col bg-pu-navy-500">
        <div class="bg-white px-4 py-5">
            <a href="{{ route('dashboard') }}" wire:navigate>
                <img src="{{ asset('images/logo-kemenpu-compact.png') }}" alt="Kementerian PU" class="h-9 w-auto">
            </a>
        </div>

        @include('components.sidebar-menu')

        @include('components.sidebar-user')
    </div>
</div>
