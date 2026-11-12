<?php

namespace Dcplibrary\Notices\Services;

use Dcplibrary\Notices\Models\DeliveryOption;
use Dcplibrary\Notices\Models\NotificationStatus;
use Dcplibrary\Notices\Models\NotificationType;
use Illuminate\Support\Facades\Cache;

/**
 * Service for resolving display labels from lookup tables.
 *
 * Handles mapping of various string representations to their proper
 * display labels with custom overrides applied.
 */
class LookupLabelService
{
    /**
     * Get display label for a delivery method.
     *
     * Maps common string representations to delivery_option_id,
     * then returns the display_label with any custom overrides applied.
     *
     * @param int|string $deliveryMethod String like "email", "voice" or delivery_option_id
     *
     * @return string Display label or original value if not found
     */
    public function getDeliveryLabel($deliveryMethod): string
    {
        if (empty($deliveryMethod)) {
            return '';
        }

        // If it's already a numeric ID, just look it up
        if (is_numeric($deliveryMethod)) {
            return $this->getDeliveryLabelById((int) $deliveryMethod);
        }

        // Map common parser strings to delivery_option_id
        $mapping = [
            'email'           => 2,
            'mail'            => 1,
            'mailing address' => 1,
            'voice'           => 3,
            'phone'           => 3,
            'phone 1'         => 3,
            'phonevoice1'     => 3,
            'sms'             => 8,
            'txt'             => 8,
            'txt messaging'   => 8,
            'text'            => 8,
            'fax'             => 6,
            'edi'             => 7,
        ];

        $deliveryOptionId = $mapping[strtolower($deliveryMethod)] ?? null;

        if ($deliveryOptionId) {
            return $this->getDeliveryLabelById($deliveryOptionId);
        }

        // Return original value if no mapping found
        return $deliveryMethod;
    }

    /**
     * Get display label for a notification type.
     *
     * @param int|string $notificationType String name or notification_type_id
     *
     * @return string Display label or original value if not found
     */
    public function getNotificationTypeLabel($notificationType): string
    {
        if (empty($notificationType)) {
            return '';
        }

        // If it's already a numeric ID, just look it up
        if (is_numeric($notificationType)) {
            return $this->getNotificationTypeLabelById((int) $notificationType);
        }

        // Map common strings to notification_type_id
        $mapping = [
            'hold'              => 2,
            'hold pickup'       => 2,
            'overdue'           => 1,
            '1st overdue'       => 1,
            '2nd overdue'       => 8,
            '3rd overdue'       => 11,
            'bill'              => 12,
            'final bill'        => 12,
            'collection'        => 13,
            'collection agency' => 13,
            'renewal'           => 7,
            'almost overdue'    => 7,
            'card expiration'   => 14,
        ];

        $notificationTypeId = $mapping[strtolower($notificationType)] ?? null;

        if ($notificationTypeId) {
            return $this->getNotificationTypeLabelById($notificationTypeId);
        }

        return $notificationType;
    }

    /**
     * Get display label for a notification status.
     *
     * @param int|string $notificationStatus String name or notification_status_id
     *
     * @return string Display label or original value if not found
     */
    public function getNotificationStatusLabel($notificationStatus): string
    {
        if (empty($notificationStatus)) {
            return '';
        }

        // If it's already a numeric ID, just look it up
        if (is_numeric($notificationStatus)) {
            return $this->getNotificationStatusLabelById((int) $notificationStatus);
        }

        // Map common strings to notification_status_id
        $mapping = [
            'pending'   => 1,
            'sent'      => 2,
            'delivered' => 3,
            'failed'    => 4,
            'bounced'   => 5,
        ];

        $notificationStatusId = $mapping[strtolower($notificationStatus)] ?? null;

        if ($notificationStatusId) {
            return $this->getNotificationStatusLabelById($notificationStatusId);
        }

        return $notificationStatus;
    }

    /**
     * Clear all cached lookup labels.
     */
    public function clearCache(): void
    {
        Cache::forget('delivery_option_label_*');
        Cache::forget('notification_type_label_*');
        Cache::forget('notification_status_label_*');
    }

    /**
     * Get delivery label by ID with caching.
     */
    protected function getDeliveryLabelById(int $id): string
    {
        return Cache::remember("delivery_option_label_{$id}", 3600, function () use ($id) {
            $deliveryOption = DeliveryOption::find($id);

            return $deliveryOption?->display_label ?? "Unknown ({$id})";
        });
    }

    /**
     * Get notification type label by ID with caching.
     */
    protected function getNotificationTypeLabelById(int $id): string
    {
        return Cache::remember("notification_type_label_{$id}", 3600, function () use ($id) {
            $notificationType = NotificationType::find($id);

            return $notificationType?->display_label ?? "Unknown ({$id})";
        });
    }

    /**
     * Get notification status label by ID with caching.
     */
    protected function getNotificationStatusLabelById(int $id): string
    {
        return Cache::remember("notification_status_label_{$id}", 3600, function () use ($id) {
            $notificationStatus = NotificationStatus::find($id);

            return $notificationStatus?->display_label ?? "Unknown ({$id})";
        });
    }
}
