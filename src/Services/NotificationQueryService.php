<?php

namespace Dcplibrary\Notices\Services;

use Dcplibrary\Notices\Models\Notification;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;

/**
 * Read/query service for notification list endpoints.
 *
 * This service isolates query-heavy notification list/search logic from
 * NotificationService orchestration methods.
 */
class NotificationQueryService
{
    /**
     * Search notifications with filters and sorting.
     *
     * @param array  $filters       Filters: date_from, date_to, status, notification_type,
     *                              delivery_option_id, patron_barcode, patron_name, title, item_barcode
     * @param string $sortBy        Column to sort by (defaults to first_exported_at)
     * @param string $sortDir       Sort direction: asc or desc (defaults to desc)
     * @param int    $perPage       Page size (bounded to 1..100)
     * @param bool   $includeTotals When false, uses simple pagination to avoid COUNT(*)
     */
    public function searchNotifications(
        array $filters = [],
        string $sortBy = 'first_exported_at',
        string $sortDir = 'desc',
        int $perPage = 50,
        bool $includeTotals = true
    ): PaginatorContract {
        $safePerPage   = max(1, min($perPage, 100));
        $selectColumns = $this->getNotificationSelectColumns();

        $query = Notification::query()
            ->select($selectColumns)
            ->with([
                'deliveryOption:id,delivery_option',
                'importBatch:id,identifier',
            ])
            ->withCount(['activeIssues' => function ($query) {
                $query->whereNull('resolved_at');
            }])
        ;

        if (!empty($filters['date_from'])) {
            $query->where('first_exported_at', '>=', $filters['date_from'] . ' 00:00:00');
        }

        if (!empty($filters['date_to'])) {
            $query->where('first_exported_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['hour'])) {
            // Convert app timezone hour to UTC for database query
            [$sql, $bindings] = \Dcplibrary\Notices\Support\TimezoneHelper::buildHourWhereClause(
                'first_exported_at',
                (int) $filters['hour']
            );
            $query->whereRaw($sql, $bindings);
        }

        if (!empty($filters['notification_type'])) {
            // UI submits notification_type as the lookup table ID (notification_type_id).
            // Support both numeric IDs (preferred) and legacy string values.
            // Some older notifications have notification_type_id=NULL but notification_type
            // populated with the string value, so we need to match both.
            if (is_numeric($filters['notification_type'])) {
                $typeId          = (int) $filters['notification_type'];
                $typeDescription = \Dcplibrary\Notices\Models\NotificationType::find($typeId)?->description;

                $query->where(function ($q) use ($typeId, $typeDescription) {
                    $q->where('notification_type_id', $typeId);
                    // Also match by string description for notifications with NULL type ID
                    if ($typeDescription) {
                        $q->orWhere(function ($subq) use ($typeDescription) {
                            $subq->whereNull('notification_type_id')
                                ->where('notification_type', $typeDescription)
                            ;
                        });
                    }
                });
            } else {
                $query->where('notification_type', $filters['notification_type']);
            }
        }

        if (!empty($filters['delivery_option_id'])) {
            $query->where('delivery_option_id', $filters['delivery_option_id']);
        }

        // Filter for orphaned notifications only (Polaris → LX Starter failures)
        if (!empty($filters['orphaned'])) {
            $query->where('delivery_option_id', 2) // Email only
                ->where(function ($q) {
                    // Failed with polaris_export_failure issue (active or resolved)
                    // OR currently pending >24h (active orphaned)
                    $q->where(function ($subq) {
                        $subq->where('status', 'failed')
                            ->whereHas('issues', function ($issueQuery) {
                                $issueQuery->where('issue_type', 'polaris_export_failure');
                            })
                        ;
                    })->orWhere(function ($subq) {
                        $cutoffDate = now()->subHours(24);
                        $subq->where('status', 'pending')
                            ->where('first_exported_at', '<', $cutoffDate)
                        ;
                    });
                })
            ;
        }

        // Filter for LS Starter communication failures only (system-level email failures), using
        // the standardized failure_reason substring set by the reconciler.
        if (!empty($filters['lx_starter_failure_only'])) {
            $query->where('delivery_option_id', 2)
                ->where('status', 'failed')
                ->where('failure_reason', 'LIKE', '%LX Starter communication error%')
            ;
        }

        // Combined search - searches patron barcode, patron names, title, item barcode, phone, and email
        if (!empty($filters['search'])) {
            $rawSearch = trim($filters['search']);

            // Different fields require different normalization:
            // - Names: lowercase, strip punctuation (apostrophes, hyphens)
            // - Titles: lowercase only
            // - Barcodes: lowercase only
            // - Phone: digits only
            // - Email: lowercase only
            $searchName    = \Dcplibrary\Notices\Support\SearchNormalizer::normalizeName($rawSearch);
            $searchTitle   = \Dcplibrary\Notices\Support\SearchNormalizer::normalizeTitle($rawSearch);
            $searchBarcode = \Dcplibrary\Notices\Support\SearchNormalizer::normalizeBarcode($rawSearch);
            $searchPhone   = \Dcplibrary\Notices\Support\SearchNormalizer::normalizePhone($rawSearch);
            $searchEmail   = \Dcplibrary\Notices\Support\SearchNormalizer::normalizeEmail($rawSearch);

            // Escape SQL LIKE wildcards (% and _) to treat them as literal characters
            $escapeLike = fn (?string $v) => $v ? addcslashes($v, '%_') : null;

            $query->where(function ($q) use ($searchName, $searchTitle, $searchBarcode, $searchPhone, $searchEmail, $escapeLike) {
                // Patron barcode (lowercase)
                if ($searchBarcode) {
                    $q->orWhere('patron_barcode', 'LIKE', '%' . $escapeLike($searchBarcode) . '%');
                }

                // Patron names (normalized: lowercase, no punctuation)
                if ($searchName) {
                    $escaped = $escapeLike($searchName);
                    $q->orWhere('search_patron_last_name', 'LIKE', '%' . $escaped . '%')
                        ->orWhere('search_patron_first_name', 'LIKE', '%' . $escaped . '%')
                        ->orWhere('search_full_name', 'LIKE', '%' . $escaped . '%')
                    ;
                }

                // Title (lowercase)
                if ($searchTitle) {
                    $q->orWhere('search_title', 'LIKE', '%' . $escapeLike($searchTitle) . '%');
                }

                // Item barcode (lowercase)
                if ($searchBarcode) {
                    $q->orWhere('search_item_barcode', 'LIKE', '%' . $escapeLike($searchBarcode) . '%');
                }

                // Phone number (digits only) - no escaping needed, digits only
                if ($searchPhone && strlen($searchPhone) >= 4) {
                    $q->orWhere('indexed_patron_phone', 'LIKE', '%' . $searchPhone . '%');
                }

                // Email address (lowercase)
                if ($searchEmail && str_contains($searchEmail, '@')) {
                    $q->orWhere('indexed_patron_email', 'LIKE', '%' . $escapeLike($searchEmail) . '%');
                }
            });
        }

        // Validate sort direction
        $sortDir = 'asc' === strtolower($sortDir) ? 'asc' : 'desc';

        // Validate sort column (whitelist to prevent SQL injection)
        $allowedSortColumns = [
            'first_exported_at',
            'patron_barcode',
            'notification_type',
            'status',
            'delivery_option_id',
        ];

        if (!in_array($sortBy, $allowedSortColumns, true)) {
            // Handle secondary sort aliases.
            if ('title' === $sortBy) {
                $query->orderBy('search_title', $sortDir)
                    ->orderBy('id', $sortDir)
                ;

                return $includeTotals
                    ? $query->paginate($safePerPage)
                    : $query->simplePaginate($safePerPage);
            }
            $sortBy = 'first_exported_at';
        }

        // Special handling for patron_barcode to sort by last name, first name.
        if ('patron_barcode' === $sortBy) {
            $query->orderBy('search_patron_last_name', $sortDir)
                ->orderBy('search_patron_first_name', $sortDir)
                ->orderBy('id', $sortDir)
            ;

            return $includeTotals
                ? $query->paginate($safePerPage)
                : $query->simplePaginate($safePerPage);
        }

        $query->orderBy($sortBy, $sortDir)
            ->orderBy('id', $sortDir)
        ;

        return $includeTotals
            ? $query->paginate($safePerPage)
            : $query->simplePaginate($safePerPage);
    }

    /**
     * Select columns for notification list queries.
     *
     * Excludes raw_payload to keep list queries efficient.
     * Display columns (display_*) have original case; search columns are normalized.
     *
     * @return array<int, string>
     */
    protected function getNotificationSelectColumns(): array
    {
        return [
            'id',
            'import_batch_id',
            'notification_key',
            'export_filename',
            'patron_barcode',
            'patron_id',
            'organization_id',
            'delivery_option_id',
            'notification_type_id',
            'ingested_email_id',
            'notification_type',
            'notice_date',
            'schedule_step',
            'generated_at',
            'first_exported_at',
            'last_exported_at',
            'expected_delivery_at',
            'delivered_at',
            'failed_at',
            'status',
            'is_combined',
            'failure_reason',
            // Display columns: original case for UI rendering
            'display_patron_first_name',
            'display_patron_last_name',
            'display_title',
            'display_item_barcode',
            // Search columns: normalized for matching
            'search_patron_first_name',
            'search_patron_last_name',
            'search_full_name',
            'search_title',
            'search_item_barcode',
            // Indexed contact info for display and search
            'indexed_patron_phone',
            'indexed_patron_email',
            'created_at',
            'updated_at',
        ];
    }
}
