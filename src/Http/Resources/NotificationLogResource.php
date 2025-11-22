<?php

namespace Dcplibrary\Notices\Http\Resources;

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
            'patron' => [
                'name' => $this->patron_name,
                'first_name' => $this->patron_first_name,
                'last_name' => $this->patron_last_name,
                'email' => $this->patron_email,
                'phone' => $this->patron_phone,
            ],
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
            'items_summary' => [
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
            'items_detail' => $this->getItemsDetail($request),
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

    /**
     * Get detailed item information.
     * Returns full item details from imported Shoutbomb data or Polaris if available.
     * 
     * @param Request $request
     * @return array
     */
    protected function getItemsDetail(Request $request): array
    {
        // If not requested (to optimize payload), return empty
        if (!$request->boolean('include_items')) {
            return [];
        }

        return $this->items->map(function ($item) {
            return [
                'title' => $item->title ?? $item->Title ?? 'Unknown',
                'item_barcode' => $item->Barcode ?? $item->item_barcode ?? null,
                'item_record_id' => $item->ItemRecordID ?? $item->staff_link ?? null,
                'call_number' => $item->CallNumber ?? null,
            ];
        })->toArray();
    }
}
