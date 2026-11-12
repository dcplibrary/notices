<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Carbon\CarbonImmutable;
use Dcplibrary\Notices\Models\ActivityFile;
use Dcplibrary\Notices\Models\DeliveryConfirmation;
use Dcplibrary\Notices\Models\EmailReportType;
use Dcplibrary\Notices\Models\IngestedEmail;
use Dcplibrary\Notices\Models\NotificationImportBatch;
use Dcplibrary\Notices\Models\PolarisHoldNotification;
use Dcplibrary\Notices\Models\PolarisOverdueNotification;
use Dcplibrary\Notices\Models\PolarisRenewalNotification;
use Dcplibrary\Notices\Support\Sortable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controller for raw import-data viewer screens.
 *
 * Exposes read-only views of the intermediate import tables — polaris hold/renewal/
 * overdue exports, notification import batches, ingested emails, delivery
 * confirmations, and activity files — to support debugging and auditing of the
 * import pipeline.
 */
class DataController extends Controller
{
    use Sortable;

    /**
     * Unified data search across report emails and export-related notifications.
     *
     * Accepts a free-text search term (barcode, phone number, email address, etc.)
     * and returns matching report emails and export batches on a single page so
     * staff can pivot quickly between sources.
     *
     * Query semantics:
     * - Report Emails: subject, sender_email, body_preview, body_full, parsed_data
     * - Export Files: NotificationImportBatch joined via notifications where
     *   patron_barcode or raw_payload contain the search term (plus a light
     *   fallback on identifier/source).
     */
    public function unifiedSearch(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $sortEmails  = $request->input('sort_emails', 'received_at');
        $dirEmails   = 'asc' === strtolower($request->input('dir_emails', 'desc')) ? 'asc' : 'desc';
        $sortImports = $request->input('sort_imports', 'imported_at');
        $dirImports  = 'asc' === strtolower($request->input('dir_imports', 'desc')) ? 'asc' : 'desc';

        $today   = now()->toDateString();
        $emails  = collect();
        $imports = collect();

        // Build base queries used for both Overview (no search) and filtered search
        $emailQuery = IngestedEmail::query()
            ->with('emailReportType')
            ->withCount('notifications')
        ;

        $importQuery = NotificationImportBatch::query()
            ->withCount('notifications')
        ;
        $dateFromStart      = $this->parseDateBoundary($request->input('date_from'));
        $dateToEndExclusive = $this->parseDateBoundary($request->input('date_to'), true);

        // Date range for emails
        if ($request->filled('date_from') || $request->filled('date_to')) {
            if (null !== $dateFromStart) {
                $emailQuery->where('received_at', '>=', $dateFromStart);
            }
            if (null !== $dateToEndExclusive) {
                $emailQuery->where('received_at', '<', $dateToEndExclusive);
            }
        } elseif ('' === $search) {
            // Overview default: today's emails when no date filter and no search term
            $todayStart = CarbonImmutable::parse($today)->startOfDay();
            $emailQuery->where('received_at', '>=', $todayStart)
                ->where('received_at', '<', $todayStart->addDay())
            ;
        }

        // Date range for imports
        if ($request->filled('date_from') || $request->filled('date_to')) {
            if (null !== $dateFromStart) {
                $importQuery->where('imported_at', '>=', $dateFromStart);
            }
            if (null !== $dateToEndExclusive) {
                $importQuery->where('imported_at', '<', $dateToEndExclusive);
            }
        } elseif ('' === $search) {
            // Overview default: today's batches when no date filter and no search term
            $todayStart = CarbonImmutable::parse($today)->startOfDay();
            $importQuery->where('imported_at', '>=', $todayStart)
                ->where('imported_at', '<', $todayStart->addDay())
            ;
        }

        // Apply search filters when a term is provided
        if ('' !== $search) {
            $like = "%{$search}%";

            $emailQuery->where(function ($q) use ($like) {
                $q->where('subject', 'like', $like)
                    ->orWhere('sender_email', 'like', $like)
                    ->orWhere('body_preview', 'like', $like)
                    ->orWhere('body_full', 'like', $like)
                    ->orWhere('parsed_data', 'like', $like)
                ;
            });

            $importQuery->where(function ($q) use ($like, $search) {
                // Match by identifier/source OR by linked notifications
                $q->where('identifier', 'like', $like)
                    ->orWhere('source', 'like', $like)
                    ->orWhereHas('notifications', function ($nq) use ($search) {
                        // OPTIMIZATION: Try exact matches on indexed fields (fast path)
                        $nq->where('patron_barcode', $search)
                            ->orWhere('indexed_patron_phone', $search)
                            ->orWhere('indexed_patron_email', $search)
                        ;
                    })
                    // Fallback to LIKE searches only if indexed fields didn't match
                    ->orWhereHas('notifications', function ($nq) use ($like) {
                        $nq->where(function ($likeQuery) use ($like) {
                            $likeQuery->where('patron_barcode', 'like', $like)
                                ->orWhere('raw_payload', 'like', $like)
                            ;
                        });
                    })
                ;
            });
        }

        // Sorting for emails section (server-side subset)
        $allowedEmailSorts = [
            'received_at' => 'received_at',
            'subject'     => 'subject',
        ];
        $emailSortColumn = $allowedEmailSorts[$sortEmails] ?? 'received_at';

        $emails = $emailQuery
            ->orderBy($emailSortColumn, $dirEmails)
            ->orderBy('id', $dirEmails)
            ->limit(50)
            ->get()
        ;

        // Sorting for imports section
        $allowedImportSorts = [
            'imported_at' => 'imported_at',
            'source'      => 'source',
            'date_for'    => 'date_for',
            'records'     => 'notifications_count',
        ];
        $importSortColumn = $allowedImportSorts[$sortImports] ?? 'imported_at';

        $imports = $importQuery
            ->orderBy($importSortColumn, $dirImports)
            ->orderBy('id', $dirImports)
            ->limit(50)
            ->get()
        ;

        // Attach display_filename just like exportFiles(), so the unified
        // search view can reuse the same presentation.
        $imports->transform(function (NotificationImportBatch $import) {
            $filename = null;

            if ($import->date_for) {
                $dateFor = $import->date_for instanceof \DateTimeInterface
                    ? $import->date_for->format('Y-m-d')
                    : (string) $import->date_for;

                if ('ftp_holds' === $import->source) {
                    $dayStart = CarbonImmutable::parse($dateFor)->startOfDay();
                    $filename = PolarisHoldNotification::query()
                        ->where('export_datetime', '>=', $dayStart)
                        ->where('export_datetime', '<', $dayStart->addDay())
                        ->orderBy('export_datetime')
                        ->value('source_filename')
                    ;
                } elseif ('ftp_renew' === $import->source) {
                    $dayStart = CarbonImmutable::parse($dateFor)->startOfDay();
                    $filename = PolarisRenewalNotification::query()
                        ->where('export_datetime', '>=', $dayStart)
                        ->where('export_datetime', '<', $dayStart->addDay())
                        ->orderBy('export_datetime')
                        ->value('source_filename')
                    ;
                } elseif ('ftp_overdue' === $import->source) {
                    $dayStart = CarbonImmutable::parse($dateFor)->startOfDay();
                    $filename = PolarisOverdueNotification::query()
                        ->where('export_datetime', '>=', $dayStart)
                        ->where('export_datetime', '<', $dayStart->addDay())
                        ->orderBy('export_datetime')
                        ->value('source_filename')
                    ;
                } elseif ('notification_log' === $import->source) {
                    $dayStart = CarbonImmutable::parse($dateFor)->startOfDay();
                    $filename = DeliveryConfirmation::query()
                        ->where('confirmation_source', 'notification_log')
                        ->where('notice_date', '>=', $dayStart)
                        ->where('notice_date', '<', $dayStart->addDay())
                        ->orderBy('notice_date')
                        ->value('source_filename')
                    ;
                } elseif ('phone_notices' === $import->source) {
                    $filename = 'PhoneNotices_' . $dateFor . '.csv';
                } elseif ('notification_history' === $import->source) {
                    $filename = 'notification_history_' . $dateFor . '.txt';
                }
            }

            $import->display_filename = $filename ?: $import->identifier;

            return $import;
        });

        return view('notices::data.search', [
            'search'       => $search,
            'emails'       => $emails,
            'imports'      => $imports,
            'sort_emails'  => $sortEmails,
            'dir_emails'   => $dirEmails,
            'sort_imports' => $sortImports,
            'dir_imports'  => $dirImports,
        ]);
    }

