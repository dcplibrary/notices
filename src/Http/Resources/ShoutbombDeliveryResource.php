<?php

namespace Dcplibrary\Notices\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShoutbombDeliveryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patron_barcode' => $this->patron_barcode,
            'phone_number' => $this->phone_number,
            'delivery_type' => $this->delivery_type,
            'sent_date' => $this->sent_date?->toDateString(),
            'delivered_date' => $this->delivered_date?->toIso8601String(),
            'status' => $this->status,
            'failure_reason' => $this->failure_reason,
            'notification_type' => $this->notification_type,
            'message_id' => $this->message_id,
            'carrier' => $this->carrier,
            'report_file' => $this->report_file,
            'report_type' => $this->report_type,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
