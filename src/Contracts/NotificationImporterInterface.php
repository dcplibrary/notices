<?php

namespace Dcplibrary\Notices\Contracts;

/**
 * Contract for importing raw notification-related data from external systems
 * (FTP exports, CSV/TXT files, etc.) into local storage.
 */
interface NotificationImporterInterface
{
    /**
     * Import Shoutbomb exports (holds, renewals, overdues) for the given date.
     *
     * @param \DateTimeInterface $date date represented in the export file names
     */
    public function importShoutbombExports(\DateTimeInterface $date): void;

    /**
     * Import Polaris PhoneNotices exports used to verify Shoutbomb exports.
     *
     * @param \DateTimeInterface $date date represented in the PhoneNotices export
     */
    public function importPolarisPhoneNotices(\DateTimeInterface $date): void;

    /**
     * Import NotificationLog exports (mail/email source of truth).
     *
     * @param \DateTimeInterface $date date represented in the NotificationLog export
     */
    public function importNotificationLog(\DateTimeInterface $date): void;

    /**
     * Import NotificationHistory exports (additional context on notifications).
     *
     * @param \DateTimeInterface $date date represented in the NotificationHistory export
     */
    public function importNotificationHistory(\DateTimeInterface $date): void;
}
