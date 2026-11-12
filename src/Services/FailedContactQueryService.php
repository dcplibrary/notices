<?php

namespace Dcplibrary\Notices\Services;

use Dcplibrary\Notices\Models\FailedDeliveryString;
use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\PatronDeliveryIssue;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FailedContactQueryService
{
    /**
     * Get a small sample of unresolved failed contacts for dashboard CTA cards.
     *
     * Results are derived from failed_delivery_strings and enriched with a
     * representative patron (when available) so the dashboard can say
     * "These patrons are not getting notifications" with concrete examples.
     *
     * For SMS/Voice contacts this method will:
     * - Look up the phone in polaris_hold/renew/overdue notifications.
     * - Fall back to Notification rows whose failure_reason/raw_payload
     *   mention the phone when raw exports are outside the current window.
     *
     * @param array<int> $deliveryOptionIds Filter by delivery option IDs (e.g., [3, 8] for SMS/Voice, [2] for Email)
     * @param int        $limit             Maximum number of contacts to return
     *
     * @return Collection<FailedDeliveryString>
     */
    public function getDashboardFailedContacts(array $deliveryOptionIds = [], int $limit = 5): Collection
    {
        $deliveryKey = !empty($deliveryOptionIds) ? implode(',', $deliveryOptionIds) : 'all';
        $cacheKey    = sprintf('notices:dashboard_failed_contacts:%s:%d', $deliveryKey, $limit);

        return Cache::remember($cacheKey, now()->addMinutes(2), function () use ($deliveryOptionIds, $limit): Collection {
            $isSmsVoice = !empty(array_intersect($deliveryOptionIds, [3, 8]));

            // Only treat these as patron-contact problems for SMS/Voice.
            // Generic shoutbomb_voice_failure summaries can represent transient delivery failures.
            $smsVoiceContactSources = [
                'shoutbomb_invalid_phone',
                'shoutbomb_voice_system_error',
            ];

            $query = FailedDeliveryString::query()
                ->with([
                    'deliveryOption',
                    'patronIssues' => function ($q) {
                        $q->orderByDesc('resolved_at');
                    },
                ])
                ->where('is_resolved', false)
            ;

            if (!empty($deliveryOptionIds)) {
                $query->whereIn('delivery_option_id', $deliveryOptionIds);
            }

            if ($isSmsVoice) {
                $query->whereIn('failure_source', $smsVoiceContactSources);
            }

            // Over-fetch slightly so we can do a stable in-memory sort by patron name
            // after enrichment without loading the entire failed-contacts dataset.
            $prefetch = max(10, $limit * 4);

            $failedStrings = $query
                ->orderByDesc('last_failed_at')
                ->orderByDesc('affected_patron_count')
                ->limit($prefetch)
                ->get()
            ;

            foreach ($failedStrings as $failedString) {
                $this->enrichFailedDeliveryString($failedString);
            }

            $mismatchedIssues = collect();
            if ($isSmsVoice) {
                $mismatchedPrefetch = max(5, $limit * 2);

                $issues = PatronDeliveryIssue::query()
                    ->where('issue_type', 'mismatched_preference')
                    ->whereNull('resolved_at')
                    ->orderByDesc('detected_at')
                    ->limit($mismatchedPrefetch)
                    ->get()
                ;

                // Batch-load the confirmation rows used to create these issues.
                $barcodes               = $issues->pluck('patron_barcode')->filter()->unique()->values();
                $confirmationsByBarcode = $barcodes->isEmpty()
                    ? collect()
                    : DB::table('delivery_confirmations')
                        ->where('confirmation_source', 'shoutbomb_mismatched_preference')
                        ->whereNull('notification_id')
                        ->whereIn('patron_barcode', $barcodes->all())
                        ->select('patron_barcode', 'delivery_string', 'delivery_option_id')
                        ->get()
                        ->keyBy('patron_barcode')
                ;

                $mismatchedIssues = $issues->map(function ($issue) use ($confirmationsByBarcode) {
                    $deliveryConfirmation = $issue->patron_barcode
                        ? $confirmationsByBarcode->get($issue->patron_barcode)
                        : null;

                    $phone = $deliveryConfirmation?->delivery_string
                        ?? \Dcplibrary\Notices\Support\IssueDisplayHelper::extractPhoneFromDetails($issue->issue_details);

                    $deliveryOptionId = $deliveryConfirmation?->delivery_option_id ?? 3;

                    $patron = $this->findPatronByIdOrBarcode($issue->patron_id, $issue->patron_barcode);

                    $pseudo                        = new \stdClass();
                    $pseudo->id                    = 'mismatched_' . $issue->id;
                    $pseudo->delivery_string       = $phone ?? 'Unknown';
                    $pseudo->delivery_option_id    = $deliveryOptionId;
                    $pseudo->string_type           = 'phone';
                    $pseudo->failure_source        = 'Mismatched Preference';
                    $pseudo->failure_details       = $issue->issue_details;
                    $pseudo->failure_count         = 1;
                    $pseudo->affected_patron_count = 1;
                    $pseudo->last_failed_at        = $issue->detected_at;
                    $pseudo->first_failed_at       = $issue->detected_at;
                    $pseudo->is_resolved           = false;
                    $pseudo->patron                = $patron;
                    $pseudo->issue_id              = $issue->id;
                    $pseudo->issue_type            = 'mismatched_preference';
                    $pseudo->deliveryOption        = null;

                    if ($patron) {
                        $pseudo->example_patron_barcode = $patron->patron_barcode;
                        $pseudo->example_patron_name    = trim(($patron->patron_first_name ?? '') . ' ' . ($patron->patron_last_name ?? ''));
                    } else {
                        $pseudo->example_patron_barcode = $issue->patron_barcode;
                        $pseudo->example_patron_name    = null;
                    }

                    return $pseudo;
                });
            }

            $allContacts = $failedStrings->concat($mismatchedIssues);

            $sorted = $allContacts->sort(function ($a, $b): int {
                // 1) last_failed_at DESC
                $aTime = $a->last_failed_at ?? $a->first_failed_at ?? null;
                $bTime = $b->last_failed_at ?? $b->first_failed_at ?? null;
                if ($aTime && $bTime && $aTime != $bTime) {
                    return $aTime < $bTime ? 1 : -1;
                }

                // 2) Patron last name ASC
                $aPatron = $a->patron                  ?? null;
                $bPatron = $b->patron                  ?? null;
                $aLast   = $aPatron?->patron_last_name ?? '';
                $bLast   = $bPatron?->patron_last_name ?? '';
                $cmpLast = strcasecmp($aLast, $bLast);
                if (0 !== $cmpLast) {
                    return $cmpLast;
                }

                // 3) Patron first name ASC
                $aFirst = $aPatron?->patron_first_name ?? '';
                $bFirst = $bPatron?->patron_first_name ?? '';

                return strcasecmp($aFirst, $bFirst);
            });

            return $sorted->take($limit)->values();
        });
    }

    /**
     * Get failed delivery strings (emails/phones that have failed).
     *
     * By default, only unresolved rows (is_resolved = false) are returned so that
     * active problems are highlighted. When $includeResolved is true, both active
     * and resolved contacts are returned for history views.
     *
     * This powers the Failed Contacts summary view and is the canonical
     * source for `FailedDeliveryString` aggregates.
     *
     * Also includes mismatched preference issues (Voice/SMS only) as they represent
     * failed contacts that need to be updated.
     */
    public function getFailedDeliveryStrings(bool $includeResolved = false, array $filters = []): Collection
    {
        $search   = trim((string) ($filters['search'] ?? ''));
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo   = $filters['date_to']   ?? null;

        $hasDateRange = is_string($dateFrom) && is_string($dateTo)
                                             && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)
                                             && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo);

        $query = FailedDeliveryString::with([
            'deliveryOption',
            // Preload patron issues so we can derive resolution metadata without N+1 queries
            'patronIssues' => function ($q) {
                $q->orderByDesc('resolved_at');
            },
        ]);

        if (!$includeResolved) {
            $query->where('is_resolved', false);
        }

        if ($hasDateRange) {
            $query->whereBetween('last_failed_at', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59',
            ]);
        }

        if ('' !== $search) {
            $query->where(function ($q) use ($search) {
                $q->where('delivery_string', 'LIKE', '%' . $search . '%')
                    ->orWhere('failure_details', 'LIKE', '%' . $search . '%')
                    ->orWhereExists(function ($subq) use ($search) {
                        $subq->select(DB::raw(1))
                            ->from('patron_delivery_issues as pdi')
                            ->whereColumn('pdi.failed_delivery_string_id', 'failed_delivery_strings.id')
                            ->where('pdi.patron_barcode', 'LIKE', '%' . $search . '%')
                        ;
                    })
                ;
            });
        }

        $failedStrings = $query
            ->orderBy('affected_patron_count', 'desc')
            ->orderBy('last_failed_at', 'desc')
            ->get()
        ;

        // PERF: Preload representative patron info in bulk to avoid N+1 queries.
        // We only do the "primary" lookups (by phone/email in raw Polaris tables) here;
        // the existing per-row fallbacks (notification payload parsing, failure_details parsing)
        // still run for any rows that don't get a match from the raw tables.
        $prefetchedPatrons = [
            'email' => [],
            'phone' => [],
        ];

        $emailStrings = $failedStrings
            ->where('delivery_option_id', 2)
            ->pluck('delivery_string')
            ->filter()
            ->unique()
            ->values()
            ->all()
        ;

        $phoneStrings = $failedStrings
            ->filter(fn ($row) => in_array((int) ($row->delivery_option_id ?? 0), [3, 8], true))
            ->pluck('delivery_string')
            ->filter()
            ->unique()
            ->values()
            ->all()
        ;

        $fetchByField = function (string $table, string $field, array $values): array {
            if (empty($values)) {
                return [];
            }

            // Select the lookup field too so we can build a map.
            return DB::table($table)
                ->whereIn($field, $values)
                ->select(
                    $field . ' as lookup_value',
                    'patron_id',
                    'patron_barcode',
                    'patron_first_name',
                    'patron_last_name'
                )
                ->get()
                ->keyBy('lookup_value')
                ->toArray()
            ;
        };

        // Prefer holds, then renewals, then overdues.
        foreach ([
            ['table' => 'polaris_hold_notifications', 'field' => 'patron_email', 'bucket' => 'email', 'values' => $emailStrings],
            ['table' => 'polaris_renewal_notifications', 'field' => 'patron_email', 'bucket' => 'email', 'values' => $emailStrings],
            ['table' => 'polaris_overdue_notifications', 'field' => 'patron_email', 'bucket' => 'email', 'values' => $emailStrings],
        ] as $spec) {
            $rows = $fetchByField($spec['table'], $spec['field'], $spec['values']);
            foreach ($rows as $lookup => $patron) {
                if (!isset($prefetchedPatrons[$spec['bucket']][$lookup])) {
                    $prefetchedPatrons[$spec['bucket']][$lookup] = $patron;
                }
            }
        }

        foreach ([
            ['table' => 'polaris_hold_notifications', 'field' => 'patron_phone', 'bucket' => 'phone', 'values' => $phoneStrings],
            ['table' => 'polaris_renewal_notifications', 'field' => 'patron_phone', 'bucket' => 'phone', 'values' => $phoneStrings],
            ['table' => 'polaris_overdue_notifications', 'field' => 'patron_phone', 'bucket' => 'phone', 'values' => $phoneStrings],
        ] as $spec) {
            $rows = $fetchByField($spec['table'], $spec['field'], $spec['values']);
            foreach ($rows as $lookup => $patron) {
                if (!isset($prefetchedPatrons[$spec['bucket']][$lookup])) {
                    $prefetchedPatrons[$spec['bucket']][$lookup] = $patron;
                }
            }
        }

        // Add patron information and resolution metadata to each failed string
        foreach ($failedStrings as $failedString) {
            $this->enrichFailedDeliveryString($failedString, $prefetchedPatrons);

            // Derive resolution user/time from linked PatronDeliveryIssue rows when available
            if ($failedString->is_resolved && $failedString->patronIssues->isNotEmpty()) {
                $resolvedIssue = $failedString->patronIssues
                    ->firstWhere('resolved_at', '!=', null) ?? $failedString->patronIssues->first()
                ;

                if ($resolvedIssue) {
                    // Dynamic properties used by views; not persisted
                    $failedString->resolved_summary_name = $resolvedIssue->resolved_by_name;
                    $failedString->resolved_summary_at   = $resolvedIssue->resolved_at;
                }
            }
        }

        // Get mismatched preference issues (Voice 3 and SMS 8 only)
        $mismatchedQuery = PatronDeliveryIssue::where('issue_type', 'mismatched_preference')
            ->with(['notification.deliveryOption'])
        ;

        if (!$includeResolved) {
            $mismatchedQuery->whereNull('resolved_at');
        }

        if ($hasDateRange) {
            $mismatchedQuery->whereBetween('detected_at', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59',
            ]);
        }

        $mismatchedIssuesRaw = $mismatchedQuery->get();

        // PERF: Prefetch mismatched-preference confirmations and patron info in bulk.
        $mismatchedBarcodes = $mismatchedIssuesRaw
            ->pluck('patron_barcode')
            ->filter()
            ->unique()
            ->values()
            ->all()
        ;

        $mismatchedConfirmationsByBarcode = [];
        if (!empty($mismatchedBarcodes)) {
            $mismatchedConfirmationsByBarcode = DB::table('delivery_confirmations')
                ->where('confirmation_source', 'shoutbomb_mismatched_preference')
                ->whereIn('patron_barcode', $mismatchedBarcodes)
                ->whereNull('notification_id')
                ->orderByDesc('notice_date')
                ->get(['patron_barcode', 'delivery_string', 'delivery_option_id', 'notice_date'])
                ->groupBy('patron_barcode')
                ->map(fn ($rows) => $rows->first())
                ->toArray()
            ;
        }

        // Patron lookup: try by barcode first (cheap + stable across exports).
        $mismatchedPatronsByBarcode = [];
        foreach ([
            'polaris_hold_notifications',
            'polaris_renewal_notifications',
            'polaris_overdue_notifications',
        ] as $table) {
            if (empty($mismatchedBarcodes)) {
                break;
            }
            $rows = DB::table($table)
                ->whereIn('patron_barcode', $mismatchedBarcodes)
                ->select('patron_barcode', 'patron_id', 'patron_first_name', 'patron_last_name')
                ->get()
                ->keyBy('patron_barcode')
                ->toArray()
            ;

            foreach ($rows as $barcode => $row) {
                if (!isset($mismatchedPatronsByBarcode[$barcode])) {
                    $mismatchedPatronsByBarcode[$barcode] = (object) [
                        'patron_id'         => $row->patron_id ?? null,
                        'patron_barcode'    => $barcode,
                        'patron_first_name' => $row->patron_first_name ?? null,
                        'patron_last_name'  => $row->patron_last_name  ?? null,
                    ];
                }
            }
        }

        $mismatchedIssues = $mismatchedIssuesRaw->map(function ($issue) use ($mismatchedConfirmationsByBarcode, $mismatchedPatronsByBarcode) {
            $deliveryConfirmation = $issue->patron_barcode
                ? ($mismatchedConfirmationsByBarcode[$issue->patron_barcode] ?? null)
                : null;

            $phone = $deliveryConfirmation?->delivery_string
                ?? \Dcplibrary\Notices\Support\IssueDisplayHelper::extractPhoneFromDetails($issue->issue_details);

            // Get delivery option from confirmation or default to Voice (3)
            $deliveryOptionId = (int) ($deliveryConfirmation?->delivery_option_id ?? 3);

            $patron = $issue->patron_barcode
                ? ($mismatchedPatronsByBarcode[$issue->patron_barcode] ?? null)
                : null;

            // Create a pseudo-FailedDeliveryString object
            $pseudo                        = new \stdClass();
            $pseudo->id                    = 'mismatched_' . $issue->id;
            $pseudo->delivery_string       = $phone ?? 'Unknown';
            $pseudo->delivery_option_id    = $deliveryOptionId;
            $pseudo->string_type           = 'phone';
            $pseudo->failure_source        = 'Mismatched Preference';
            $pseudo->failure_details       = $issue->issue_details;
            $pseudo->failure_count         = 1;
            $pseudo->affected_patron_count = 1;
            $pseudo->last_failed_at        = $issue->detected_at;
            $pseudo->first_failed_at       = $issue->detected_at;
            $pseudo->is_resolved           = null !== $issue->resolved_at;
            $pseudo->patron                = $patron;
            $pseudo->issue_id              = $issue->id;
            $pseudo->issue_type            = 'mismatched_preference';
            $pseudo->deliveryOption        = $issue->notification?->deliveryOption;

            // Add patron convenience attributes for views
            if ($patron) {
                $pseudo->example_patron_barcode = $patron->patron_barcode;
                $pseudo->example_patron_name    = trim(($patron->patron_first_name ?? '') . ' ' . ($patron->patron_last_name ?? ''));
            } else {
                $pseudo->example_patron_barcode = $issue->patron_barcode;
                $pseudo->example_patron_name    = null;
            }

            return $pseudo;
        });

        // Combine failed strings and mismatched issues
        $allContacts = $failedStrings->concat($mismatchedIssues);

        if ('' !== $search) {
            $needle      = strtolower($search);
            $allContacts = $allContacts->filter(function ($row) use ($needle) {
                $hay = strtolower(
                    (string) ($row->delivery_string ?? '') . ' '
                    . (string) ($row->failure_details ?? '') . ' '
                    . (string) ($row->example_patron_barcode ?? '')
                );

                return str_contains($hay, $needle);
            });
        }

        // Sort by affected patron count (desc), then by last failed at (desc)
        return $allContacts->sort(function ($a, $b): int {
            // 1) affected_patron_count DESC
            $aCount = $a->affected_patron_count ?? 0;
            $bCount = $b->affected_patron_count ?? 0;
            if ($aCount != $bCount) {
                return $bCount <=> $aCount;
            }

            // 2) last_failed_at DESC
            $aTime = $a->last_failed_at ?? $a->first_failed_at ?? null;
            $bTime = $b->last_failed_at ?? $b->first_failed_at ?? null;
            if ($aTime && $bTime && $aTime != $bTime) {
                return $aTime < $bTime ? 1 : -1;
            }

            return 0;
        })->values();
    }

    /**
     * Get aggregate counts of unresolved failed contacts by channel.
     *
     * Counts include:
     * - FailedDeliveryString rows (unresolved)
     * - mismatched_preference PatronDeliveryIssue rows (unresolved)
     *
     * Returns counts grouped into logical buckets used by the dashboard/UI:
     * - sms_voice: Voice (3) + SMS (8) + mismatched_preference
     * - email: Email (2)
     * - combined: sms_voice + email
     *
     * @return array{
     *   sms_voice: array{patrons:int, contacts:int, failures:int},
     *   email: array{patrons:int, contacts:int, failures:int},
     *   combined: array{patrons:int, contacts:int, failures:int}
     * }
     */
    public function getFailedContactCountsByChannel(): array
    {
        return Cache::remember('notices:stats:failed_contact_counts', now()->addMinutes(2), function (): array {
            // Only treat these as "contact health" problems for SMS/Voice.
            $smsVoiceContactSources = [
                'shoutbomb_invalid_phone',
                'shoutbomb_voice_system_error',
            ];

            $base = FailedDeliveryString::query()
                ->where('is_resolved', false)
                ->where(function ($q) use ($smsVoiceContactSources) {
                    // Email counts: include all unresolved failed delivery strings.
                    $q->where('delivery_option_id', 2)
                        // Voice/SMS counts: exclude generic voice-failure summaries; keep true contact problems only.
                        ->orWhere(function ($q2) use ($smsVoiceContactSources) {
                            $q2->whereIn('delivery_option_id', [3, 8])
                                ->whereIn('failure_source', $smsVoiceContactSources)
                            ;
                        })
                    ;
                })
                ->selectRaw('delivery_option_id, SUM(affected_patron_count) as patrons, COUNT(*) as contacts, SUM(failure_count) as failures')
                ->groupBy('delivery_option_id')
                ->get()
                ->keyBy('delivery_option_id')
            ;

            // Count mismatched preference issues (Voice 3 and SMS 8 only)
            $mismatchedQuery = PatronDeliveryIssue::where('issue_type', 'mismatched_preference')
                ->whereNull('resolved_at')
            ;

            $mismatchedIssues = (int) $mismatchedQuery->count();

            // Prefer distinct barcodes for patron count; fall back to distinct patron_id; fall back to total issue count.
            $mismatchedPatrons = (int) $mismatchedQuery->clone()
                ->whereNotNull('patron_barcode')
                ->distinct('patron_barcode')
                ->count('patron_barcode')
            ;

            if (0 === $mismatchedPatrons) {
                $mismatchedPatrons = (int) $mismatchedQuery->clone()
                    ->whereNotNull('patron_id')
                    ->distinct('patron_id')
                    ->count('patron_id')
                ;
            }
            if (0 === $mismatchedPatrons) {
                $mismatchedPatrons = $mismatchedIssues;
            }

            $smsVoicePatrons  = (int) (($base->get(3)->patrons ?? 0) + ($base->get(8)->patrons ?? 0) + $mismatchedPatrons);
            $smsVoiceContacts = (int) (($base->get(3)->contacts ?? 0) + ($base->get(8)->contacts ?? 0) + $mismatchedIssues);
            $smsVoiceFailures = (int) (($base->get(3)->failures ?? 0) + ($base->get(8)->failures ?? 0) + $mismatchedIssues);

            $emailPatrons  = (int) ($base->get(2)->patrons ?? 0);
            $emailContacts = (int) ($base->get(2)->contacts ?? 0);
            $emailFailures = (int) ($base->get(2)->failures ?? 0);

            $combinedPatrons  = (int) ($smsVoicePatrons + $emailPatrons);
            $combinedContacts = (int) ($smsVoiceContacts + $emailContacts);
            $combinedFailures = (int) ($smsVoiceFailures + $emailFailures);

            return [
                'sms_voice' => [
                    'patrons'  => $smsVoicePatrons,
                    'contacts' => $smsVoiceContacts,
                    'failures' => $smsVoiceFailures,
                ],
                'email' => [
                    'patrons'  => $emailPatrons,
                    'contacts' => $emailContacts,
                    'failures' => $emailFailures,
                ],
                'combined' => [
                    'patrons'  => $combinedPatrons,
                    'contacts' => $combinedContacts,
                    'failures' => $combinedFailures,
                ],
            ];
        });
    }

    /**
     * Date-scoped variant of getFailedContactCountsByChannel().
     *
     * When a valid $dateFrom/$dateTo range is provided, limits counts to
     * unresolved failed contacts whose most recent failure (FailedDeliveryString
     * last_failed_at) or detection time (PatronDeliveryIssue detected_at) falls
     * within the range. When the range is missing/invalid, falls back to the
     * all-time unresolved aggregates.
     */
    public function getFailedContactCountsByChannelForRange(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $hasDateRange = is_string($dateFrom) && is_string($dateTo)
                                             && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)
                                             && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo);

        if (!$hasDateRange) {
            return $this->getFailedContactCountsByChannel();
        }

        $cacheKey = sprintf('notices:stats:failed_contact_counts:%s:%s', $dateFrom, $dateTo);

        return Cache::remember($cacheKey, now()->addMinutes(2), function () use ($dateFrom, $dateTo): array {
            // Only treat these as "contact health" problems for SMS/Voice.
            $smsVoiceContactSources = [
                'shoutbomb_invalid_phone',
                'shoutbomb_voice_system_error',
            ];

            $base = FailedDeliveryString::query()
                ->where('is_resolved', false)
                ->whereBetween('last_failed_at', [
                    $dateFrom . ' 00:00:00',
                    $dateTo . ' 23:59:59',
                ])
                ->where(function ($q) use ($smsVoiceContactSources) {
                    // Email counts: include all unresolved failed delivery strings.
                    $q->where('delivery_option_id', 2)
                        // Voice/SMS counts: exclude generic voice-failure summaries; keep true contact problems only.
                        ->orWhere(function ($q2) use ($smsVoiceContactSources) {
                            $q2->whereIn('delivery_option_id', [3, 8])
                                ->whereIn('failure_source', $smsVoiceContactSources)
                            ;
                        })
                    ;
                })
                ->selectRaw('delivery_option_id, SUM(affected_patron_count) as patrons, COUNT(*) as contacts, SUM(failure_count) as failures')
                ->groupBy('delivery_option_id')
                ->get()
                ->keyBy('delivery_option_id')
            ;

            // Count mismatched preference issues (Voice 3 and SMS 8 only)
            $mismatchedQuery = PatronDeliveryIssue::where('issue_type', 'mismatched_preference')
                ->whereNull('resolved_at')
                ->whereBetween('detected_at', [
                    $dateFrom . ' 00:00:00',
                    $dateTo . ' 23:59:59',
                ])
            ;

            $mismatchedIssues = (int) $mismatchedQuery->count();

            // Prefer distinct barcodes for patron count; fall back to distinct patron_id; fall back to total issue count.
            $mismatchedPatrons = (int) $mismatchedQuery->clone()
                ->whereNotNull('patron_barcode')
                ->distinct('patron_barcode')
                ->count('patron_barcode')
            ;

            if (0 === $mismatchedPatrons) {
                $mismatchedPatrons = (int) $mismatchedQuery->clone()
                    ->whereNotNull('patron_id')
                    ->distinct('patron_id')
                    ->count('patron_id')
                ;
            }
            if (0 === $mismatchedPatrons) {
                $mismatchedPatrons = $mismatchedIssues;
            }

            $smsVoicePatrons  = (int) (($base->get(3)->patrons ?? 0) + ($base->get(8)->patrons ?? 0) + $mismatchedPatrons);
            $smsVoiceContacts = (int) (($base->get(3)->contacts ?? 0) + ($base->get(8)->contacts ?? 0) + $mismatchedIssues);
            $smsVoiceFailures = (int) (($base->get(3)->failures ?? 0) + ($base->get(8)->failures ?? 0) + $mismatchedIssues);

            $emailPatrons  = (int) ($base->get(2)->patrons ?? 0);
            $emailContacts = (int) ($base->get(2)->contacts ?? 0);
            $emailFailures = (int) ($base->get(2)->failures ?? 0);

            $combinedPatrons  = (int) ($smsVoicePatrons + $emailPatrons);
            $combinedContacts = (int) ($smsVoiceContacts + $emailContacts);
            $combinedFailures = (int) ($smsVoiceFailures + $emailFailures);

            return [
                'sms_voice' => [
                    'patrons'  => $smsVoicePatrons,
                    'contacts' => $smsVoiceContacts,
                    'failures' => $smsVoiceFailures,
                ],
                'email' => [
                    'patrons'  => $emailPatrons,
                    'contacts' => $emailContacts,
                    'failures' => $emailFailures,
                ],
                'combined' => [
                    'patrons'  => $combinedPatrons,
                    'contacts' => $combinedContacts,
                    'failures' => $combinedFailures,
                ],
            ];
        });
    }

    /**
     * Get paginated failed delivery strings for API list endpoints.
     *
     * This intentionally avoids expensive enrichment and cross-table synthesis so
     * large unresolved datasets can be paged directly in SQL.
     */
    public function getFailedDeliveryStringsPaginatedForApi(
        bool $includeResolved = false,
        array $filters = [],
        int $perPage = 50,
        int $page = 1,
        bool $includeTotals = false
    ): PaginatorContract {
        $search   = trim((string) ($filters['search'] ?? ''));
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo   = $filters['date_to']   ?? null;

        $hasDateRange = is_string($dateFrom) && is_string($dateTo)
                                             && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)
                                             && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo);

        $safePerPage = max(1, min($perPage, 200));
        $safePage    = max(1, $page);

        $query = FailedDeliveryString::query()
            ->with('deliveryOption')
        ;

        if (!$includeResolved) {
            $query->where('is_resolved', false);
        }

        if ($hasDateRange) {
            $query->whereBetween('last_failed_at', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59',
            ]);
        }

        if ('' !== $search) {
            $query->where(function ($q) use ($search) {
                $q->where('delivery_string', 'LIKE', '%' . $search . '%')
                    ->orWhere('failure_details', 'LIKE', '%' . $search . '%')
                    ->orWhereExists(function ($subq) use ($search) {
                        $subq->select(DB::raw(1))
                            ->from('patron_delivery_issues as pdi')
                            ->whereColumn('pdi.failed_delivery_string_id', 'failed_delivery_strings.id')
                            ->where('pdi.patron_barcode', 'LIKE', '%' . $search . '%')
                        ;
                    })
                ;
            });
        }

        $query
            ->orderBy('last_failed_at', 'desc')
            ->orderBy('affected_patron_count', 'desc')
            ->orderBy('id', 'desc')
        ;

        // For high-volume API reads, avoid the extra COUNT(*) query by default.
        // Callers can opt back into totals/last_page when needed.
        $paginator = $includeTotals
            ? $query->paginate($safePerPage, ['*'], 'page', $safePage)
            : $query->simplePaginate($safePerPage, ['*'], 'page', $safePage);

        // Add derived string_type expected by API consumers without costly enrichment.
        $paginator->getCollection()->transform(function (FailedDeliveryString $row) {
            $row->string_type = in_array((int) $row->delivery_option_id, [3, 8], true) ? 'phone' : 'email';

            return $row;
        });

        return $paginator;
    }

    /**
     * Get a single failed delivery string with enriched patron information.
     *
     * Unlike getFailedDeliveryStrings(), this will return a record even if it
     * has already been resolved so that the modal can show historical details.
     */
    public function getFailedDeliveryString(int $id): FailedDeliveryString
    {
        $failedString = FailedDeliveryString::where('id', $id)
            ->with('deliveryOption')
            ->firstOrFail()
        ;

        $this->enrichFailedDeliveryString($failedString);

        return $failedString;
    }

    /**
     * Find patron information by patron_id or patron_barcode.
     *
     * Searches across polaris_hold_notifications, polaris_renewal_notifications,
     * and polaris_overdue_notifications tables. First tries patron_id if provided,
     * then falls back to patron_barcode if no match found.
     *
     * @param null|int    $patronId Patron ID to search for
     * @param null|string $barcode  Patron barcode to search for
     *
     * @return null|object Patron object with patron_id, patron_barcode, patron_first_name, patron_last_name, or null if not found
     */
    public function findPatronByIdOrBarcode(?int $patronId, ?string $barcode): ?object
    {
        $patron = null;

        // First try by patron_id if provided
        if ($patronId) {
            $patron = DB::table('polaris_hold_notifications')
                ->where('patron_id', $patronId)
                ->select('patron_id', 'patron_barcode', 'patron_first_name', 'patron_last_name')
                ->first()
            ;

            if (!$patron) {
                $patron = DB::table('polaris_renewal_notifications')
                    ->where('patron_id', $patronId)
                    ->select('patron_id', 'patron_barcode', 'patron_first_name', 'patron_last_name')
                    ->first()
                ;
            }

            if (!$patron) {
                $patron = DB::table('polaris_overdue_notifications')
                    ->where('patron_id', $patronId)
                    ->select('patron_id', 'patron_barcode', 'patron_first_name', 'patron_last_name')
                    ->first()
                ;
            }
        }

        // Fall back to barcode lookup if no match by patron_id
        if (!$patron && $barcode) {
            $patron = DB::table('polaris_hold_notifications')
                ->where('patron_barcode', $barcode)
                ->select('patron_id', 'patron_barcode', 'patron_first_name', 'patron_last_name')
                ->first()
            ;

            if (!$patron) {
                $patron = DB::table('polaris_renewal_notifications')
                    ->where('patron_barcode', $barcode)
                    ->select('patron_id', 'patron_barcode', 'patron_first_name', 'patron_last_name')
                    ->first()
                ;
            }

            if (!$patron) {
                $patron = DB::table('polaris_overdue_notifications')
                    ->where('patron_barcode', $barcode)
                    ->select('patron_id', 'patron_barcode', 'patron_first_name', 'patron_last_name')
                    ->first()
                ;
            }
        }

        return $patron;
    }

    /**
     * Get delivery confirmation for a mismatched preference issue.
     *
     * Retrieves the delivery confirmation record that was used to create
     * a mismatched preference issue. Returns the phone number and delivery
     * option ID from the confirmation.
     *
     * @param string $patronBarcode Patron barcode to look up
     *
     * @return null|object Object with 'delivery_string' (phone) and 'delivery_option_id', or null if not found
     */
    public function getMismatchedPreferenceConfirmation(string $patronBarcode): ?object
    {
        return DB::table('delivery_confirmations')
            ->where('confirmation_source', 'shoutbomb_mismatched_preference')
            ->where('patron_barcode', $patronBarcode)
            ->whereNull('notification_id')
            ->select('delivery_string', 'delivery_option_id')
            ->first()
        ;
    }

    /**
     * Get patrons affected by a specific failed delivery string.
     */
    public function getAffectedPatrons(int $failedStringId): Collection
    {
        return PatronDeliveryIssue::where('issue_type', 'failed_delivery_string')
            ->where('failed_delivery_string_id', $failedStringId)
            ->whereNull('resolved_at')
            ->with('notification')
            ->orderBy('detected_at', 'desc')
            ->get()
        ;
    }

    /**
     * Enrich a FailedDeliveryString with representative patron information.
     *
     * Attaches a lightweight patron object and convenience attributes used by
     * the dashboard cards and failed-contacts table (example_patron_*).
     */
    protected function enrichFailedDeliveryString(FailedDeliveryString $failedString, ?array $prefetchedPatrons = null): void
    {
        // Get the first patron using this delivery string
        $patron = null;

        if (3 == $failedString->delivery_option_id || 8 == $failedString->delivery_option_id) {
            if ($prefetchedPatrons && isset($prefetchedPatrons['phone'][$failedString->delivery_string])) {
                $patron = $prefetchedPatrons['phone'][$failedString->delivery_string];
            }
        }
        if (!$patron && (3 == $failedString->delivery_option_id || 8 == $failedString->delivery_option_id)) {
            // Voice/SMS - search by phone in raw Polaris exports first
            $patron = \DB::table('polaris_hold_notifications')
                ->where('patron_phone', $failedString->delivery_string)
                ->select('patron_id', 'patron_barcode', 'patron_first_name', 'patron_last_name')
                ->first()
            ;

            if (!$patron) {
                $patron = \DB::table('polaris_renewal_notifications')
                    ->where('patron_phone', $failedString->delivery_string)
                    ->select('patron_id', 'patron_barcode', 'patron_first_name', 'patron_last_name')
                    ->first()
                ;
            }

            if (!$patron) {
                $patron = \DB::table('polaris_overdue_notifications')
                    ->where('patron_phone', $failedString->delivery_string)
                    ->select('patron_id', 'patron_barcode', 'patron_first_name', 'patron_last_name')
                    ->first()
                ;
            }

            // Secondary lookup path: if we still haven't found a patron, try to
            // locate a Notification that references this phone in its failure_reason
            // or raw_payload. This covers cases where we have Shoutbomb failures
            // but the underlying Polaris export rows for that phone are not
            // present in the current import window.
            if (!$patron) {
                // OPTIMIZATION: Try indexed lookup first (fast), then fall back to LIKE (slow)
                // This two-step approach allows MySQL to use the index efficiently
                $notification = Notification::query()
                    ->whereIn('delivery_option_id', [3, 8])
                    ->where('indexed_patron_phone', $failedString->delivery_string)
                    ->orderByDesc('first_exported_at')
                    ->first()
                ;

                // If not found via index, fall back to LIKE searches for legacy data
                if (!$notification) {
                    $notification = Notification::query()
                        ->whereIn('delivery_option_id', [3, 8])
                        ->whereNull('indexed_patron_phone')
                        ->where(function ($q) use ($failedString) {
                            $q->where('failure_reason', 'like', '%' . $failedString->delivery_string . '%')
                                ->orWhere('raw_payload', 'like', '%' . $failedString->delivery_string . '%')
                            ;
                        })
                        ->orderByDesc('first_exported_at')
                        ->first()
                    ;
                }

                if ($notification) {
                    // Try to pull name + barcode from the notification raw_payload
                    $raw     = $notification->raw_payload ? json_decode($notification->raw_payload, true) : null;
                    $rawRows = is_array($raw) ? $raw : [];

                    $pickFirstNonEmpty = function (array $rows, string $key) {
                        foreach ($rows as $row) {
                            if (!is_array($row)) {
                                continue;
                            }
                            $value = $row[$key] ?? null;
                            if (null !== $value && '' !== $value) {
                                return $value;
                            }
                        }

                        return null;
                    };

                    $patronId = $pickFirstNonEmpty($rawRows, 'patron_id')      ?? $notification->patron_id;
                    $barcode  = $pickFirstNonEmpty($rawRows, 'patron_barcode') ?? $notification->patron_barcode;
                    $first    = $pickFirstNonEmpty($rawRows, 'patron_first_name');
                    $last     = $pickFirstNonEmpty($rawRows, 'patron_last_name');

                    if ($patronId || $barcode || $first || $last) {
                        $patron = (object) [
                            'patron_id'         => $patronId,
                            'patron_barcode'    => $barcode,
                            'patron_first_name' => $first,
                            'patron_last_name'  => $last,
                        ];
                    }
                }
            }
        } elseif (2 == $failedString->delivery_option_id) {
            if ($prefetchedPatrons && isset($prefetchedPatrons['email'][$failedString->delivery_string])) {
                $patron = $prefetchedPatrons['email'][$failedString->delivery_string];
            }
        }
        if (!$patron && 2 == $failedString->delivery_option_id) {
            // Email - search by email
            $patron = \DB::table('polaris_hold_notifications')
                ->where('patron_email', $failedString->delivery_string)
                ->select('patron_id', 'patron_barcode', 'patron_first_name', 'patron_last_name')
                ->first()
            ;

            if (!$patron) {
                $patron = \DB::table('polaris_renewal_notifications')
                    ->where('patron_email', $failedString->delivery_string)
                    ->select('patron_id', 'patron_barcode', 'patron_first_name', 'patron_last_name')
                    ->first()
                ;
            }

            if (!$patron) {
                $patron = \DB::table('polaris_overdue_notifications')
                    ->where('patron_email', $failedString->delivery_string)
                    ->select('patron_id', 'patron_barcode', 'patron_first_name', 'patron_last_name')
                    ->first()
                ;
            }

            // Secondary lookup for email: fall back to Notification rows when
            // raw Polaris exports for this email are not present.
            if (!$patron) {
                // OPTIMIZATION: Try indexed lookup first (fast), then fall back to LIKE (slow)
                // This two-step approach allows MySQL to use the index efficiently
                $notification = Notification::query()
                    ->where('delivery_option_id', 2)
                    ->where('indexed_patron_email', $failedString->delivery_string)
                    ->orderByDesc('first_exported_at')
                    ->first()
                ;

                // If not found via index, fall back to LIKE searches for legacy data
                if (!$notification) {
                    $notification = Notification::query()
                        ->where('delivery_option_id', 2)
                        ->whereNull('indexed_patron_email')
                        ->where(function ($q) use ($failedString) {
                            $q->where('failure_reason', 'like', '%' . $failedString->delivery_string . '%')
                                ->orWhere('raw_payload', 'like', '%' . $failedString->delivery_string . '%')
                            ;
                        })
                        ->orderByDesc('first_exported_at')
                        ->first()
                    ;
                }

                if ($notification) {
                    $raw     = $notification->raw_payload ? json_decode($notification->raw_payload, true) : null;
                    $rawRows = is_array($raw) ? $raw : [];

                    $pickFirstNonEmpty = function (array $rows, string $key) {
                        foreach ($rows as $row) {
                            if (!is_array($row)) {
                                continue;
                            }
                            $value = $row[$key] ?? null;
                            if (null !== $value && '' !== $value) {
                                return $value;
                            }
                        }

                        return null;
                    };

                    $patronId = $pickFirstNonEmpty($rawRows, 'patron_id')      ?? $notification->patron_id;
                    $barcode  = $pickFirstNonEmpty($rawRows, 'patron_barcode') ?? $notification->patron_barcode;
                    $first    = $pickFirstNonEmpty($rawRows, 'patron_first_name');
                    $last     = $pickFirstNonEmpty($rawRows, 'patron_last_name');

                    if ($patronId || $barcode || $first || $last) {
                        $patron = (object) [
                            'patron_id'         => $patronId,
                            'patron_barcode'    => $barcode,
                            'patron_first_name' => $first,
                            'patron_last_name'  => $last,
                        ];
                    }
                }
            }
        }

        // Final fallback: parse barcode and name from free-form failure_details
        if (!$patron && !empty($failedString->failure_details)) {
            $parsed = $this->parsePatronFromFailureDetails($failedString);
            if ($parsed) {
                $patron = (object) $parsed;

                // If we only derived a barcode (common for Shoutbomb invalid phone lines),
                // look up the patron in raw Polaris exports to fill names/patron_id.
                if (
                    $patron
                    && (empty($patron->patron_first_name) && empty($patron->patron_last_name))
                    && (!empty($patron->patron_barcode) || !empty($patron->patron_id))
                ) {
                    $lookup = $this->findPatronByIdOrBarcode(
                        !empty($patron->patron_id) ? (int) $patron->patron_id : null,
                        !empty($patron->patron_barcode) ? (string) $patron->patron_barcode : null
                    );

                    if ($lookup) {
                        $patron->patron_id         = $lookup->patron_id         ?? $patron->patron_id;
                        $patron->patron_barcode    = $lookup->patron_barcode    ?? $patron->patron_barcode;
                        $patron->patron_first_name = $lookup->patron_first_name ?? $patron->patron_first_name;
                        $patron->patron_last_name  = $lookup->patron_last_name  ?? $patron->patron_last_name;
                    }
                }
            }
        }

        // If no patron found from data sources but we have a manual mapping from a
        // resolved contact, synthesize a lightweight patron object from it.
        if (!$patron && (
            !empty($failedString->resolved_patron_barcode)
            || !empty($failedString->resolved_patron_first_name)
            || !empty($failedString->resolved_patron_last_name)
        )) {
            $patron = (object) [
                'patron_id'         => null,
                'patron_barcode'    => $failedString->resolved_patron_barcode    ?? null,
                'patron_first_name' => $failedString->resolved_patron_first_name ?? null,
                'patron_last_name'  => $failedString->resolved_patron_last_name  ?? null,
            ];
        }

        $failedString->patron = $patron;

        if ($patron) {
            // Convenience attributes used by Blade views for summary display
            $failedString->example_patron_barcode    = $patron->patron_barcode    ?? null;
            $failedString->example_patron_first_name = $patron->patron_first_name ?? null;
            $failedString->example_patron_last_name  = $patron->patron_last_name  ?? null;
        }

        // If a manual mapping was recorded when the contact was resolved, let it
        // override the example patron data used for display. This ensures that
        // barcodes/names typed in the modal (e.g. for 5555098844) are what staff
        // see in Failed Contacts tables and cards.
        if (
            !empty($failedString->resolved_patron_barcode)
            || !empty($failedString->resolved_patron_first_name)
            || !empty($failedString->resolved_patron_last_name)
        ) {
            if (!empty($failedString->resolved_patron_barcode)) {
                $failedString->example_patron_barcode = $failedString->resolved_patron_barcode;
            }
            if (!empty($failedString->resolved_patron_first_name)) {
                $failedString->example_patron_first_name = $failedString->resolved_patron_first_name;
            }
            if (!empty($failedString->resolved_patron_last_name)) {
                $failedString->example_patron_last_name = $failedString->resolved_patron_last_name;
            }
        }
    }

    /**
     * Attempt to derive a patron barcode and name from a free-form failure_details string.
     *
     * Handles Shoutbomb-style summary lines such as:
     *   "5555312217 | 20000443473270| Daviess County Public Library| PATRON, SAMPLE | Renew item message"
     */
    protected function parsePatronFromFailureDetails(FailedDeliveryString $failedString): ?array
    {
        $details = $failedString->failure_details ?? '';

        if (!is_string($details)) {
            return null;
        }

        if (false !== strpos($details, '|')) {
            $parts = array_map('trim', explode('|', $details));
        } elseif (false !== strpos($details, '::')) {
            $parts = preg_split('/\s*::\s*/', $details) ?: [];
            $parts = array_map('trim', $parts);
        } else {
            return null;
        }

        $deliveryString = trim((string) $failedString->delivery_string);

        $patronId = null;
        $barcode  = null;
        $last     = null;
        $first    = null;

        foreach ($parts as $part) {
            if ('' === $part) {
                continue;
            }

            // Look for a numeric segment that is not the delivery string itself and
            // is longer than a typical 10-digit phone number (e.g., 14-digit barcodes)
            if (null === $barcode && ctype_digit($part) && $part !== $deliveryString && strlen($part) >= 11) {
                $barcode = $part;

                continue;
            }

            // Some Shoutbomb exports include a Polaris numeric patron_id alongside the barcode.
            // Example: "5555614987 :: 20000153320963 :: 185486 :: 3 :: SMS"
            if (
                null === $patronId
                && ctype_digit($part)
                && $part !== $deliveryString
                && ($barcode ? $part !== $barcode : true)
                && strlen($part) >= 4
                && strlen($part) <= 10
            ) {
                $patronId = (int) $part;

                continue;
            }

            // Look for a LAST, FIRST style name segment
            if (null === $last && false !== strpos($part, ',')) {
                [$lastCandidate, $firstCandidate] = array_map('trim', explode(',', $part, 2));

                if ('' !== $lastCandidate || '' !== $firstCandidate) {
                    $last  = $lastCandidate ?: null;
                    $first = $firstCandidate ?: null;
                }
            }
        }

        if (!$barcode && !$patronId && !$last && !$first) {
            return null;
        }

        return [
            'patron_id'         => $patronId ?: null,
            'patron_barcode'    => $barcode,
            'patron_first_name' => $first,
            'patron_last_name'  => $last,
        ];
    }
}
