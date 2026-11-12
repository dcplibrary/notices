<?php

namespace Dcplibrary\Notices\Services;

use Dcplibrary\Notices\Models\FailedDeliveryString;
use Dcplibrary\Notices\Models\Note;
use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\PatronDeliveryIssue;

class IssueResolutionService
{
    /**
     * Resolve a single delivery issue and attach a resolution note.
     */
    public function resolveIssue(PatronDeliveryIssue $issue, string $resolutionNotes): bool
    {
        $attributes = $this->buildResolutionAttributes($resolutionNotes);

        $updated = $issue->update($attributes);

        if ($updated) {
            $this->addResolutionNote($issue, $resolutionNotes, 'single');
        }

        return $updated;
    }

    /**
     * Resolve an aggregated failed delivery string (FailedDeliveryString).
     *
     * Marks the failed contact as resolved, optionally resolves any linked
     * PatronDeliveryIssue rows, and records a Note for audit history.
     */
    public function resolveFailedDeliveryString(
        FailedDeliveryString $failedString,
        ?string $patronBarcode,
        ?string $patronFirstName,
        ?string $patronLastName,
        ?string $resolutionType,
        string $resolutionNotes
    ): bool {
        // Resolve any open patron issues tied to this failed delivery string
        $attributes = $this->buildResolutionAttributes($resolutionNotes);

        $issueQuery = PatronDeliveryIssue::where('failed_delivery_string_id', $failedString->id)
            ->whereNull('resolved_at')
        ;

        if ($patronBarcode) {
            $issueQuery->where('patron_barcode', $patronBarcode);
        }

        $issues = $issueQuery->get();

        foreach ($issues as $issue) {
            if ($issue->update($attributes)) {
                $this->addResolutionNote($issue, $resolutionNotes, 'failed_contact');
            }
        }

        // Mark the aggregate failed contact as resolved, including staff attribution
        $failedString->is_resolved = true;
        $failedString->resolved_at = $attributes['resolved_at'] ?? now();

        // FailedDeliveryString does not have resolved_by_* casts by default,
        // but the columns exist; assign when present in attributes
        if (array_key_exists('resolved_by_user_id', $attributes)) {
            $failedString->resolved_by_user_id = $attributes['resolved_by_user_id'];
        }
        if (array_key_exists('resolved_by_name', $attributes)) {
            $failedString->resolved_by_name = $attributes['resolved_by_name'];
        }

        // Persist any manually-entered patron mapping so it can be shown on lists
        if ($patronBarcode) {
            $failedString->resolved_patron_barcode = $patronBarcode;
        }
        if ($patronFirstName) {
            $failedString->resolved_patron_first_name = $patronFirstName;
        }
        if ($patronLastName) {
            $failedString->resolved_patron_last_name = $patronLastName;
        }

        $failedString->resolution_notes = $resolutionNotes;

        $saved = $failedString->save();

        // Attach a Note directly to the FailedDeliveryString for quick reference
        $user = auth()->user();

        Note::create([
            'subject_type' => FailedDeliveryString::class,
            'subject_id'   => $failedString->id,
            'author_type'  => $user ? get_class($user) : null,
            'author_id'    => $user ? $user->getAuthIdentifier() : null,
            'category'     => 'resolution',
            'body'         => $resolutionNotes,
            'is_private'   => false,
            'meta'         => array_filter([
                'issue_type'        => 'failed_delivery_string',
                'context'           => 'failed_contact',
                'resolution_type'   => $resolutionType,
                'patron_barcode'    => $patronBarcode,
                'patron_first_name' => $patronFirstName,
                'patron_last_name'  => $patronLastName,
            ]),
        ]);

        // Log to user activity so admins can see who resolved this and when
        /** @var UserActivityLogger $activityLogger */
        $activityLogger = app(UserActivityLogger::class);
        $activityLogger->log(
            $user ? $user->getAuthIdentifier() : null,
            'resolve_failed_contact',
            'Resolved failed contact ' . $failedString->delivery_string,
            now(),
            [
                'failed_delivery_string_id' => $failedString->id,
                'delivery_option_id'        => $failedString->delivery_option_id,
                'resolution_type'           => $resolutionType,
                'patron_barcode'            => $patronBarcode,
            ]
        );

        return $saved;
    }

