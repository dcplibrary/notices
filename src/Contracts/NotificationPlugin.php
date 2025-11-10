<?php

namespace Dcplibrary\Notices\Contracts;

use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Services\VerificationResult;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Carbon\Carbon;

/**
 * Interface for notification channel plugins.
 *
 * Each notification channel (Shoutbomb, Email, SMS Direct, etc.) implements this interface
 * to provide channel-specific verification and statistics.
 */
interface NotificationPlugin
{
    /**
     * Get the plugin's unique identifier.
     */
    public function getName(): string;

    /**
     * Get the plugin's display name.
     */
    public function getDisplayName(): string;

    /**
     * Get the plugin's description.
     */
    public function getDescription(): string;

    /**
     * Get the delivery option IDs this plugin handles.
     *
     * @return array<int> Array of delivery_option_id values
     */
    public function getDeliveryOptionIds(): array;

    /**
     * Check if this plugin can verify a given notice.
     */
    public function canVerify(NotificationLog $log): bool;

    /**
     * Verify a notice using this plugin's verification logic.
     *
     * Plugins should check:
     * - Was it submitted to the delivery service?
     * - Was it verified/confirmed?
     * - Was it delivered?
     * - What was the delivery status?
     */
    public function verify(NotificationLog $log, VerificationResult $result): VerificationResult;

    /**
     * Get statistics for this plugin within a date range.
     *
     * @return array{
     *   total_sent: int,
     *   total_delivered: int,
     *   total_failed: int,
     *   success_rate: float,
     *   additional_stats?: array
     * }
     */
    public function getStatistics(Carbon $startDate, Carbon $endDate): array;

    /**
     * Get failed notices for this plugin.
     *
     * @return Collection<array>
     */
    public function getFailedNotices(Carbon $startDate, Carbon $endDate, ?string $reason = null): Collection;

    /**
     * Get the dashboard widget view for this plugin.
     *
     * Returns a Blade view to display on the main dashboard.
     */
    public function getDashboardWidget(Carbon $startDate, Carbon $endDate): ?View;

    /**
     * Get configuration settings for this plugin.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array;

    /**
     * Check if this plugin is enabled.
     */
    public function isEnabled(): bool;
}
