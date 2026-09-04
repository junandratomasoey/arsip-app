@props(['nodes' => []])

<ul class="space-y-1">
    @foreach ($nodes as $node)
        <li>
            <div class="group flex items-center gap-2 py-1 rounded px-2 hover:bg-gray-50">
                <span class="text-[10px] font-semibold uppercase tracking-wide rounded px-1.5 py-0.5 {{ $node['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-500' }}">
                    {{ $node['type'] }}
                </span>

                <span class="{{ $node['is_active'] ? 'text-gray-900' : 'text-gray-400 line-through' }}">
                    {{ $node['name'] }}
                </span>

                @if ($node['code'])
                    <span class="text-xs text-gray-400">({{ $node['code'] }})</span>
                @endif

                <span class="ms-auto hidden group-hover:flex items-center gap-3 text-xs">
                    <button type="button" wire:click="createNew({{ $node['id'] }})" class="text-pu-navy-600 hover:underline">
                        + sub-unit
                    </button>
                    <button type="button" wire:click="edit({{ $node['id'] }})" class="text-pu-navy-600 hover:underline">
                        ubah
                    </button>
                    <button type="button" wire:click="toggleActive({{ $node['id'] }})" class="text-amber-600 hover:underline">
                        {{ $node['is_active'] ? 'nonaktifkan' : 'aktifkan' }}
                    </button>
                    <button type="button" wire:click="confirmDelete({{ $node['id'] }})" class="text-red-600 hover:underline">
                        hapus
                    </button>
                </span>
            </div>

            @if (count($node['children']))
                <div class="ms-5 ps-3 border-l border-gray-200">
                    <x-organizational-unit-tree :nodes="$node['children']" />
                </div>
            @endif
        </li>
    @endforeach

    @if (empty($nodes))
        <li class="text-sm text-gray-400 italic px-2 py-1">Belum ada unit.</li>
    @endif
</ul>
