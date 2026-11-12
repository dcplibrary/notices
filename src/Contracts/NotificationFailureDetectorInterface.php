<?php

namespace Dcplibrary\Notices\Contracts;

/**
 * Contract for detecting notification failures and at-risk patrons.
 */
interface NotificationFailureDetectorInterface
{
    /**
     * Detect failed or missing voice/SMS notifications for the given date.
     *
     * @param \DateTimeInterface $date date window to inspect for failures
     *
     * @return array failure records keyed by patron or notification
     */
    public function detectFailures(\DateTimeInterface $date): array;

    /**
     * Detect patrons whose invalid phone numbers will block future notices until fixed.
     *
     * @param \DateTimeInterface $date date window for analyzing invalid phone reports
     *
     * @return array list of patrons with invalid phone numbers and related metadata
     */
    public function detectInvalidPhoneNumbers(\DateTimeInterface $date): array;

    /**
     * Detect failed or missing email notifications using Polaris Summary Report.
     *
     * This method correlates email notifications with the Polaris Summary Report
     * to identify:
     * - Notifications that were successfully delivered (confirmed by report)
     * - Notifications that failed (reported as "failed to:")
     * - Polaris system failures ("Email Failed" with no email address)
     * - Soft bounces or missing emails that need investigation (no status update, absent from report)
     *
     * @param \DateTimeInterface $date date window to inspect for email failures
     *
     * @return array email failure/success records
     */
    public function detectEmailFailures(\DateTimeInterface $date): array;
}
