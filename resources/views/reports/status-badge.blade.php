@if ($status)
    <span @class([
        'text-[10px] font-semibold uppercase tracking-wide rounded px-1.5 py-0.5',
        'bg-gray-100 text-gray-600' => $status === 'draf',
        'bg-sky-100 text-sky-700' => $status === 'menunggu_review',
        'bg-amber-100 text-amber-700' => $status === 'revisi',
        'bg-emerald-100 text-emerald-700' => $status === 'disetujui',
    ])>{{ \App\Models\DocumentFileVersion::REVIEW_LABELS[$status] ?? $status }}</span>
@endif
