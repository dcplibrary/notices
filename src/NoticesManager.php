<?php

namespace Dcplibrary\Notices;

use Dcplibrary\Notices\Contracts\EmailIngestionInterface;
use Dcplibrary\Notices\Contracts\NotificationFailureDetectorInterface;
use Dcplibrary\Notices\Contracts\NotificationImporterInterface;
use Dcplibrary\Notices\Contracts\NotificationReconcilerInterface;

/**
 * High-level entrypoint for working with the notices domain.
 *
 * This class orchestrates imports, reconciliation, deduplication, and
 * failure detection across all delivery options (Mail, Email, Voice, SMS).
 */
class NoticesManager
{
    /**
     * Create a new NoticesManager instance.
     *
     * @param NotificationImporterInterface        $importer        service responsible for importing raw exports (FTP/CSV/TXT)
     * @param EmailIngestionInterface              $emailIngestion  service that ingests failure/summary emails via Microsoft Graph
     * @param NotificationReconcilerInterface      $reconciler      service that deduplicates and reconciles notifications
     * @param NotificationFailureDetectorInterface $failureDetector service that detects notification failures and at-risk patrons
     */
    public function __construct(
        protected NotificationImporterInterface $importer,
        protected EmailIngestionInterface $emailIngestion,
        protected NotificationReconcilerInterface $reconciler,
        protected NotificationFailureDetectorInterface $failureDetector,
    ) {}

    /**
     * Run the full daily import (all sources, reconcile, failures) for a
     * single logical date. Prefer runImportForDateRange() for new code.
     */
    public function runDailyPipeline(?\DateTimeInterface $date = null): void
    {
        $date ??= new \DateTimeImmutable('today');

        $this->runImportForDateRange(
            new \DateTimeImmutable($date->format('Y-m-d')),
            new \DateTimeImmutable($date->format('Y-m-d')),
        );
    }

    /**
     * Run the full notices import across a date range, optionally reporting
     * progress to a sink that can render console output or UI events.
     */
    public function runImportForDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Support\Progress\ProgressSink $sink = null): void
    {
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        $current = $from;
        while ($current <= $to) {
            $sink?->startDay($current);

            // Import raw data from all sources
            $sink?->step('ftp', 'pending');
            $this->importShoutbombExports($current);
            $this->importPolarisPhoneNotices($current);
            $this->importNotificationLog($current);
            $this->importNotificationHistory($current);
            $sink?->step('ftp', 'done');

            // Ingest failure and summary emails
            $sink?->step('email', 'pending');
            $this->ingestFailureAndSummaryEmails($current);
            $sink?->step('email', 'done');

            // Deduplicate and reconcile into unified notification records
            $sink?->step('reconcile', 'pending');
            $this->deduplicateNotifications($current);
            $this->reconcileStatuses($current);
            $sink?->step('reconcile', 'done');

            // Detect failures and mark successful deliveries
            $sink?->step('failures', 'pending');
            $this->detectNotificationFailures($current);
            $this->failureDetector->detectInvalidPhoneNumbers($current);
            $sink?->step('failures', 'done');

            $sink?->endDay($current);

            $current = $current->add(new \DateInterval('P1D'));
        }
    }

    /**
     * Import Shoutbomb-related exports (holds, renewals/almost overdue, overdue) from FTP.
     *
     * @param \DateTimeInterface $date date of the exports to import (typically the export date)
     */
    public function importShoutbombExports(\DateTimeInterface $date): void
    {
        $this->importer->importShoutbombExports($date);
    }

    /**
     * Import Polaris PhoneNotices exports for cross-verification.
     *
     * @param \DateTimeInterface $date date of the PhoneNotices export (usually matches Shoutbomb export date)
     */
    public function importPolarisPhoneNotices(\DateTimeInterface $date): void
    {
        $this->importer->importPolarisPhoneNotices($date);
    }

    /**
     * Import NotificationLog exports (source of truth for Mail and Email).
     *
     * @param \DateTimeInterface $date date of the NotificationLog export
     */
    public function importNotificationLog(\DateTimeInterface $date): void
    {
        $this->importer->importNotificationLog($date);
    }

    /**
     * Import NotificationHistory exports for additional context.
     *
     * @param \DateTimeInterface $date date of the NotificationHistory export
     */
    public function importNotificationHistory(\DateTimeInterface $date): void
    {
        $this->importer->importNotificationHistory($date);
    }

    /**
     * Ingest failure and summary emails from Microsoft 365 via the Graph API.
     *
     * This includes failure reports (invalid phones, undelivered calls, errors)
     * and monthly/weekly summary statistics.
     *
     * @param \DateTimeInterface $date logical date used for the ingestion window
     */
    public function ingestFailureAndSummaryEmails(\DateTimeInterface $date): void
    {
        $this->emailIngestion->ingestForDate($date);
    }

    /**
     * Deduplicate and merge notifications across exports for a given date.
     *
     * - Same patron
     * - Same time window
     * - Same delivery option
     * => counts as a single logical notification.
     *
     * @param \DateTimeInterface $date date used when grouping and writing deduplicated notifications
     */
    public function deduplicateNotifications(\DateTimeInterface $date): void
    {
        $this->reconciler->deduplicate($date);
    }

    /**
     * Reconcile notifications across all data sources and update statuses.
     *
     * @param \DateTimeInterface $date date window for reconciliation
     */
    public function reconcileStatuses(\DateTimeInterface $date): void
    {
        $this->reconciler->reconcile($date);
    }

    /**
     * Detect patrons who are not receiving expected notifications.
     *
     * @param \DateTimeInterface $date date to analyze for failures (typically yesterday)
     *
     * @return array list of failure summaries keyed by patron
     */
    public function detectNotificationFailures(\DateTimeInterface $date): array
    {
        return $this->failureDetector->detectFailures($date);
    }

    /**
     * Get a per-patron notification timeline across all delivery options.
     *
     * @param int|string $patronId polaris PatronID or barcode, depending on how timelines are keyed
     *
     * @return array chronological list of notification events for the patron
     */
    public function getPatronTimeline(int|string $patronId): array
    {
        // TODO: return chronological events (generated, exported, delivered, failed, etc.).
        return [];
    }

    /**
     * Get notifications for a specific delivery option (DeliveryOptionID) on a given date.
     *
     * @param int                $deliveryOptionId polaris DeliveryOptionID (1 Mail, 2 Email, 3 Voice, 8 SMS)
     * @param \DateTimeInterface $date             date of interest
     */
    public function getNotificationsForChannel(int $deliveryOptionId, \DateTimeInterface $date): array
    {
        // TODO: query unified notification storage.
        return [];
    }
}
