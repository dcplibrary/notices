<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Carbon\CarbonImmutable;
use Dcplibrary\Notices\Http\Requests\Api\DashboardStatsRequest;
use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controller for dashboard-oriented notices screens and APIs.
 */
class DashboardController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display the main notifications dashboard with statistics and import monitoring.
     */
    public function dashboard(Request $request)
    {
        // Default to yesterday, or most recent notification date if no data yesterday
        $yesterday = today()->subDay()->toDateString();

        $dateFrom   = $request->input('date_from');
        $dateTo     = $request->input('date_to');
        $quickRange = $request->input('quick_range');

        // If no dates provided, always default to **yesterday**.
        // This avoids surprising fallbacks to an older "most recent" date
        // (e.g., Jan 15) when imports are paused.
        if (!$dateFrom || !$dateTo) {
            $dateFrom   = $yesterday;
            $dateTo     = $yesterday;
            $quickRange = $quickRange ?? 'yesterday';
        }

        // Validate date formats
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = $yesterday;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = $yesterday;
        }

        $stats         = $this->notificationService->getDateRangeStatistics($dateFrom, $dateTo);
        $confirmations = $this->notificationService->getDateRangeConfirmations($dateFrom, $dateTo);
        $orphanedDays  = (int) ($request->input('orphaned_days') ?: 30);
        $orphanedData  = $this->notificationService->getOrphanedNotifications($orphanedDays);
        $importStatus  = $this->notificationService->getImportStatus();

        // Failed contact health – used for prominent dashboard CTAs.
        $failedContactCounts    = $this->notificationService->getFailedContactCountsByChannel();
        $failedContactsSmsVoice = $this->notificationService->getDashboardFailedContacts([3, 8], 5);
        $failedContactsEmail    = $this->notificationService->getDashboardFailedContacts([2], 5);

        // Resolve override-aware labels for SMS/Voice for use in headings
        $lookupLabelService = app(\Dcplibrary\Notices\Services\LookupLabelService::class);
        $deliveryLabels     = [
            'sms'   => $lookupLabelService->getDeliveryLabel('sms'),
            'voice' => $lookupLabelService->getDeliveryLabel('voice'),
        ];

        return view('notices::notifications.dashboard', [
            'date_from'                => $dateFrom,
            'date_to'                  => $dateTo,
            'quick_range'              => $quickRange,
            'stats'                    => $stats,
            'orphaned_data'            => $orphanedData,
            'orphaned_days'            => $orphanedDays,
            'confirmations'            => $confirmations,
            'import_status'            => $importStatus,
            'failed_contact_counts'    => $failedContactCounts,
            'failed_contacts_smsvoice' => $failedContactsSmsVoice,
            'failed_contacts_email'    => $failedContactsEmail,
            'delivery_labels'          => $deliveryLabels,
        ]);
    }

    /**
     * API endpoint: Dashboard data.
     */
    public function dashboardApi(Request $request)
    {
        // Default to yesterday, or most recent notification date if no data yesterday
        $yesterday = today()->subDay()->toDateString();

        $dateFrom   = $request->input('date_from');
        $dateTo     = $request->input('date_to');
        $quickRange = $request->input('quick_range');

        // If no dates provided, check if yesterday has data
        if (!$dateFrom || !$dateTo) {
            $yesterdayStart = CarbonImmutable::parse($yesterday)->startOfDay();
            $yesterdayEnd   = $yesterdayStart->addDay();
            $yesterdayCount = Notification::query()
                ->where('first_exported_at', '>=', $yesterdayStart)
                ->where('first_exported_at', '<', $yesterdayEnd)
                ->count()
            ;

            if (0 === $yesterdayCount) {
                // No data yesterday, find most recent notification date
                $mostRecent = Notification::latest('first_exported_at')->first();
                if ($mostRecent) {
                    $dateFrom   = $mostRecent->first_exported_at->toDateString();
                    $dateTo     = $dateFrom;
                    $quickRange = 'custom';
                } else {
                    $dateFrom   = $yesterday;
                    $dateTo     = $yesterday;
                    $quickRange = $quickRange ?? 'yesterday';
                }
            } else {
                $dateFrom   = $yesterday;
                $dateTo     = $yesterday;
                $quickRange = $quickRange ?? 'yesterday';
            }
        }

        // Validate date formats
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = $yesterday;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = $yesterday;
        }

        $stats         = $this->notificationService->getDateRangeStatistics($dateFrom, $dateTo);
        $confirmations = $this->notificationService->getDateRangeConfirmations($dateFrom, $dateTo);
        $orphanedDays  = (int) ($request->input('orphaned_days') ?: 30);
        $orphanedData  = $this->notificationService->getOrphanedNotifications($orphanedDays);
        $importStatus  = $this->notificationService->getImportStatus();

        // Failed contact health
        $failedContactCounts    = $this->notificationService->getFailedContactCountsByChannel();
        $failedContactsSmsVoice = $this->notificationService->getDashboardFailedContacts([3, 8], 5);
        $failedContactsEmail    = $this->notificationService->getDashboardFailedContacts([2], 5);

        return response()->json([
            'date_from'                 => $dateFrom,
            'date_to'                   => $dateTo,
            'quick_range'               => $quickRange,
            'orphaned_days'             => $orphanedDays,
            'statistics'                => $stats,
            'confirmations'             => $confirmations,
            'orphaned_notifications'    => $orphanedData,
            'import_status'             => $importStatus,
            'failed_contact_counts'     => $failedContactCounts,
            'failed_contacts_sms_voice' => $failedContactsSmsVoice,
            'failed_contacts_email'     => $failedContactsEmail,
        ]);
    }

    /**
     * API endpoint: Dashboard statistics only.
     */
    public function dashboardStatsApi(DashboardStatsRequest $request)
    {
        $stats = $this->notificationService->getDateRangeStatistics(
            $request->input('date_from'),
            $request->input('date_to')
        );

        return response()->json($stats);
    }

    /**
     * API endpoint: Import status.
     */
    public function importStatusApi()
    {
        $importStatus = $this->notificationService->getImportStatus();

        return response()->json($importStatus);
    }
}