    /**
     * Display list of ingested report emails from LX Starter.
     *
     * Shows email reports received from LX Starter including:
     * - Polaris Summary Reports (daily delivery confirmations)
     * - Shoutbomb Voice Failure Reports
     * - Shoutbomb Invalid Phone Reports
     * - Shoutbomb Voice System Error Reports
     *
     * Supports filtering by:
     * - Search query (subject, sender, body preview)
     * - Report type (using EmailReportType lookup)
     * - Date range (received_at)
     *
     * @return \Illuminate\View\View
     */
    public function reportEmails(Request $request)
    {
        $allowedSorts = [
            'received_at' => 'received_at',
            'subject'     => 'subject',
        ];

        [$sortColumn, $sortDir, $sortBy] = $this->resolveSort(
            $request,
            $allowedSorts,
            'received_at',
            'desc',
        );

        $query = IngestedEmail::query()
            ->with('emailReportType')
            ->withCount('notifications')
        ;

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');

            // NOTE: We intentionally search across subject, sender, body_preview,
            // body_full, and parsed_data so that barcodes, phone numbers, and
            // other identifiers anywhere in the email are discoverable.
            $query->where(function ($q) use ($search) {
                $like = "%{$search}%";

                $q->where('subject', 'like', $like)
                    ->orWhere('sender_email', 'like', $like)
                    ->orWhere('body_preview', 'like', $like)
                    ->orWhere('body_full', 'like', $like)
                    ->orWhere('parsed_data', 'like', $like)
                ;
            });
        }

