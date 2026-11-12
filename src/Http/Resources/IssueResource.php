<?php

namespace Dcplibrary\Notices\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for PatronDeliveryIssue model.
 *
 * Transforms PatronDeliveryIssue model into consistent JSON format for API responses.
 */
class IssueResource extends JsonResource
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
        $includeDetails = filter_var($request->input('include_details', false), FILTER_VALIDATE_BOOLEAN);

        return [
            'id'                        => $this->id,
            'issue_type'                => $this->issue_type,
            'patron_id'                 => $this->patron_id,
            'patron_barcode'            => $this->patron_barcode,
            'failed_delivery_string_id' => $this->failed_delivery_string_id,
            'notification_id'           => $this->notification_id,
            'detected_at'               => $this->detected_at?->toIso8601String(),
            'resolved_at'               => $this->resolved_at?->toIso8601String(),
            'resolved_by_user_id'       => $this->resolved_by_user_id,
            'resolved_by_name'          => $this->resolved_by_name,
            'created_at'                => $this->created_at?->toIso8601String(),
            'updated_at'                => $this->updated_at?->toIso8601String(),
            'issue_details'             => $this->when($includeDetails, fn () => $this->issue_details),
            'resolution_notes'          => $this->when($includeDetails, fn () => $this->resolution_notes),

            // Relationships
            'notification' => $this->when(
                $includeDetails && $this->relationLoaded('notification'),
                fn () => new NotificationResource($this->notification)
            ),
            'failed_delivery_string' => $this->when(
                $includeDetails && $this->relationLoaded('failedDeliveryString'),
                fn () => new FailedContactResource($this->failedDeliveryString)
            ),
        ];
    }
}
