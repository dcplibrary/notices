<?php

namespace Dcplibrary\Notices\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for FailedDeliveryString model.
 *
 * Transforms FailedDeliveryString model into consistent JSON format for API responses.
 */
class FailedContactResource extends JsonResource
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
        // Handle pseudo-objects from mismatched preference issues
        if (is_object($this->resource) && !($this->resource instanceof \Dcplibrary\Notices\Models\FailedDeliveryString)) {
            return [
                'id'                    => $this->id                    ?? null,
                'delivery_string'       => $this->delivery_string       ?? null,
                'delivery_option_id'    => $this->delivery_option_id    ?? null,
                'string_type'           => $this->string_type           ?? null,
                'failure_source'        => $this->failure_source        ?? null,
                'failure_details'       => $this->failure_details       ?? null,
                'failure_count'         => $this->failure_count         ?? 0,
                'affected_patron_count' => $this->affected_patron_count ?? 0,
                'first_failed_at'       => isset($this->first_failed_at) && $this->first_failed_at
                    ? (is_string($this->first_failed_at) ? $this->first_failed_at : $this->first_failed_at->toIso8601String()) : null,
                'last_failed_at' => isset($this->last_failed_at) && $this->last_failed_at
                    ? (is_string($this->last_failed_at) ? $this->last_failed_at : $this->last_failed_at->toIso8601String()) : null,
                'is_resolved' => $this->is_resolved ?? false,
                'resolved_at' => isset($this->resolved_at) && $this->resolved_at
                    ? (is_string($this->resolved_at) ? $this->resolved_at : $this->resolved_at->toIso8601String()) : null,
                'issue_type'             => $this->issue_type             ?? null,
                'issue_id'               => $this->issue_id               ?? null,
                'example_patron_barcode' => $this->example_patron_barcode ?? null,
                'example_patron_name'    => $this->example_patron_name    ?? null,
            ];
        }

        return [
            'id'                         => $this->id,
            'delivery_string'            => $this->delivery_string,
            'delivery_option_id'         => $this->delivery_option_id,
            'string_type'                => $this->string_type ?? (8 == $this->delivery_option_id || 3 == $this->delivery_option_id ? 'phone' : 'email'),
            'failure_source'             => $this->failure_source,
            'failure_details'            => $this->failure_details,
            'failure_count'              => $this->failure_count,
            'affected_patron_count'      => $this->affected_patron_count,
            'first_failed_at'            => $this->first_failed_at?->toIso8601String(),
            'last_failed_at'             => $this->last_failed_at?->toIso8601String(),
            'is_resolved'                => $this->is_resolved,
            'resolved_at'                => $this->resolved_at?->toIso8601String(),
            'resolved_by_user_id'        => $this->resolved_by_user_id,
            'resolved_by_name'           => $this->resolved_by_name,
            'resolved_patron_barcode'    => $this->resolved_patron_barcode,
            'resolved_patron_first_name' => $this->resolved_patron_first_name,
            'resolved_patron_last_name'  => $this->resolved_patron_last_name,
            'resolution_notes'           => $this->resolution_notes,
            'created_at'                 => $this->created_at?->toIso8601String(),
            'updated_at'                 => $this->updated_at?->toIso8601String(),

            // Relationships
            'delivery_option' => $this->whenLoaded('deliveryOption', function () {
                return $this->deliveryOption->delivery_option ?? null;
            }),
        ];
    }
}
