<?php

namespace Dcplibrary\Notices\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Laravel Facade for the Notices package entry point.
 *
 * Provides static proxy access to the underlying NoticesManager singleton,
 * which orchestrates imports, reconciliation, deduplication, and failure
 * detection across all delivery options (Mail, Email, Voice, SMS).
 *
 * Example usage:
 *   Notices::runDailyPipeline();
 *   Notices::importShoutbombExports(new \DateTimeImmutable('today'));
 *
 * @see \Dcplibrary\Notices\NoticesManager
 *
 * @method static void  runDailyPipeline(?\DateTimeInterface $date = null)
 * @method static void  runImportForDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to, ?\Dcplibrary\Notices\Support\Progress\ProgressSink $sink = null)
 * @method static void  importShoutbombExports(\DateTimeInterface $date)
 * @method static void  importPolarisPhoneNotices(\DateTimeInterface $date)
 * @method static void  importNotificationLog(\DateTimeInterface $date)
 * @method static void  importNotificationHistory(\DateTimeInterface $date)
 * @method static void  ingestFailureAndSummaryEmails(\DateTimeInterface $date)
 * @method static void  deduplicateNotifications(\DateTimeInterface $date)
 * @method static void  reconcileStatuses(\DateTimeInterface $date)
 * @method static array detectNotificationFailures(\DateTimeInterface $date)
 * @method static array getPatronTimeline(int|string $patronId)
 * @method static array getNotificationsForChannel(int $deliveryOptionId, \DateTimeInterface $date)
 */
class Notices extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'dcplibrary.notices';
    }
}
