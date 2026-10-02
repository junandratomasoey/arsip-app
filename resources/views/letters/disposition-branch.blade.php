{{--
    Satu tingkat pohon disposisi (rekursif). $tree = koleksi disposisi
    dikelompokkan per parent_id (0 = disposisi pertama dari pimpinan).
--}}
<ul class="{{ $depth > 0 ? 'ml-5 pl-4 border-l-2 border-pu-navy-100' : '' }} space-y-3">
    @foreach ($tree[$parentKey] ?? [] as $d)
        <li wire:key="disp-{{ $d->id }}">
            <div class="rounded-md border border-gray-200 p-3 text-sm {{ $d->to_user_id === auth()->id() ? 'bg-pu-gold-50 border-pu-gold-200' : 'bg-white' }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-gray-900">
                        <span class="font-medium">{{ $d->fromUser?->name ?? '-' }}</span>
                        <span class="text-gray-400">&rarr;</span>
                        <span class="font-medium">{{ $d->toUser?->name ?? '-' }}</span>
                        @if ($d->toUser?->organizationalUnit)
                            <span class="text-xs text-gray-400">({{ $d->toUser->organizationalUnit->name }})</span>
                        @endif
                    </p>
                    <div class="flex items-center gap-2">
                        <span @class([
                            'text-[10px] font-semibold uppercase tracking-wide rounded px-1.5 py-0.5',
                            'bg-sky-100 text-sky-700' => $d->status === 'baru',
                            'bg-gray-100 text-gray-600' => $d->status === 'dibaca',
                            'bg-amber-100 text-amber-700' => $d->status === 'diproses',
                            'bg-emerald-100 text-emerald-700' => $d->status === 'selesai',
                        ])>{{ $d->statusLabel() }}</span>
                        @if ($d->isOverdue())
                            <span class="text-[10px] font-semibold uppercase tracking-wide rounded px-1.5 py-0.5 bg-red-100 text-red-700">Lewat batas</span>
                        @endif
                    </div>
                </div>
                <p class="mt-1 text-gray-700 whitespace-pre-line">{{ $d->instruction }}</p>
                <p class="mt-1 text-xs text-gray-400">
                    {{ $d->created_at->format('d-m-Y H:i') }}
                    @if ($d->due_date) · batas {{ $d->due_date->format('d-m-Y') }} @endif
                    @if ($d->read_at) · dibaca {{ $d->read_at->format('d-m-Y H:i') }} @endif
                </p>

                @if ($d->response)
                    <div class="mt-2 rounded bg-gray-50 px-3 py-2">
                        <p class="text-xs text-gray-400">Tindak lanjut · {{ $d->responded_at?->format('d-m-Y H:i') }}</p>
                        <p class="text-gray-700 whitespace-pre-line">{{ $d->response }}</p>
                    </div>
                @endif

                @if ($d->to_user_id === auth()->id() && ! $letter->trashed())
                    <button type="button" wire:click="startRespond({{ $d->id }})" class="mt-2 text-xs text-pu-navy-600 hover:underline">
                        {{ $d->response ? 'ubah tindak lanjut' : 'isi tindak lanjut' }}
                    </button>
                @endif
            </div>

            @if (isset($tree[$d->id]))
                <div class="mt-3">
                    @include('letters.disposition-branch', ['tree' => $tree, 'parentKey' => $d->id, 'depth' => $depth + 1])
                </div>
            @endif
        </li>
    @endforeach
</ul>
