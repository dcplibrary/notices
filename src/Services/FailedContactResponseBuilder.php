<?php

namespace Dcplibrary\Notices\Services;

class FailedContactResponseBuilder
{
    /**
     * Build failed contact JSON response structure for API.
     *
     * Constructs a standardized JSON response array for failed contact details
     * used by the Failed Contact edit/resolve modal. Handles both regular
     * FailedDeliveryString records and mismatched preference issues.
     *
     * @param array $data Array containing:
     *                    - id (string|int): Contact ID
     *                    - delivery_string (string): Phone or email
     *                    - string_type (string): 'phone' or 'email'
     *                    - delivery_option_id (int): Delivery option ID
     *                    - delivery_option_label (string): Display label
     *                    - failure_source (string): Source of failure
     *                    - failure_details (string): Details text
     *                    - failure_count (int): Number of failures
     *                    - affected_patron_count (int): Number of affected patrons
     *                    - first_failed_at (Carbon|null): First failure date
     *                    - last_failed_at (Carbon|null): Last failure date
     *                    - is_resolved (bool): Whether resolved
     *                    - resolved_at (Carbon|null): Resolution date
     *                    - resolution_notes (string|null): Resolution notes
     *                    - patron (object|null): Patron object with patron_id, patron_barcode, patron_first_name, patron_last_name
     *
     * @return array JSON response array structure
     */
    public function buildFailedContactApiResponse(array $data): array
    {
        $lookupLabelService = app(LookupLabelService::class);

        return [
            'id'                    => $data['id'],
            'delivery_string'       => $data['delivery_string'] ?? 'Unknown',
            'string_type'           => $data['string_type'],
            'delivery_option_id'    => $data['delivery_option_id'],
            'delivery_option_label' => $data['delivery_option_label'] ?? 'Unknown',
            'failure_source'        => $data['failure_source'],
            'failure_details'       => $data['failure_details']       ?? null,
            'failure_count'         => $data['failure_count']         ?? 0,
            'affected_patron_count' => $data['affected_patron_count'] ?? 0,
            'first_failed_at'       => \Dcplibrary\Notices\Support\DateFormatter::formatDate($data['first_failed_at'] ?? null),
            'last_failed_at'        => \Dcplibrary\Notices\Support\DateFormatter::formatDate($data['last_failed_at'] ?? null),
            'is_resolved'           => $data['is_resolved'] ?? false,
            'resolved_at'           => \Dcplibrary\Notices\Support\DateFormatter::formatDateTime($data['resolved_at'] ?? null),
            'resolution_notes'      => $data['resolution_notes'] ?? null,
            'patron'                => $data['patron'] ? [
                'patron_id'         => $data['patron']->patron_id         ?? null,
                'patron_barcode'    => $data['patron']->patron_barcode    ?? null,
                'patron_first_name' => $data['patron']->patron_first_name ?? null,
                'patron_last_name'  => $data['patron']->patron_last_name  ?? null,
            ] : null,
            'delivery_labels' => [
                'email' => $lookupLabelService->getDeliveryLabel(2),
                'mail'  => $lookupLabelService->getDeliveryLabel(1),
            ],
        ];
    }
}
