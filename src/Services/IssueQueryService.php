<?php

namespace Dcplibrary\Notices\Services;

use Dcplibrary\Notices\Models\PatronDeliveryIssue;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;

/**
 * Read/query service for issue list endpoints.
 *
 * This service holds query-heavy issue search/list logic so NotificationService
 * can focus on orchestration/business flows.
 */
class IssueQueryService
{
    /**
     * Get all active delivery issues with related data.
     *
     * @param null|string $issueType Filter by specific issue type
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getActiveIssues(?string $issueType = null)
    {
        $query = PatronDeliveryIssue::whereNull('resolved_at')
            ->with(['notification.deliveryOption', 'failedDeliveryString'])
            ->orderBy('detected_at', 'desc')
            ->orderBy('id', 'desc')
        ;

        if ($issueType) {
            $query->where('issue_type', $issueType);
        }

        return $query->paginate(50);
    }

    /**
     * Search and filter delivery issues with pagination and sorting.
     *
     * @param null|string $issueType      Optional issue type filter
     * @param array       $filters        Associative array of filter criteria
     * @param string      $sortBy         Column to sort by
     * @param string      $sortDir        Sort direction ('asc' or 'desc')
     * @param bool        $includeDetails When true, eager-load related notification/failed-string models
     * @param int         $perPage        Page size (bounded to 1..200)
     * @param bool        $includeTotals  When false, use simple pagination to avoid COUNT(*)
     */
    public function searchIssues(
        ?string $issueType = null,
        array $filters = [],
        string $sortBy = 'notification_date',
        string $sortDir = 'desc',
        bool $includeDetails = true,
        int $perPage = 50,
        bool $includeTotals = true
    ): PaginatorContract {
        $safePerPage   = max(1, min(200, $perPage));
        $selectColumns = $this->getIssueSelectColumns($includeDetails);

        $query = PatronDeliveryIssue::query();
        if ($includeDetails) {
            $query->with(['notification.deliveryOption', 'failedDeliveryString']);
        } else {
            // Keep list payload lean for API list endpoints.
            $query->select($selectColumns);
        }

        // Filter by resolved status - only show unresolved by default
        if (empty($filters['show_resolved'])) {
            $query->whereNull('resolved_at');
        }

        // Filter by issue type
        if ($issueType) {
            $query->where('issue_type', $issueType);
        }

        // Combined search - searches patron barcode, patron name, title, and item barcode
        if (!empty($filters['search'])) {
            // Normalize search term to match indexed values
            $searchTerm = \Dcplibrary\Notices\Support\SearchNormalizer::normalizeSearchTerm($filters['search']);
            if ($searchTerm) {
                $query->where(function ($q) use ($searchTerm) {
                    // Search patron barcode (qualify with table name to avoid ambiguity)
                    $q->where('patron_delivery_issues.patron_barcode', 'LIKE', '%' . $searchTerm . '%')
                      // Search patron name and title/item barcode in materialized notification columns.
                        ->orWhereHas('notification', function ($subQ) use ($searchTerm) {
                            $subQ->where(function ($payloadQ) use ($searchTerm) {
                                $payloadQ->where('search_patron_last_name', 'LIKE', '%' . $searchTerm . '%')
                                    ->orWhere('search_patron_first_name', 'LIKE', '%' . $searchTerm . '%')
                                    ->orWhere('search_title', 'LIKE', '%' . $searchTerm . '%')
                                    ->orWhere('search_item_barcode', 'LIKE', '%' . $searchTerm . '%')
                                ;
                            });
                        })
                    ;
                });
            }
        }

        // Filter by date range
        if (!empty($filters['date_from'])) {
            $query->where('detected_at', '>=', $filters['date_from'] . ' 00:00:00');
        }

        if (!empty($filters['date_to'])) {
            $query->where('detected_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        // Filter by delivery option
        if (!empty($filters['delivery_option_id'])) {
            $query->whereHas('notification', function ($q) use ($filters) {
                $q->where('delivery_option_id', $filters['delivery_option_id']);
            });
        }

        // Validate sort direction
        $sortDir = 'asc' === strtolower($sortDir) ? 'asc' : 'desc';

        // Handle sorting
        if ('notification_date' === $sortBy) {
            // Sort by notification export date (first_exported_at)
            $query->leftJoin('notifications as n', 'patron_delivery_issues.notification_id', '=', 'n.id')
                ->select($selectColumns)
                ->orderBy('n.first_exported_at', $sortDir)
                ->orderBy('patron_delivery_issues.id', $sortDir)
            ;
        } elseif ('delivery_option_id' === $sortBy) {
            // Sort by delivery method (Mail / Email / Voice / SMS)
            $query->leftJoin('notifications as n', 'patron_delivery_issues.notification_id', '=', 'n.id')
                ->select($selectColumns)
                ->orderBy('n.delivery_option_id', $sortDir)
                ->orderBy('patron_delivery_issues.id', $sortDir)
            ;
        } elseif ('item' === $sortBy) {
            // Sort by materialized item title from notification payload.
            $query->leftJoin('notifications as n', 'patron_delivery_issues.notification_id', '=', 'n.id')
                ->select($selectColumns)
                ->orderBy('n.search_title', $sortDir)
                ->orderBy('patron_delivery_issues.id', $sortDir)
            ;
        } else {
            // Validate sort column (whitelist to prevent SQL injection)
            $allowedSortColumns = [
                'detected_at',
                'patron_barcode',
                'issue_type',
            ];

            if (!in_array($sortBy, $allowedSortColumns, true)) {
                $sortBy = 'notification_date';
                // Default to notification_date - use LEFT JOIN to include issues without notifications
                $query->leftJoin('notifications as n', 'patron_delivery_issues.notification_id', '=', 'n.id')
                    ->select($selectColumns)
                    ->orderBy('n.first_exported_at', $sortDir)
                    ->orderBy('patron_delivery_issues.id', $sortDir)
                ;
            } else {
                $query->orderBy($sortBy, $sortDir)
                    ->orderBy('patron_delivery_issues.id', $sortDir)
                ;
            }
        }

        return $includeTotals
            ? $query->paginate($safePerPage)
            : $query->simplePaginate($safePerPage);
    }

    /**
     * Select columns for issue list queries.
     *
     * In lean mode, omit large text columns to reduce row payload and hydration cost.
     *
     * @return array<int, string>
     */
    protected function getIssueSelectColumns(bool $includeDetails): array
    {
        if ($includeDetails) {
            return ['patron_delivery_issues.*'];
        }

        return [
            'patron_delivery_issues.id',
            'patron_delivery_issues.issue_type',
            'patron_delivery_issues.patron_id',
            'patron_delivery_issues.patron_barcode',
            'patron_delivery_issues.failed_delivery_string_id',
            'patron_delivery_issues.notification_id',
            'patron_delivery_issues.detected_at',
            'patron_delivery_issues.resolved_at',
            'patron_delivery_issues.resolved_by_user_id',
            'patron_delivery_issues.resolved_by_name',
            'patron_delivery_issues.created_at',
            'patron_delivery_issues.updated_at',
        ];
    }
}
