<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Dcplibrary\Notices\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controller for global notices search endpoints.
 */
class SearchController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Global search endpoint for dashboard search bar.
     *
     * Returns a lightweight list of results from notifications, issues,
     * and failed contacts, each with label/description/location/url.
     */
    public function globalSearch(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        if ('' === $q) {
            return response()->json(['results' => []]);
        }

        $results = [];

        // 1) Notifications
        $notificationFilters = ['search' => $q];
        $notifications       = $this->notificationService
            ->searchNotifications($notificationFilters, 'first_exported_at', 'desc')
            ->take(10)
        ;

        foreach ($notifications as $n) {
            $results[] = [
                'type'        => 'notification',
                'label'       => $n->patron_barcode,
                'description' => sprintf('%s • %s', ucfirst($n->notification_type), $n->deliveryOption->delivery_option ?? 'Method'),
                'location'    => 'Notifications',
                'url'         => route('notices.index', ['search' => $q]),
            ];
        }

        // 2) Issues
        $issueFilters = ['search' => $q];
        $issues       = $this->notificationService
            ->searchIssues(null, $issueFilters, 'notification_date', 'desc')
            ->take(10)
        ;

        foreach ($issues as $issue) {
            $results[] = [
                'type'        => 'issue',
                'label'       => $issue->title    ?? ($issue->failure_reason ?? 'Issue'),
                'description' => $issue->category ?? 'Delivery issue',
                'location'    => 'Issues',
                'url'         => route('notices.issues', ['search' => $q]),
            ];
        }

        // 3) Failed contacts (simple contains() filter on delivery_string)
        $failedStrings = $this->notificationService
            ->getFailedDeliveryStrings(false)
            ->filter(function ($f) use ($q) {
                return false !== stripos($f->delivery_string, $q);
            })
            ->take(10)
        ;

        foreach ($failedStrings as $f) {
            $results[] = [
                'type'        => 'failed_contact',
                'label'       => $f->delivery_string,
                'description' => (('phone' === $f->string_type) ? 'Phone' : 'Email') . ' • ' . ($f->failure_source ?? 'Failed contact'),
                'location'    => 'Issues → Failed Contacts',
                'url'         => route('notices.issues', ['type' => 'failed_contacts', 'search' => $f->delivery_string]),
            ];
        }

        // Limit total results
        $results = array_slice($results, 0, 25);

        return response()->json(['results' => $results]);
    }
}
