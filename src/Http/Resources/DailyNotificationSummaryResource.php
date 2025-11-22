<?php

namespace Dcplibrary\Notices\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyNotificationSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'summary_date' => $this->summary_date?->toDateString(),
            'notification_type' => [
                'id' => $this->notification_type_id,
                'name' => $this->notification_type_name,
            ],
            'delivery_method' => [
                'id' => $this->delivery_option_id,
                'name' => $this->delivery_method_name,
            ],
            'totals' => [
                'sent' => $this->total_sent,
                'success' => $this->total_success,
                'failed' => $this->total_failed,
                'pending' => $this->total_pending,
            ],
            'items' => [
                'holds' => $this->total_holds,
                'overdues' => $this->total_overdues,
                'overdues_2nd' => $this->total_overdues_2nd,
                'overdues_3rd' => $this->total_overdues_3rd,
                'cancels' => $this->total_cancels,
                'recalls' => $this->total_recalls,
                'bills' => $this->total_bills,
            ],
            'unique_patrons' => $this->unique_patrons,
            'rates' => [
                'success_rate' => (float) $this->success_rate,
                'failure_rate' => (float) $this->failure_rate,
            ],
            'aggregated_at' => $this->aggregated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
