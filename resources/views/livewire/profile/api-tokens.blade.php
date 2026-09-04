<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $tokenName = '';

    public ?string $newPlainTextToken = null;

    public function createToken(): void
    {
        $this->validate([
            'tokenName' => 'required|string|max:255',
        ]);

        $token = Auth::user()->createToken($this->tokenName);

        $this->newPlainTextToken = $token->plainTextToken;
        $this->tokenName = '';
    }

    public function dismissNewToken(): void
    {
        $this->newPlainTextToken = null;
    }

    public ?int $confirmingRevokeId = null;

    public function confirmRevoke(int $tokenId): void
    {
        $this->confirmingRevokeId = $tokenId;
    }

    public function cancelRevoke(): void
    {
        $this->confirmingRevokeId = null;
    }

    public function revoke(): void
    {
        Auth::user()->tokens()->where('id', $this->confirmingRevokeId)->delete();

        $this->confirmingRevokeId = null;
        $this->newPlainTextToken = null;
    }

    public function with(): array
    {
        return [
            'tokens' => Auth::user()->tokens()->orderByDesc('created_at')->get(),
        ];
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Token API') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Buat token pribadi untuk mengakses REST API (data Pekerjaan & Dokumen) dari sistem lain. Token mewarisi hak akses akun Anda.') }}
        </p>
    </header>

    @if ($newPlainTextToken)
        <div class="mt-4 bg-amber-50 border border-amber-200 rounded-md p-4">
            <p class="text-sm text-amber-800 font-medium">Token baru dibuat - salin sekarang, tidak akan ditampilkan lagi:</p>
            <code class="mt-2 block text-xs bg-white border border-amber-200 rounded px-3 py-2 break-all select-all">{{ $newPlainTextToken }}</code>
            <button type="button" wire:click="dismissNewToken" class="mt-2 text-xs text-amber-700 hover:underline">Sudah disalin, tutup</button>
        </div>
    @endif

    <form wire:submit="createToken" class="mt-4 flex items-end gap-3">
        <div class="flex-1">
            <x-input-label for="tokenName" value="Nama token (mis. 'Integrasi E-DMS')" />
            <x-text-input wire:model="tokenName" id="tokenName" class="mt-1 block w-full" />
            <x-input-error :messages="$errors->get('tokenName')" class="mt-1" />
        </div>
        <x-primary-button>{{ __('Buat Token') }}</x-primary-button>
    </form>

    <div class="mt-6 space-y-2">
        @forelse ($tokens as $token)
            <div class="flex items-center justify-between text-sm bg-gray-50 rounded-md px-3 py-2">
                <div>
                    <span class="font-medium text-gray-800">{{ $token->name }}</span>
                    <span class="text-xs text-gray-400 ml-2">
                        dibuat {{ $token->created_at->format('d-m-Y') }}
                        @if ($token->last_used_at)
                            - terakhir dipakai {{ $token->last_used_at->diffForHumans() }}
                        @else
                            - belum pernah dipakai
                        @endif
                    </span>
                </div>
                <button type="button" wire:click="confirmRevoke({{ $token->id }})" class="text-xs text-red-600 hover:underline">
                    Cabut
                </button>
            </div>
        @empty
            <p class="text-sm text-gray-400 italic">Belum ada token API.</p>
        @endforelse
    </div>

    @if ($confirmingRevokeId)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-500/75" wire:click="cancelRevoke"></div>
            <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-md sm:mx-auto p-6">
                <h3 class="text-lg font-medium text-gray-900">Cabut token ini?</h3>
                <p class="mt-1 text-sm text-gray-500">Sistem yang memakai token ini akan berhenti bisa mengakses API.</p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="cancelRevoke" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="button" wire:click="revoke" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">
                        Ya, Cabut
                    </button>
                </div>
            </div>
        </div>
    @endif
</section>