        // Report type filter
        if ($request->filled('report_type')) {
            $query->where('email_report_type_id', $request->input('report_type'));
        }

        // Date range filter
        $dateFromStart      = $this->parseDateBoundary($request->input('date_from'));
        $dateToEndExclusive = $this->parseDateBoundary($request->input('date_to'), true);
        if (null !== $dateFromStart) {
            $query->where('received_at', '>=', $dateFromStart);
        }
        if (null !== $dateToEndExclusive) {
            $query->where('received_at', '<', $dateToEndExclusive);
        }

        // Sorting (server-side, across full result set)
        $query->orderBy($sortColumn, $sortDir)
            ->orderBy('id', $sortDir)
        ;

        $emails      = $query->paginate(50)->appends($request->query());
        $reportTypes = EmailReportType::enabled();

        return view('notices::data.report-emails', [
            'emails'      => $emails,
            'reportTypes' => $reportTypes,
            'sort'        => $sortBy,
            'dir'         => $sortDir,
        ]);
    }

    /**
     * Display single report email detail.
     *
     * Shows complete email information including:
     * - Email metadata (subject, sender, received date)
     * - Parsed data structure (varies by report type)
     * - Linked notifications (if any)
     * - Affected patrons count
     *
     * @param int $id IngestedEmail ID
     *
     * @return \Illuminate\View\View
     */
    public function reportEmailDetail($id)
    {
        $email = IngestedEmail::with(['notifications' => function ($query) {
            $query->with(['deliveryOption', 'activeIssues'])->latest();
        }])->findOrFail($id);

        return view('notices::data.email-detail', compact('email'));
    }

    /**
     * Display list of Polaris FTP export file imports.
     *
     * Shows import batches for files exported from Polaris via FTP including:
     * - FTP Holds (ftp_holds)
     * - FTP Renewal (ftp_renew)
     * - FTP Overdue (ftp_overdue)
     * - Email Reports (email_report)
     *
     * Each import batch represents one file processed by the FTP import pipeline.
     *
     * Supports filtering by:
     * - Search query (identifier/filename, source)
     * - Import source (ftp_holds, ftp_renew, ftp_overdue, email_report, etc.)
     * - Date range (imported_at or date_for)
     * - Status (if available)
     *
     * @return \Illuminate\View\View
     */
    public function exportFiles(Request $request)
    {
        $allowedSorts = [
            'imported_at' => 'imported_at',
            'source'      => 'source',
            'date_for'    => 'date_for',
            'records'     => 'notifications_count',
        ];

        [$sortColumn, $sortDir, $sortBy] = $this->resolveSort(
            $request,
            $allowedSorts,
            'imported_at',
            'desc',
        );

        $query = NotificationImportBatch::query()
            ->withCount('notifications')
        ;

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('identifier', 'like', "%{$search}%")
                    ->orWhere('source', 'like', "%{$search}%")
                ;
            });
        }

        // Source filter
        if ($request->filled('source')) {
            $query->where('source', $request->input('source'));
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Date range filter
        $dateFromStart      = $this->parseDateBoundary($request->input('date_from'));
        $dateToEndExclusive = $this->parseDateBoundary($request->input('date_to'), true);
        if (null !== $dateFromStart) {
            $query->where('imported_at', '>=', $dateFromStart);
        }
        if (null !== $dateToEndExclusive) {
            $query->where('imported_at', '<', $dateToEndExclusive);
        }

        // Sorting (server-side, across full result set)
        $query->orderBy($sortColumn, $sortDir)
            ->orderBy('id', $sortDir)
        ;

        $imports = $query->paginate(50)->appends($request->query());

        // Attach a representative full filename (with time) for holds/renew/overdue/notification_log/phone_notices/notification_history
        $imports->getCollection()->transform(function (NotificationImportBatch $import) {
            $filename = null;

            if ($import->date_for) {
                $dateFor = $import->date_for instanceof \DateTimeInterface
                    ? $import->date_for->format('Y-m-d')
                    : (string) $import->date_for;

                if ('ftp_holds' === $import->source) {
                    $dayStart = CarbonImmutable::parse($dateFor)->startOfDay();
                    $filename = PolarisHoldNotification::query()
                        ->where('export_datetime', '>=', $dayStart)
                        ->where('export_datetime', '<', $dayStart->addDay())
                        ->orderBy('export_datetime')
                        ->value('source_filename')
                    ;
                } elseif ('ftp_renew' === $import->source) {
                    $dayStart = CarbonImmutable::parse($dateFor)->startOfDay();
                    $filename = PolarisRenewalNotification::query()
                        ->where('export_datetime', '>=', $dayStart)
                        ->where('export_datetime', '<', $dayStart->addDay())
                        ->orderBy('export_datetime')
                        ->value('source_filename')
                    ;
                } elseif ('ftp_overdue' === $import->source) {
                    $dayStart = CarbonImmutable::parse($dateFor)->startOfDay();
                    $filename = PolarisOverdueNotification::query()
                        ->where('export_datetime', '>=', $dayStart)
                        ->where('export_datetime', '<', $dayStart->addDay())
                        ->orderBy('export_datetime')
                        ->value('source_filename')
                    ;
                } elseif ('notification_log' === $import->source) {
                    $dayStart = CarbonImmutable::parse($dateFor)->startOfDay();
                    $filename = DeliveryConfirmation::query()
                        ->where('confirmation_source', 'notification_log')
                        ->where('notice_date', '>=', $dayStart)
                        ->where('notice_date', '<', $dayStart->addDay())
                        ->orderBy('notice_date')
                        ->value('source_filename')
                    ;
                } elseif ('phone_notices' === $import->source) {
                    $filename = 'PhoneNotices_' . $dateFor . '.csv';
                } elseif ('notification_history' === $import->source) {
                    $filename = 'notification_history_' . $dateFor . '.txt';
                }
            }

            // Fall back to the batch identifier if we couldn't resolve a concrete filename
            $import->display_filename = $filename ?: $import->identifier;

            return $import;
        });

        // Get unique sources for filter dropdown
        $sources = NotificationImportBatch::select('source')
            ->distinct()
            ->orderBy('source')
            ->pluck('source')
        ;

        return view('notices::data.export-files', [
            'imports' => $imports,
            'sources' => $sources,
            'sort'    => $sortBy,
            'dir'     => $sortDir,
        ]);
    }

    /**
     * Display detail for a single ActivityFile.
     *
     * Shows file metadata plus all notification activity events linked
     * to this file (via notification_activity_logs.activity_file_id).
     */
    public function activityFileDetail(int $id)
    {
        $file = ActivityFile::with([
            'activityLogs' => function ($query) {
                $query->with('notification')->latest('occurred_at');
            },
            'jobRun',
        ])
            ->findOrFail($id)
        ;

        $activityCount = $file->activityLogs->count();
        $firstOccurred = $file->activityLogs->min('occurred_at');
        $lastOccurred  = $file->activityLogs->max('occurred_at');

        return view('notices::data.activity-file-detail', [
            'file'          => $file,
            'activityCount' => $activityCount,
            'firstOccurred' => $firstOccurred,
            'lastOccurred'  => $lastOccurred,
        ]);
    }

    /**
     * Parse a Y-m-d date into a day boundary for range queries.
     */
    protected function parseDateBoundary(mixed $value, bool $endExclusive = false): ?CarbonImmutable
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $dayStart = CarbonImmutable::parse($value)->startOfDay();

        return $endExclusive ? $dayStart->addDay() : $dayStart;
    }
}
