<?php

namespace Dcplibrary\Notifications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'polaris_log_id' => $this->polaris_log_id,
            'patron_id' => $this->patron_id,
            'patron_barcode' => $this->patron_barcode,
            'notification_date' => $this->notification_date?->toIso8601String(),
            'notification_type' => [
                'id' => $this->notification_type_id,
                'name' => $this->notification_type_name,
            ],
            'delivery_method' => [
                'id' => $this->delivery_option_id,
                'name' => $this->delivery_method_name,
            ],
            'status' => [
                'id' => $this->notification_status_id,
                'name' => $this->notification_status_name,
            ],
            'delivery_string' => $this->delivery_string,
            'items' => [
                'holds' => $this->holds_count,
                'overdues' => $this->overdues_count,
                'overdues_2nd' => $this->overdues_2nd_count,
                'overdues_3rd' => $this->overdues_3rd_count,
                'cancels' => $this->cancels_count,
                'recalls' => $this->recalls_count,
                'routings' => $this->routings_count,
                'bills' => $this->bills_count,
                'manual_bills' => $this->manual_bill_count,
                'total' => $this->total_items,
            ],
            'reporting_org_id' => $this->reporting_org_id,
            'language_id' => $this->language_id,
            'carrier_name' => $this->carrier_name,
            'details' => $this->details,
            'reported' => $this->reported,
            'imported_at' => $this->imported_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
