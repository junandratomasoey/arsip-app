<?php

use App\Models\AuditLog;
use App\Models\Loan;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $statusFilter = 'pending';

    public ?string $approvingId = null;
    public string $dueDate = '';

    public ?string $rejectingId = null;
    public string $rejectionReason = '';

    public ?string $confirmingReturnId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('loan.view'), 403);
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $loans = Loan::query()
            ->with(['document.work', 'requester'])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('created_at')
            ->paginate(15);

        $counts = Loan::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return ['loans' => $loans, 'counts' => $counts];
    }

    public function startApprove(string $id): void
    {
        abort_unless(auth()->user()->can('loan.approve'), 403);

        $this->resetValidation();
        $this->approvingId = $id;
        $this->dueDate = now()->addDays(14)->format('Y-m-d');
    }

    public function cancelApprove(): void
    {
        $this->approvingId = null;
    }

    public function saveApprove(): void
    {
        abort_unless(auth()->user()->can('loan.approve'), 403);

        $this->validate([
            'dueDate' => 'nullable|date',
        ]);

        $loan = Loan::where('status', Loan::STATUS_PENDING)->findOrFail($this->approvingId);
        $loan->update([
            'status' => Loan::STATUS_APPROVED,
            'due_date' => $this->dueDate !== '' ? $this->dueDate : null,
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);

        AuditLog::record('loan.approved', $loan, 'Setujui peminjaman: ' . $loan->document->title, [
            'borrower' => $loan->borrower_name,
            'due_date' => $loan->due_date?->format('Y-m-d'),
        ]);

        $this->approvingId = null;
        session()->flash('status', 'Peminjaman disetujui.');
    }

    public function startReject(string $id): void
    {
        abort_unless(auth()->user()->can('loan.reject'), 403);

        $this->resetValidation();
        $this->rejectingId = $id;
        $this->rejectionReason = '';
    }

    public function cancelReject(): void
    {
        $this->rejectingId = null;
    }

    public function saveReject(): void
    {
        abort_unless(auth()->user()->can('loan.reject'), 403);

        $this->validate([
            'rejectionReason' => 'required|string|max:1000',
        ]);

        $loan = Loan::where('status', Loan::STATUS_PENDING)->findOrFail($this->rejectingId);
        $loan->update([
            'status' => Loan::STATUS_REJECTED,
            'rejection_reason' => $this->rejectionReason,
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);

        AuditLog::record('loan.rejected', $loan, 'Tolak peminjaman: ' . $loan->document->title, [
            'borrower' => $loan->borrower_name,
            'reason' => $loan->rejection_reason,
        ]);

        $this->rejectingId = null;
        session()->flash('status', 'Peminjaman ditolak.');
    }

    public function confirmReturn(string $id): void
    {
        abort_unless(auth()->user()->can('loan.return'), 403);

        $this->confirmingReturnId = $id;
    }

    public function cancelReturn(): void
    {
        $this->confirmingReturnId = null;
    }

    public function markReturned(): void
    {
        abort_unless(auth()->user()->can('loan.return'), 403);

        $loan = Loan::where('status', Loan::STATUS_APPROVED)->findOrFail($this->confirmingReturnId);
        $loan->update([
            'status' => Loan::STATUS_RETURNED,
            'returned_at' => now(),
        ]);

        AuditLog::record('loan.returned', $loan, 'Tandai dikembalikan: ' . $loan->document->title, [
            'borrower' => $loan->borrower_name,
        ]);

        $this->confirmingReturnId = null;
        session()->flash('status', 'Dokumen ditandai sudah dikembalikan.');
    }
}; ?>

<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Peminjaman Dokumen
    </h2>
</x-slot>

<div>
    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-md px-4 py-3">
                    {{ session('status') }}
                </div>
            @endif

            <div class="flex flex-wrap gap-2">
                @foreach (['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'returned' => 'Dikembalikan', '' => 'Semua'] as $value => $label)
                    <button
                        type="button"
                        wire:click="$set('statusFilter', '{{ $value }}')"
                        @class([
                            'px-3 py-1.5 rounded-md text-xs font-semibold uppercase tracking-widest',
                            'bg-gray-800 text-white' => $statusFilter === $value,
                            'bg-white text-gray-600 border border-gray-300' => $statusFilter !== $value,
                        ])
                    >
                        {{ $label }} @if ($value !== '' && ($counts[$value] ?? 0) > 0) ({{ $counts[$value] }}) @endif
                    </button>
                @endforeach
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Dokumen</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Peminjam</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Keperluan</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase text-xs">Status</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500 uppercase text-xs">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($loans as $loan)
                            <tr>
                                <td class="px-4 py-2 text-gray-900 align-top">
                                    {{ $loan->document->title }}
                                    <p class="text-xs text-gray-400">{{ $loan->document->work->code }} - {{ $loan->document->work->name }}</p>
                                </td>
                                <td class="px-4 py-2 text-gray-500 align-top">
                                    {{ $loan->borrower_name }}
                                    @if ($loan->borrower_instansi)
                                        <p class="text-xs text-gray-400">{{ $loan->borrower_instansi }}</p>
                                    @endif
                                    <p class="text-xs text-gray-400">{{ $loan->borrower_contact }}</p>
                                </td>
                                <td class="px-4 py-2 text-gray-500 align-top max-w-xs">{{ $loan->purpose }}</td>
                                <td class="px-4 py-2 align-top whitespace-nowrap">
                                    <span @class([
                                        'text-[10px] font-semibold uppercase tracking-wide rounded px-1.5 py-0.5',
                                        'bg-amber-100 text-amber-700' => $loan->status === 'pending',
                                        'bg-emerald-100 text-emerald-700' => $loan->status === 'approved',
                                        'bg-red-100 text-red-700' => $loan->status === 'rejected',
                                        'bg-gray-200 text-gray-600' => $loan->status === 'returned',
                                    ])>
                                        {{ $loan->statusLabel() }}
                                    </span>
                                    @if ($loan->status === 'approved' && $loan->due_date)
                                        <p class="text-xs text-gray-400 mt-1">jatuh tempo: {{ $loan->due_date->format('d-m-Y') }}</p>
                                    @endif
                                    @if ($loan->status === 'rejected' && $loan->rejection_reason)
                                        <p class="text-xs text-gray-400 mt-1">{{ $loan->rejection_reason }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right space-x-3 text-xs whitespace-nowrap align-top">
                                    @if ($loan->status === 'pending')
                                        @can('loan.approve')
                                            <button type="button" wire:click="startApprove('{{ $loan->id }}')" class="text-emerald-600 hover:underline">setujui</button>
                                        @endcan
                                        @can('loan.reject')
                                            <button type="button" wire:click="startReject('{{ $loan->id }}')" class="text-red-600 hover:underline">tolak</button>
                                        @endcan
                                    @elseif ($loan->status === 'approved')
                                        @can('loan.return')
                                            <button type="button" wire:click="confirmReturn('{{ $loan->id }}')" class="text-indigo-600 hover:underline">tandai dikembalikan</button>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-400 italic">Tidak ada pengajuan peminjaman.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>
                {{ $loans->links() }}
            </div>
        </div>
    </div>

    @if ($approvingId)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-500/75" wire:click="cancelApprove"></div>
            <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-sm sm:mx-auto">
                <form wire:submit="saveApprove" class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Setujui Peminjaman</h3>
                    <div>
                        <x-input-label for="dueDate" value="Jatuh tempo pengembalian (opsional)" />
                        <x-text-input wire:model="dueDate" id="dueDate" type="date" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('dueDate')" class="mt-1" />
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" wire:click="cancelApprove" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                            Setujui
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($rejectingId)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-500/75" wire:click="cancelReject"></div>
            <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-sm sm:mx-auto">
                <form wire:submit="saveReject" class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Tolak Peminjaman</h3>
                    <div>
                        <x-input-label for="rejectionReason" value="Alasan penolakan" />
                        <textarea wire:model="rejectionReason" id="rejectionReason" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                        <x-input-error :messages="$errors->get('rejectionReason')" class="mt-1" />
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" wire:click="cancelReject" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">
                            Tolak
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($confirmingReturnId)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-500/75" wire:click="cancelReturn"></div>
            <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-md sm:mx-auto p-6">
                <h3 class="text-lg font-medium text-gray-900">Tandai dokumen sudah dikembalikan?</h3>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="cancelReturn" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="button" wire:click="markReturned" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                        Ya, Sudah Dikembalikan
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
