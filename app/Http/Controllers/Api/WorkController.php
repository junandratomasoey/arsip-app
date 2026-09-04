<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\WorkResource;
use App\Models\Work;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * REST API baca-saja untuk data Pekerjaan, dipakai integrasi sistem lain
 * (Bab IV.14 dokumen perancangan). Otentikasi lewat token Sanctum milik
 * pengguna yang sama seperti login web - jadi hak akses per dokumen tetap
 * mengikuti permission Spatie akun tersebut, bukan aturan terpisah.
 */
class WorkController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $works = Work::query()
            ->with(['workType', 'satkerUnit', 'ppkUnit', 'workStatus'])
            ->when($request->string('search')->toString(), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%");
                });
            })
            ->when($request->integer('satker_id'), fn ($q, $id) => $q->where('satker_unit_id', $id))
            ->when($request->integer('work_status_id'), fn ($q, $id) => $q->where('work_status_id', $id))
            ->when($request->integer('fiscal_year'), fn ($q, $year) => $q->where('fiscal_year', $year))
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return WorkResource::collection($works);
    }

    public function show(Work $work): WorkResource
    {
        $work->load(['workType', 'satkerUnit', 'ppkUnit', 'workStatus']);

        return new WorkResource($work);
    }

    public function documents(Request $request, Work $work): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('document.view'), 403);

        $documents = $work->documents()
            ->with(['phase', 'files.currentVersion', 'physicalLocation'])
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return DocumentResource::collection($documents);
    }
}
