{{-- Modal keputusan review laporan; dipakai bersama trait App\Livewire\Concerns\ReviewsReports. --}}
@if ($reviewingVersionId)
    <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
        <div class="fixed inset-0 bg-gray-500/75" wire:click="cancelReview"></div>
        <div class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl sm:max-w-md sm:mx-auto">
            <form wire:submit="saveReview" class="p-6 space-y-4">
                <h3 class="text-lg font-medium text-gray-900">Review Laporan</h3>

                <div class="flex gap-4 text-sm">
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" wire:model.live="reviewDecision" value="disetujui" class="border-gray-300 text-pu-navy focus:ring-pu-navy-500">
                        Setujui
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" wire:model.live="reviewDecision" value="revisi" class="border-gray-300 text-pu-navy focus:ring-pu-navy-500">
                        Minta revisi
                    </label>
                </div>

                <div>
                    <x-input-label for="reviewNotes" value="{{ $reviewDecision === 'revisi' ? 'Catatan revisi *' : 'Catatan (opsional)' }}" />
                    <textarea wire:model="reviewNotes" id="reviewNotes" rows="5" placeholder="{{ $reviewDecision === 'revisi' ? 'mis. Bab 3 perhitungan debit banjir rencana belum memakai data 10 tahun terakhir...' : '' }}" class="mt-1 block w-full border-gray-300 focus:border-pu-navy-500 focus:ring-pu-navy-500 rounded-md shadow-sm text-sm"></textarea>
                    <x-input-error :messages="$errors->get('reviewNotes')" class="mt-1" />
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" wire:click="cancelReview" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">Batal</button>
                    <button type="submit" @class([
                        'inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest',
                        'bg-emerald-600 hover:bg-emerald-500' => $reviewDecision === 'disetujui',
                        'bg-amber-600 hover:bg-amber-500' => $reviewDecision === 'revisi',
                    ])>{{ $reviewDecision === 'revisi' ? 'Kirim Catatan Revisi' : 'Setujui Laporan' }}</button>
                </div>
            </form>
        </div>
    </div>
@endif
