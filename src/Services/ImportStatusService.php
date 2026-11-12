<?php

namespace Dcplibrary\Notices\Services;

use Carbon\CarbonImmutable;
use Dcplibrary\Notices\Models\DeliveryConfirmation;
use Dcplibrary\Notices\Models\IngestedEmail;
use Dcplibrary\Notices\Models\PolarisHoldNotification;
use Dcplibrary\Notices\Models\PolarisOverdueNotification;
use Dcplibrary\Notices\Models\PolarisRenewalNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ImportStatusService
{
    /**
     * Get import status information for dashboard monitoring.
     *
     * Checks last import times for all expected data sources and determines
     * if imports are running on schedule based on the expected daily schedule:
     * - 5:00 AM: Notification Log & History
     * - 8:05 AM: First FTP batch (holds, renewal, overdue)
     * - 9:05 AM: Second holds batch
     * - 1:05 PM: Third holds batch
     * - 5:05 PM: Fourth holds batch
     *
     * @return array Import status with sources, failed files, and schedule adherence
     */
    public function getImportStatus(): array
    {
        return Cache::remember('notices:import_status', now()->addMinutes(2), function (): array {
            // Use the package's operational timezone for all import status math/display.
            // This keeps "hours since import" consistent regardless of server/PHP default TZ.
            $tz = config('notices.timezone', 'America/Chicago');
            // Use Illuminate\Support\Carbon so test-time travel (setTestNow/travelTo) is respected.
            $now   = \Illuminate\Support\Carbon::now($tz);
            $today = $now->toDateString();

            // Get latest import batches and total records processed per source
            $latestBatches = DB::table('notification_import_batches')
                ->select(
                    'source',
                    DB::raw('MAX(imported_at) as last_import'),
                    DB::raw('MAX(date_for) as last_date_for'),
                    DB::raw('SUM(records_count) as files_count'), // total records updated/created
                    DB::raw('MAX(identifier) as last_identifier')
                )
                ->where('imported_at', '>=', $now->copy()->subDays(2)->startOfDay())
                ->groupBy('source')
                ->get()
                ->keyBy('source')
            ;

            // Determine overall last import timestamp across all sources for header display
            $lastImportOverall = $latestBatches->max('last_import');

            // Define expected sources
            $sources = [
                'ftp_holds' => [
                    'label'          => 'FTP Holds',
                    'expected_times' => ['08:05', '09:05', '13:05', '17:05'], // 8:05 AM, 9:05 AM, 1:05 PM, 5:05 PM
                    'last_import'    => null,
                    'files_count'    => 0,
                    'status'         => 'error',
                ],
                'ftp_renew' => [
                    'label'          => 'FTP Renewal',
                    'expected_times' => ['08:05'], // 8:05 AM
                    'last_import'    => null,
                    'files_count'    => 0,
                    'status'         => 'error',
                ],
                'ftp_overdue' => [
                    'label'          => 'FTP Overdue',
                    'expected_times' => ['08:05'], // 8:05 AM
                    'last_import'    => null,
                    'files_count'    => 0,
                    'status'         => 'error',
                ],
                'notification_log' => [
                    'label'          => 'Notification Log',
                    'expected_times' => ['05:00'], // 5:00 AM export
                    'last_import'    => null,
                    'files_count'    => 0,
                    'status'         => 'error',
                ],
                'notification_history' => [
                    'label'          => 'Notification History',
                    'expected_times' => ['05:00'], // 5:00 AM export
                    'last_import'    => null,
                    'files_count'    => 0,
                    'status'         => 'error',
                ],
                'phone_notices' => [
                    'label'          => 'PhoneNotices',
                    'expected_times' => ['05:00'], // 5:00 AM export
                    'last_import'    => null,
                    'files_count'    => 0,
                    'status'         => 'error',
                ],
                'email_reports' => [
                    'label'          => 'Reports',
                    'expected_times' => ['06:00'], // 6:00 AM (failure reports arrive ~6 AM)
                    'last_import'    => null,
                    'files_count'    => 0,
                    'status'         => 'warning', // Email reports are less critical
                ],
            ];

            // Populate with actual data
            foreach ($sources as $key => &$source) {
                $batch = $latestBatches->get($key);
                if ($batch) {
                    $lastImport            = \Illuminate\Support\Carbon::parse($batch->last_import, $tz);
                    $source['last_import'] = $lastImport->format('M j, g:i A');
                    $source['date_for']    = $batch->last_date_for;

                    // Default to batch-level records and identifier (identifier will be overridden below with real filenames when possible)
                    $source['files_count'] = (int) $batch->files_count;
                    $source['identifier']  = $batch->last_identifier;

                    // Attach real source filenames where we can, matching export-files view logic
                    $displayFilename = null;
                    $dateFor         = $batch->last_date_for;

                    if ($dateFor) {
                        // Always normalize to Y-m-d so we don't get "YYYY-MM-DD 00:00:00" in filenames
                        if ($dateFor instanceof \DateTimeInterface) {
                            $dateForStr = $dateFor->format('Y-m-d');
                        } else {
                            $dateForStr = (string) $dateFor;
                        }

                        if ('ftp_holds' === $key) {
                            $dayStart        = CarbonImmutable::parse($dateForStr)->startOfDay();
                            $displayFilename = PolarisHoldNotification::query()
                                ->where('export_datetime', '>=', $dayStart)
                                ->where('export_datetime', '<', $dayStart->addDay())
                                ->orderBy('export_datetime')
                                ->value('source_filename')
                            ;
                            if (!$displayFilename) {
                                // Fallback: latest holds filename if none match this exact date
                                $displayFilename = PolarisHoldNotification::query()
                                    ->orderByDesc('export_datetime')
                                    ->value('source_filename')
                                ;
                            }
                        } elseif ('ftp_renew' === $key) {
                            $dayStart        = CarbonImmutable::parse($dateForStr)->startOfDay();
                            $displayFilename = PolarisRenewalNotification::query()
                                ->where('export_datetime', '>=', $dayStart)
                                ->where('export_datetime', '<', $dayStart->addDay())
                                ->orderBy('export_datetime')
                                ->value('source_filename')
                            ;
                            if (!$displayFilename) {
                                $displayFilename = PolarisRenewalNotification::query()
                                    ->orderByDesc('export_datetime')
                                    ->value('source_filename')
                                ;
                            }
                        } elseif ('ftp_overdue' === $key) {
                            $dayStart        = CarbonImmutable::parse($dateForStr)->startOfDay();
                            $displayFilename = PolarisOverdueNotification::query()
                                ->where('export_datetime', '>=', $dayStart)
                                ->where('export_datetime', '<', $dayStart->addDay())
                                ->orderBy('export_datetime')
                                ->value('source_filename')
                            ;
                            if (!$displayFilename) {
                                $displayFilename = PolarisOverdueNotification::query()
                                    ->orderByDesc('export_datetime')
                                    ->value('source_filename')
                                ;
                            }
                        } elseif ('notification_log' === $key) {
                            $dayStart        = CarbonImmutable::parse($dateForStr)->startOfDay();
                            $displayFilename = DeliveryConfirmation::query()
                                ->where('confirmation_source', 'notification_log')
                                ->where('notice_date', '>=', $dayStart)
                                ->where('notice_date', '<', $dayStart->addDay())
                                ->orderBy('notice_date')
                                ->value('source_filename')
                            ;
                            if (!$displayFilename) {
                                $displayFilename = DeliveryConfirmation::query()
                                    ->where('confirmation_source', 'notification_log')
                                    ->orderByDesc('notice_date')
                                    ->value('source_filename')
                                ;
                            }
                        } elseif ('phone_notices' === $key) {
                            $displayFilename = 'PhoneNotices_' . $dateForStr . '.csv';
                        } elseif ('notification_history' === $key) {
                            $dayStart = CarbonImmutable::parse($dateForStr)->startOfDay();
                            // Derive filename from export_datetime so we get the real timestamped name
                            $historyExport = \Dcplibrary\Notices\Models\PolarisNotificationHistory::query()
                                ->where('export_datetime', '>=', $dayStart)
                                ->where('export_datetime', '<', $dayStart->addDay())
                                ->orderBy('export_datetime')
                                ->value('export_datetime')
                            ;

                            if ($historyExport) {
                                $exportTs        = \Carbon\Carbon::parse($historyExport);
                                $displayFilename = 'notification_history_' . $exportTs->format('Y-m-d_H-i-s') . '.txt';
                            } else {
                                // Fallback: just use date without any time component
                                $displayFilename = 'notification_history_' . $dateForStr . '.txt';
                            }
                        }
                    }

                    if ($displayFilename) {
                        $source['identifier'] = $displayFilename;
                    }

                    // For email reports row, show latest email subject + notifications count
                    if ('email_reports' === $key) {
                        $latestEmail = IngestedEmail::query()
                            ->withCount('notifications')
                            ->orderByDesc('received_at')
                            ->first()
                        ;

                        if ($latestEmail) {
                            $source['identifier']  = $latestEmail->subject ?: $batch->last_identifier;
                            $source['files_count'] = (int) $latestEmail->notifications_count;
                            $source['email_id']    = $latestEmail->id;
                        }
                    }

                    // Determine if import is current (within last 25 hours for daily imports)
                    // Carbon v3 returns signed diffs by default; force absolute hours.
                    $hoursSinceImport = $now->diffInHours($lastImport, true);
                    if ($hoursSinceImport < 25) {
                        $source['status'] = 'success';
                    } elseif ($hoursSinceImport < 48) {
                        $source['status'] = 'warning';
                    } else {
                        $source['status'] = 'error';
                    }
                }
            }
            unset($source); // Unset reference to avoid issues

            // Check if we're behind schedule based on current time
            $isCurrent     = true;
            $currentHour   = (int) $now->format('H');
            $currentMinute = (int) $now->format('i');
            $currentTime   = $currentHour * 60 + $currentMinute; // Minutes since midnight

            // Check if FTP imports should have run today
            if ($currentTime > (8 * 60 + 30)) { // After 8:30 AM
                foreach (['ftp_holds', 'ftp_renew', 'ftp_overdue'] as $source) {
                    if ('success' !== $sources[$source]['status']) {
                        $isCurrent = false;
                    }
                }
            }

            // Get failed files (if we track them)
            $failedFiles = [];

            // Per-email Shoutbomb / summary reports for the latest imports list
            $emailReportsList = IngestedEmail::query()
                ->where('received_at', '>=', CarbonImmutable::parse($today)->startOfDay())
                ->where('received_at', '<', CarbonImmutable::parse($today)->addDay()->startOfDay())
                ->orderByDesc('received_at')
                ->orderByDesc('id')
                ->get(['id', 'subject', 'received_at'])
                ->toArray()
            ;

            return [
                'is_current'         => $isCurrent,
                'sources'            => $sources,
                'failed_files'       => $failedFiles,
                'email_reports_list' => $emailReportsList,
                // Use last actual import time if available; otherwise fall back to now
                'last_checked' => $lastImportOverall
                    ? \Carbon\Carbon::parse($lastImportOverall)->format('M j, Y g:i A')
                    : $now->format('M j, Y g:i A'),
            ];
        });
    }
}
