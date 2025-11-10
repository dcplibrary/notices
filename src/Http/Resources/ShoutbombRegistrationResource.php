<?php

namespace Dcplibrary\Notices\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShoutbombRegistrationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'snapshot_date' => $this->snapshot_date?->toDateString(),
            'subscribers' => [
                'text' => [
                    'count' => $this->total_text_subscribers,
                    'percentage' => (float) $this->text_percentage,
                ],
                'voice' => [
                    'count' => $this->total_voice_subscribers,
                    'percentage' => (float) $this->voice_percentage,
                ],
                'total' => $this->total_subscribers,
            ],
            'growth' => [
                'new_registrations' => $this->new_registrations,
                'cancellations' => $this->cancellations,
                'net_change' => $this->net_change,
            ],
            'report_file' => $this->report_file,
            'report_type' => $this->report_type,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
