<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Work */
class WorkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'package_name' => $this->package_name,
            'work_type' => $this->whenLoaded('workType', fn () => $this->workType?->name),
            'fiscal_year' => $this->fiscal_year,
            'funding_source' => $this->funding_source,
            'province' => $this->province,
            'regency' => $this->regency,
            'district' => $this->district,
            'village' => $this->village,
            'contract_number' => $this->contract_number,
            'contract_date' => $this->contract_date?->format('Y-m-d'),
            'contract_value' => $this->contract_value,
            'spmk_number' => $this->spmk_number,
            'spmk_date' => $this->spmk_date?->format('Y-m-d'),
            'bast_number' => $this->bast_number,
            'bast_date' => $this->bast_date?->format('Y-m-d'),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'provider_name' => $this->provider_name,
            'satker' => $this->whenLoaded('satkerUnit', fn () => $this->satkerUnit?->name),
            'ppk' => $this->whenLoaded('ppkUnit', fn () => $this->ppkUnit?->name),
            'status' => $this->whenLoaded('workStatus', fn () => $this->workStatus?->name),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
