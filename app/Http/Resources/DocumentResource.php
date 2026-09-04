<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Document */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_id' => $this->work_id,
            'work_code' => $this->whenLoaded('work', fn () => $this->work?->code),
            'phase' => $this->whenLoaded('phase', fn () => $this->phase?->name),
            'title' => $this->title,
            'description' => $this->description,
            'visibility' => $this->visibility,
            'status' => $this->status,
            'physical_location' => $this->whenLoaded('physicalLocation', fn () => $this->physicalLocation?->breadcrumbLabel()),
            'files' => DocumentFileResource::collection($this->whenLoaded('files')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
