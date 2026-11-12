<?php

namespace Dcplibrary\Notices\Support;

use Dcplibrary\Notices\Models\UiMessage;
use Illuminate\Support\Str;

/**
 * Helper for deriving UI-facing failure labels for notifications.
 *
 * Centralizes the mapping from delivery option + failure reason into a
 * ui_messages key and then resolves the configured/overridden label.
 */
final class NotificationFailureLabelResolver
{
    /**
     * Derive a UI failure label for a failed notification.
     *
     * @param null|int    $deliveryOptionId Polaris DeliveryOptionID (1=Mail,2=Email,3=Voice,8=SMS)
     * @param null|string $failureReason    Raw failure reason text from notification
     */
    public static function getUiFailureLabel(?int $deliveryOptionId, ?string $failureReason): ?string
    {
        $uiFailureKey = self::getUiFailureKey($deliveryOptionId, $failureReason);

        if (!$uiFailureKey) {
            return null;
        }

        $labelsConfig = config('notices.ui_labels', []);
        $defaultLabel = $labelsConfig[$uiFailureKey] ?? Str::title(str_replace('_', ' ', $uiFailureKey));
        $override     = UiMessage::where('key', $uiFailureKey)->first();

        return $override->label ?? $defaultLabel;
    }

    /**
     * Map context to a ui_messages key (e.g. sms_opt_out, email_bounce).
     */
    public static function getUiFailureKey(?int $deliveryOptionId, ?string $failureReason): ?string
    {
        $reasonRaw = Str::lower((string) ($failureReason ?? ''));

        // System-level LX Starter failures always win regardless of method.
        if ($failureReason && str_contains($failureReason, 'LX Starter communication error')) {
            return 'lx_starter_failure';
        }

        // Voice / SMS (DeliveryOptionID 3, 8)
        if (in_array((int) $deliveryOptionId, [DeliveryMethodHelper::VOICE, DeliveryMethodHelper::SMS], true)) {
            if (str_contains($reasonRaw, 'opted out') || str_contains($reasonRaw, 'opted-out') || str_contains($reasonRaw, 'opt out')) {
                return 'sms_opt_out';
            }

            if (str_contains($reasonRaw, 'invalid') && str_contains($reasonRaw, 'phone')) {
                return 'sms_invalid_phone';
            }

            if (str_contains($reasonRaw, 'landline')) {
                return 'sms_landline';
            }

            if (str_contains($reasonRaw, 'carrier')) {
                return 'sms_carrier_issue';
            }

            return 'sms_delivery_failure';
        }

        // Email
        if (DeliveryMethodHelper::EMAIL === (int) $deliveryOptionId) {
            if (str_contains($reasonRaw, 'invalid')) {
                return 'email_invalid_address';
            }

            if (str_contains($reasonRaw, 'bounce') || str_contains($reasonRaw, 'bounced')) {
                return 'email_bounce';
            }

            return 'email_delivery_failure';
        }

        return null;
    }
}
