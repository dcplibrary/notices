<?php

namespace Dcplibrary\Notices\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShoutbombKeywordUsageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'keyword' => $this->keyword,
            'keyword_description' => $this->keyword_description,
            'usage_count' => $this->usage_count,
            'usage_date' => $this->usage_date?->toDateString(),
            'report_file' => $this->report_file,
            'report_period' => $this->report_period,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