    /**
     * Bulk resolve issues by notification IDs.
     *
     * Creates a resolution note for each affected issue so that
     * there is an audit trail even when changes are made in bulk.
     *
     * @return int Number of issues resolved
     */
    public function bulkResolveByNotifications(array $notificationIds, string $resolutionNotes): int
    {
        $attributes = $this->buildResolutionAttributes($resolutionNotes);

        $issues = PatronDeliveryIssue::whereIn('notification_id', $notificationIds)
            ->whereNull('resolved_at')
            ->get()
        ;

        $count = 0;

        foreach ($issues as $issue) {
            if ($issue->update($attributes)) {
                ++$count;
                $this->addResolutionNote($issue, $resolutionNotes, 'bulk_notifications');
            }
        }

        return $count;
    }

    /**
     * Bulk resolve issues by time window.
     *
     * @param string $startTime Time in H:i format (e.g., "08:03")
     * @param string $endTime   Time in H:i format (e.g., "08:06")
     * @param string $date      Date in Y-m-d format
     *
     * @return int Number of issues resolved
     */
    public function bulkResolveByTimeWindow(string $startTime, string $endTime, string $date, string $resolutionNotes): int
    {
        $windowStart = new \DateTimeImmutable($date . ' ' . $startTime . ':00');
        $windowEnd   = new \DateTimeImmutable($date . ' ' . $endTime . ':59');

        // Find notification IDs exported in this time window
        $notificationIds = Notification::query()
            ->where('first_exported_at', '>=', $windowStart)
            ->where('first_exported_at', '<=', $windowEnd)
            ->pluck('id')
            ->toArray()
        ;

        if (empty($notificationIds)) {
            return 0;
        }

        return $this->bulkResolveByNotifications($notificationIds, $resolutionNotes);
    }

    /**
     * Bulk resolve issues for a specific patron.
     *
     * @return int Number of issues resolved
     */
    public function bulkResolveByPatron(string $patronBarcode, string $resolutionNotes): int
    {
        $attributes = $this->buildResolutionAttributes($resolutionNotes);

        $issues = PatronDeliveryIssue::where('patron_barcode', $patronBarcode)
            ->whereNull('resolved_at')
            ->get()
        ;

        $count = 0;

        foreach ($issues as $issue) {
            if ($issue->update($attributes)) {
                ++$count;
                $this->addResolutionNote($issue, $resolutionNotes, 'bulk_patron');
            }
        }

        return $count;
    }

    /**
     * Build the common attribute payload for resolving issues, including staff attribution.
     *
     * @param string $resolutionNotes Human-readable notes describing the resolution
     *
     * @return array<string, mixed>
     */
    protected function buildResolutionAttributes(string $resolutionNotes): array
    {
        $user           = auth()->user();
        $resolvedById   = $user ? $user->getAuthIdentifier() : null;
        $resolvedByName = null;

        if ($user) {
            // Prefer explicit name attribute when available; fall back to email/identifier
            $resolvedByName = $user->name
                ?? ($user->email ?? null)
                ?? (method_exists($user, 'getAuthIdentifierName') ? $user->getAuthIdentifierName() : null);
        }

        return [
            'resolved_at'         => now(),
            'resolution_notes'    => $resolutionNotes,
            'resolved_by_user_id' => $resolvedById,
            'resolved_by_name'    => $resolvedByName,
        ];
    }

    /**
     * Attach a free-floating Note to an issue when it is resolved.
     *
     * Notes provide a richer, auditable history than the single
     * resolution_notes field on PatronDeliveryIssue and can be
     * filtered or exported independently.
     */
    protected function addResolutionNote(PatronDeliveryIssue $issue, string $resolutionNotes, string $context = 'manual'): void
    {
        $user = auth()->user();

        Note::create([
            'subject_type' => PatronDeliveryIssue::class,
            'subject_id'   => $issue->id,
            'author_type'  => $user ? get_class($user) : null,
            'author_id'    => $user ? $user->getAuthIdentifier() : null,
            'category'     => 'resolution',
            'body'         => $resolutionNotes,
            'is_private'   => false,
            'meta'         => [
                'issue_type' => $issue->issue_type,
                'context'    => $context,
            ],
        ]);
    }
}
