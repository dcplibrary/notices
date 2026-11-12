<?php

namespace Dcplibrary\Notices\Support;

/**
 * Delivery method helpers for Polaris DeliveryOptionID values.
 *
 * These constants MUST match Polaris DeliveryOptionID values.
 */
final class DeliveryMethodHelper
{
    public const MAIL  = 1;
    public const EMAIL = 2;
    public const VOICE = 3;
    public const SMS   = 8;

    /**
     * Delivery methods that are considered "electronic" for failure workflows.
     *
     * @var array<int>
     */
    public const ELECTRONIC_DELIVERY_METHODS = [
        self::EMAIL,
        self::VOICE,
        self::SMS,
    ];

    /**
     * Delivery methods that should never be treated as LX Starter-related.
     *
     * @var array<int>
     */
    public const NON_LX_STARTER_METHODS = [
        self::MAIL,
    ];

    /**
     * Determine whether the given delivery option ID uses an electronic channel.
     *
     * Electronic channels (Email, Voice, SMS) go through LX Starter or Shoutbomb
     * and generate confirmations that need to be reconciled. Mail does not.
     *
     * @param null|int $deliveryOptionId Polaris DeliveryOptionID, or null
     *
     * @return bool True for Email (2), Voice (3), and SMS (8)
     */
    public static function isElectronicDelivery(?int $deliveryOptionId): bool
    {
        return null !== $deliveryOptionId && in_array($deliveryOptionId, self::ELECTRONIC_DELIVERY_METHODS, true);
    }

    /**
     * Determine whether the given delivery option ID is physical mail.
     *
     * Mail (1) is the only manual / physical delivery method. It does not
     * generate electronic delivery confirmations.
     *
     * @param null|int $deliveryOptionId Polaris DeliveryOptionID, or null
     *
     * @return bool True only for Mail (1)
     */
    public static function isManualDelivery(?int $deliveryOptionId): bool
    {
        return self::MAIL === $deliveryOptionId;
    }

    /**
     * Get a human-readable name for the given delivery option ID.
     *
     * @param null|int $deliveryOptionId Polaris DeliveryOptionID, or null
     *
     * @return string One of "Mail", "Email", "Voice", "SMS", or "Unknown"
     */
    public static function getName(?int $deliveryOptionId): string
    {
        return match ($deliveryOptionId) {
            self::MAIL  => 'Mail',
            self::EMAIL => 'Email',
            self::VOICE => 'Voice',
            self::SMS   => 'SMS',
            default     => 'Unknown',
        };
    }

    /**
     * Get the icon type identifier for the given delivery option ID.
     *
     * Returns a semantic string used by Blade views to render the appropriate
     * delivery-method icon (see the x-notices::delivery-method-icon component).
     *
     * @param null|int $deliveryOptionId Polaris DeliveryOptionID, or null
     *
     * @return string One of "mail", "email", "voice", "sms", or "unknown"
     */
    public static function getIconType(?int $deliveryOptionId): string
    {
        return match ($deliveryOptionId) {
            self::MAIL  => 'mail',
            self::EMAIL => 'email',
            self::VOICE => 'voice',
            self::SMS   => 'sms',
            default     => 'unknown',
        };
    }
}
