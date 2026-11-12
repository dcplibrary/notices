<?php

namespace Dcplibrary\Notices\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for Notification model.
 *
 * Transforms Notification model into consistent JSON format for API responses.
 */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'                      => $this->id,
            'patron_id'               => $this->patron_id,
            'patron_barcode'          => $this->patron_barcode,
            'notification_type'       => $this->notification_type,
            'notification_type_id'    => $this->notification_type_id,
            'delivery_option_id'      => $this->delivery_option_id,
            'delivery_option'         => $this->deliveryOption?->delivery_option ?? null,
            'status'                  => $this->status,
            'schedule_step'           => $this->schedule_step,
            'is_combined'             => $this->is_combined,
            'failure_reason'          => $this->failure_reason,
            'generated_at'            => $this->generated_at?->toIso8601String(),
            'first_exported_at'       => $this->first_exported_at?->toIso8601String(),
            'last_exported_at'        => $this->last_exported_at?->toIso8601String(),
            'expected_delivery_at'    => $this->expected_delivery_at?->toIso8601String(),
            'delivered_at'            => $this->delivered_at?->toIso8601String(),
            'failed_at'               => $this->failed_at?->toIso8601String(),
            'notice_date'             => $this->notice_date?->toIso8601String(),
            'export_filename'         => $this->export_filename,
            'import_batch_id'         => $this->import_batch_id,
            'import_batch_identifier' => $this->importBatch?->identifier,
            'ingested_email_id'       => $this->ingested_email_id,
            'created_at'              => $this->created_at?->toIso8601String(),
            'updated_at'              => $this->updated_at?->toIso8601String(),

            // Relationships (when loaded)
            'delivery_option_label' => $this->whenLoaded('deliveryOption', function () {
                return $this->deliveryOption->delivery_option ?? null;
            }),
            'active_issues_count' => $this->when(isset($this->active_issues_count), function () {
                return $this->active_issues_count ?? 0;
            }),
        ];
    }
}
