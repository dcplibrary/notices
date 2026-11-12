<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Dcplibrary\Notices\Http\Resources\FailedContactResource;
use Dcplibrary\Notices\Models\PatronDeliveryIssue;
use Dcplibrary\Notices\Services\NotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controller for failed-contact endpoints (web + API).
 */
class FailedContactsController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get failed contact details as JSON for modal display.
     *
     * Also handles mismatched preference issues (format: "mismatched_{issue_id}").
     */
    public function showFailedContact(string $id)
    {
        // Handle mismatched preference issues (format: "mismatched_{issue_id}")
        if (str_starts_with($id, 'mismatched_')) {
            $issueId = (int) str_replace('mismatched_', '', $id);
            $issue   = PatronDeliveryIssue::findOrFail($issueId);

            // Get phone number from delivery confirmation
            $deliveryConfirmation = $this->notificationService->getMismatchedPreferenceConfirmation($issue->patron_barcode);
            $phone                = $deliveryConfirmation?->delivery_string ?? \Dcplibrary\Notices\Support\IssueDisplayHelper::extractPhoneFromDetails($issue->issue_details);

            // Get delivery option
            $deliveryOptionId = $deliveryConfirmation?->delivery_option_id ?? 3;
            $deliveryOption   = \Dcplibrary\Notices\Models\DeliveryOption::find($deliveryOptionId);

            // Find patron info
            $patron = $this->notificationService->findPatronByIdOrBarcode($issue->patron_id, $issue->patron_barcode);

            $response = $this->notificationService->buildFailedContactApiResponse([
                'id'                    => $id,
                'delivery_string'       => $phone ?? 'Unknown',
                'string_type'           => 'phone',
                'delivery_option_id'    => $deliveryOptionId,
                'delivery_option_label' => $deliveryOption?->display_label ?? 'Unknown',
                'failure_source'        => 'Mismatched Preference',
                'failure_details'       => $issue->issue_details,
                'failure_count'         => 1,
                'affected_patron_count' => 1,
                'first_failed_at'       => $issue->detected_at,
                'last_failed_at'        => $issue->detected_at,
                'is_resolved'           => null !== $issue->resolved_at,
                'resolved_at'           => $issue->resolved_at,
                'resolution_notes'      => $issue->resolution_notes,
                'patron'                => $patron,
            ]);

            return response()->json($response);
        }

        // Regular FailedDeliveryString
        $failedString = $this->notificationService->getFailedDeliveryString((int) $id);
        $isPhone      = in_array($failedString->delivery_option_id, [3, 8], true);

        $response = $this->notificationService->buildFailedContactApiResponse([
            'id'                    => $failedString->id,
            'delivery_string'       => $failedString->delivery_string,
            'string_type'           => $isPhone ? 'phone' : 'email',
            'delivery_option_id'    => $failedString->delivery_option_id,
            'delivery_option_label' => $failedString->deliveryOption?->display_label,
            'failure_source'        => $failedString->failure_source,
            'failure_details'       => $failedString->failure_details,
            'failure_count'         => $failedString->failure_count,
            'affected_patron_count' => $failedString->affected_patron_count,
            'first_failed_at'       => $failedString->first_failed_at,
            'last_failed_at'        => $failedString->last_failed_at,
            'is_resolved'           => (bool) $failedString->is_resolved,
            'resolved_at'           => $failedString->resolved_at,
            'resolution_notes'      => $failedString->resolution_notes,
            'patron'                => $failedString->patron ?? null,
        ]);

        return response()->json($response);
    }

    /**
     * Resolve an aggregated failed contact (FailedDeliveryString).
     */
    public function resolveFailedContact(Request $request, string $id)
    {
        $data = $request->validate([
            'patron_barcode'    => 'nullable|string|max:50',
            'patron_first_name' => 'nullable|string|max:100',
            'patron_last_name'  => 'nullable|string|max:100',
            'resolution_type'   => 'nullable|string|in:updated_phone,changed_to_email,changed_to_mail,other',
            'resolution_notes'  => 'nullable|string|max:1000',
        ]);

        // Handle mismatched preference issues
        if (str_starts_with($id, 'mismatched_')) {
            $issueId = (int) str_replace('mismatched_', '', $id);
            $issue   = PatronDeliveryIssue::findOrFail($issueId);

            // Resolve the mismatched preference issue
            $issue->update([
                'resolved_at'      => now(),
                'resolution_notes' => $data['resolution_notes'] ?? '',
            ]);

            return redirect()
                ->back()
                ->with('success', 'Mismatched preference issue resolved successfully.')
            ;
        }

        // Regular FailedDeliveryString
        $failedString = \Dcplibrary\Notices\Models\FailedDeliveryString::findOrFail((int) $id);

        $this->notificationService->resolveFailedDeliveryString(
            $failedString,
            $data['patron_barcode']    ?? null,
            $data['patron_first_name'] ?? null,
            $data['patron_last_name']  ?? null,
            $data['resolution_type']   ?? null,
            $data['resolution_notes']  ?? ''
        );

        return redirect()
            ->back()
            ->with('success', 'Failed contact resolved successfully.')
        ;
    }

    /**
     * API endpoint: Resolve an aggregated failed contact (FailedDeliveryString)
     * or a mismatched preference issue (id format: mismatched_{issueId}).
     */
    public function resolveFailedContactApi(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'patron_barcode'    => 'nullable|string|max:50',
            'patron_first_name' => 'nullable|string|max:100',
            'patron_last_name'  => 'nullable|string|max:100',
            'resolution_type'   => 'nullable|string|in:updated_phone,changed_to_email,changed_to_mail,other',
            'resolution_notes'  => 'required|string|max:1000',
        ]);

        // Handle mismatched preference issues
        if (str_starts_with($id, 'mismatched_')) {
            $issueId = (int) str_replace('mismatched_', '', $id);
            $issue   = PatronDeliveryIssue::findOrFail($issueId);

            $updated = $this->notificationService->resolveIssue($issue, $data['resolution_notes']);
            $fresh   = $issue->fresh();

            return response()->json([
                'success'     => (bool) $updated,
                'type'        => 'mismatched_preference',
                'issue_id'    => $issue->id,
                'resolved_at' => $fresh?->resolved_at?->toIso8601String(),
            ]);
        }

        $failedString = \Dcplibrary\Notices\Models\FailedDeliveryString::findOrFail((int) $id);

        $updated = $this->notificationService->resolveFailedDeliveryString(
            $failedString,
            $data['patron_barcode']    ?? null,
            $data['patron_first_name'] ?? null,
            $data['patron_last_name']  ?? null,
            $data['resolution_type']   ?? null,
            $data['resolution_notes']
        );

        $fresh = $failedString->fresh();

        return response()->json([
            'success'                   => (bool) $updated,
            'type'                      => 'failed_delivery_string',
            'failed_delivery_string_id' => $failedString->id,
            'resolved_at'               => $fresh?->resolved_at?->toIso8601String(),
        ]);
    }

    /**
     * API endpoint: Failed contacts list.
     */
    public function failedContactsApi(Request $request)
    {
        $perPage = (int) $request->input('per_page', 50);
        $page    = (int) $request->input('page', 1);

        // resolved=true means include resolved rows in addition to active rows.
        $includeResolved = filter_var($request->input('resolved', false), FILTER_VALIDATE_BOOLEAN);
        $includeTotals   = filter_var($request->input('include_totals', false), FILTER_VALIDATE_BOOLEAN);
        $filters         = [
            'search'    => $request->input('search'),
            'date_from' => $request->input('date_from'),
            'date_to'   => $request->input('date_to'),
        ];

        // API path uses DB-level pagination and avoids heavy enrichment loops.
        $failedContacts = $this->notificationService->getFailedDeliveryStringsPaginatedForApi(
            $includeResolved,
            $filters,
            $perPage,
            $page,
            $includeTotals
        );

        $isLengthAware = $failedContacts instanceof LengthAwarePaginatorContract;

        return response()->json([
            'data' => FailedContactResource::collection($failedContacts->items()),
            'meta' => [
                'current_page'   => $failedContacts->currentPage(),
                'last_page'      => $isLengthAware ? $failedContacts->lastPage() : null,
                'per_page'       => $failedContacts->perPage(),
                'total'          => $isLengthAware ? $failedContacts->total() : null,
                'from'           => $failedContacts->firstItem(),
                'to'             => $failedContacts->lastItem(),
                'has_more_pages' => $failedContacts->hasMorePages(),
                'include_totals' => $includeTotals,
            ],
            'links' => [
                'first' => $failedContacts->url(1),
                'last'  => $isLengthAware ? $failedContacts->url($failedContacts->lastPage()) : null,
                'prev'  => $failedContacts->previousPageUrl(),
                'next'  => $failedContacts->nextPageUrl(),
            ],
        ]);
    }

    /**
     * API endpoint: Failed contact counts by channel.
     */
    public function failedContactCountsApi()
    {
        $counts = $this->notificationService->getFailedContactCountsByChannel();

        return response()->json($counts);
    }
}
