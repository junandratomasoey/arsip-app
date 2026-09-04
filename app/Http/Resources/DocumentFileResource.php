<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DocumentFile */
class DocumentFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $version = $this->currentVersion;

        return [
            'id' => $this->id,
            'label' => $this->label,
            'current_version' => $version ? [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'original_filename' => $version->original_filename,
                'mime_type' => $version->mime_type,
                'size_bytes' => $version->size_bytes,
                'checksum_sha256' => $version->checksum_sha256,
                'uploaded_at' => $version->created_at?->toIso8601String(),
                'download_url' => route('api.v1.documents.files.download', [
                    'document' => $this->document_id,
                    'version' => $version->id,
                ]),
            ] : null,
        ];
    }
}
