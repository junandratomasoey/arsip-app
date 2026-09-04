<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentFileVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function show(Document $document): DocumentResource
    {
        $document->load(['work', 'phase', 'files.currentVersion', 'physicalLocation']);

        return new DocumentResource($document);
    }

    public function downloadFile(Request $request, Document $document, DocumentFileVersion $version)
    {
        abort_unless($request->user()->can('document.download'), 403);
        abort_unless($version->documentFile->document_id === $document->id, 404);

        AuditLog::record('document.downloaded', $document, 'Unduh berkas via API: ' . $version->original_filename, [
            'file_label' => $version->documentFile->label,
            'version' => $version->version_number,
            'via' => 'api',
        ]);

        return Storage::disk($version->disk)->download($version->path, $version->original_filename);
    }
}
