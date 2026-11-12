<?php

namespace Dcplibrary\Notices\Tests\Unit;

use Dcplibrary\Notices\Support\DeliveryMethodHelper;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DeliveryMethodHelper.
 *
 * Verifies correct classification of delivery methods and proper constant definitions.
 */
class DeliveryMethodHelperTest extends TestCase
{
    /**
     * Test that electronic delivery methods are correctly identified.
     */
    public function test_electronic_delivery_identification(): void
    {
        // Electronic methods should return true
        $this->assertTrue(DeliveryMethodHelper::isElectronicDelivery(DeliveryMethodHelper::EMAIL));
        $this->assertTrue(DeliveryMethodHelper::isElectronicDelivery(DeliveryMethodHelper::SMS));
        $this->assertTrue(DeliveryMethodHelper::isElectronicDelivery(DeliveryMethodHelper::VOICE));

        // Mail should return false
        $this->assertFalse(DeliveryMethodHelper::isElectronicDelivery(DeliveryMethodHelper::MAIL));

        // Null should return false
        $this->assertFalse(DeliveryMethodHelper::isElectronicDelivery(null));

        // Unknown ID should return false
        $this->assertFalse(DeliveryMethodHelper::isElectronicDelivery(999));
    }

    /**
     * Test that manual delivery methods are correctly identified.
     */
    public function test_manual_delivery_identification(): void
    {
        // Mail should return true
        $this->assertTrue(DeliveryMethodHelper::isManualDelivery(DeliveryMethodHelper::MAIL));

        // Electronic methods should return false
        $this->assertFalse(DeliveryMethodHelper::isManualDelivery(DeliveryMethodHelper::EMAIL));
        $this->assertFalse(DeliveryMethodHelper::isManualDelivery(DeliveryMethodHelper::SMS));
        $this->assertFalse(DeliveryMethodHelper::isManualDelivery(DeliveryMethodHelper::VOICE));

        // Null should return false
        $this->assertFalse(DeliveryMethodHelper::isManualDelivery(null));

        // Unknown ID should return false
        $this->assertFalse(DeliveryMethodHelper::isManualDelivery(999));
    }

    /**
     * Test that delivery method names are correct.
     */
    public function test_delivery_method_names(): void
    {
        $this->assertEquals('Mail', DeliveryMethodHelper::getName(DeliveryMethodHelper::MAIL));
        $this->assertEquals('Email', DeliveryMethodHelper::getName(DeliveryMethodHelper::EMAIL));
        $this->assertEquals('Voice', DeliveryMethodHelper::getName(DeliveryMethodHelper::VOICE));
        $this->assertEquals('SMS', DeliveryMethodHelper::getName(DeliveryMethodHelper::SMS));
        $this->assertEquals('Unknown', DeliveryMethodHelper::getName(null));
        $this->assertEquals('Unknown', DeliveryMethodHelper::getName(999));
    }

    /**
     * Test that icon types are correct.
     */
    public function test_icon_types(): void
    {
        $this->assertEquals('mail', DeliveryMethodHelper::getIconType(DeliveryMethodHelper::MAIL));
        $this->assertEquals('email', DeliveryMethodHelper::getIconType(DeliveryMethodHelper::EMAIL));
        $this->assertEquals('voice', DeliveryMethodHelper::getIconType(DeliveryMethodHelper::VOICE));
        $this->assertEquals('sms', DeliveryMethodHelper::getIconType(DeliveryMethodHelper::SMS));
        $this->assertEquals('unknown', DeliveryMethodHelper::getIconType(null));
        $this->assertEquals('unknown', DeliveryMethodHelper::getIconType(999));
    }

    /**
     * Test that constant values match Polaris DeliveryOptionID values.
     */
    public function test_constant_values_match_polaris(): void
    {
        // These values MUST match Polaris database values
        $this->assertEquals(1, DeliveryMethodHelper::MAIL, 'MAIL constant must equal 1 (Polaris value)');
        $this->assertEquals(2, DeliveryMethodHelper::EMAIL, 'EMAIL constant must equal 2 (Polaris value)');
        $this->assertEquals(3, DeliveryMethodHelper::VOICE, 'VOICE constant must equal 3 (Polaris value)');
        $this->assertEquals(8, DeliveryMethodHelper::SMS, 'SMS constant must equal 8 (Polaris value)');
    }

    /**
     * Test that ELECTRONIC_DELIVERY_METHODS array is correct.
     */
    public function test_electronic_delivery_methods_array(): void
    {
        $expected = [
            DeliveryMethodHelper::EMAIL,
            DeliveryMethodHelper::VOICE,
            DeliveryMethodHelper::SMS,
        ];

        $this->assertEquals($expected, DeliveryMethodHelper::ELECTRONIC_DELIVERY_METHODS);
        $this->assertNotContains(DeliveryMethodHelper::MAIL, DeliveryMethodHelper::ELECTRONIC_DELIVERY_METHODS, 'Mail should NOT be in electronic methods');
    }

    /**
     * Test that NON_LX_STARTER_METHODS array is correct.
     */
    public function test_non_lx_starter_methods_array(): void
    {
        $expected = [DeliveryMethodHelper::MAIL];

        $this->assertEquals($expected, DeliveryMethodHelper::NON_LX_STARTER_METHODS);
        $this->assertContains(DeliveryMethodHelper::MAIL, DeliveryMethodHelper::NON_LX_STARTER_METHODS, 'Mail should be in non-LX-Starter methods');
    }
}
