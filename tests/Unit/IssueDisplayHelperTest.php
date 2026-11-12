<?php

namespace Dcplibrary\Notices\Tests\Unit;

use Dcplibrary\Notices\Models\FailedDeliveryString;
use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\PatronDeliveryIssue;
use Dcplibrary\Notices\Support\IssueDisplayHelper;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Unit tests for IssueDisplayHelper.
 *
 * Verifies correct extraction and formatting of issue display data.
 */
class IssueDisplayHelperTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test issue descriptions for all issue types.
     */
    public function test_issue_descriptions(): void
    {
        // Test failed_delivery_string with actual failed string
        $failedString = FailedDeliveryString::create([
            'delivery_string' => 'bad@email.com',
            'delivery_option_id' => 2,
            'first_failed_at' => now(),
            'last_failed_at' => now(),
            'failure_count' => 1,
            'failure_source' => 'test',
        ]);

        $issue = new PatronDeliveryIssue([
            'issue_type' => 'failed_delivery_string',
            'failed_delivery_string_id' => $failedString->id,
        ]);
        $issue->setRelation('failedDeliveryString', $failedString);

        $description = IssueDisplayHelper::getIssueDescription($issue);
        $this->assertEquals('Failed contact: bad@email.com', $description);

        // Test failed_delivery_string without failed string
        $issue = new PatronDeliveryIssue(['issue_type' => 'failed_delivery_string']);
        $description = IssueDisplayHelper::getIssueDescription($issue);
        $this->assertEquals('Invalid contact information', $description);

        // Test lx_starter_failure
        $issue = new PatronDeliveryIssue(['issue_type' => 'lx_starter_failure']);
        $description = IssueDisplayHelper::getIssueDescription($issue);
        $this->assertEquals('Not delivered to patron', $description);

        // Test null_preference
        $issue = new PatronDeliveryIssue(['issue_type' => 'null_preference']);
        $description = IssueDisplayHelper::getIssueDescription($issue);
        $this->assertEquals('No delivery preference set', $description);

        // Test manual_review
        $issue = new PatronDeliveryIssue(['issue_type' => 'manual_review']);
        $description = IssueDisplayHelper::getIssueDescription($issue);
        $this->assertEquals('Requires manual review', $description);

        // Test system_error
        $issue = new PatronDeliveryIssue(['issue_type' => 'system_error']);
        $description = IssueDisplayHelper::getIssueDescription($issue);
        $this->assertEquals('System processing error', $description);

        // Test custom issue_details override
        $issue = new PatronDeliveryIssue([
            'issue_type' => 'system_error',
            'issue_details' => 'Custom error message',
        ]);
        $description = IssueDisplayHelper::getIssueDescription($issue);
        $this->assertEquals('System processing error', $description); // Match expression takes precedence

        // Test unknown issue type
        $issue = new PatronDeliveryIssue(['issue_type' => 'unknown_type']);
        $description = IssueDisplayHelper::getIssueDescription($issue);
        $this->assertEquals('Unknown type', $description); // Formatted from issue_type (ucwords + str_replace)
    }

    /**
     * Test patron name extraction from notification payload.
     */
    public function test_patron_name_extraction(): void
    {
        $notification = Notification::create([
            'patron_id' => 1001,
            'patron_barcode' => '20000001001',
            'notification_type_id' => 2,
            'delivery_option_id' => 2,
            'status' => 'pending',
            'raw_payload' => json_encode([[
                'patron_first_name' => 'John',
                'patron_last_name' => 'Doe',
                'item_barcode' => '30000001001',
                'title' => 'Test Book',
            ]]),
        ]);

        $issue = new PatronDeliveryIssue();
        $issue->setRelation('notification', $notification);

        $name = IssueDisplayHelper::getPatronName($issue);

        $this->assertEquals('John', $name['first_name']);
        $this->assertEquals('Doe', $name['last_name']);

        // Test formatted name
        $formatted = IssueDisplayHelper::getFormattedPatronName($issue);
        $this->assertEquals('Doe, John', $formatted);
    }

    /**
     * Test patron name extraction with missing data.
     */
    public function test_patron_name_extraction_with_missing_data(): void
    {
        // Test with no notification
        $issue = new PatronDeliveryIssue();
        $name = IssueDisplayHelper::getPatronName($issue);

        $this->assertEquals('', $name['first_name']);
        $this->assertEquals('', $name['last_name']);
        $this->assertEquals('', IssueDisplayHelper::getFormattedPatronName($issue));

        // Test with empty payload
        $notification = Notification::create([
            'patron_id' => 1002,
            'patron_barcode' => '20000001002',
            'notification_type_id' => 2,
            'delivery_option_id' => 2,
            'status' => 'pending',
            'raw_payload' => json_encode([[]]),
        ]);

        $issue->setRelation('notification', $notification);
        $name = IssueDisplayHelper::getPatronName($issue);

        $this->assertEquals('', $name['first_name']);
        $this->assertEquals('', $name['last_name']);
    }

    /**
     * Test item information extraction from notification payload.
     */
    public function test_item_info_extraction(): void
    {
        $notification = Notification::create([
            'patron_id' => 1003,
            'patron_barcode' => '20000001003',
            'notification_type_id' => 2,
            'delivery_option_id' => 2,
            'status' => 'pending',
            'raw_payload' => json_encode([[
                'patron_first_name' => 'John',
                'patron_last_name' => 'Doe',
                'item_barcode' => '30000001001',
                'title' => 'The Great Gatsby',
            ]]),
        ]);

        $issue = new PatronDeliveryIssue();
        $issue->setRelation('notification', $notification);

        $itemInfo = IssueDisplayHelper::getItemInfo($issue);

        $this->assertEquals('The Great Gatsby', $itemInfo['title']);
        $this->assertEquals('30000001001', $itemInfo['barcode']);
    }

    /**
     * Test item info extraction with missing data.
     */
    public function test_item_info_extraction_with_missing_data(): void
    {
        // Test with no notification
        $issue = new PatronDeliveryIssue();
        $itemInfo = IssueDisplayHelper::getItemInfo($issue);

        $this->assertEquals('', $itemInfo['title']);
        $this->assertEquals('', $itemInfo['barcode']);

        // Test with empty payload
        $notification = Notification::create([
            'patron_id' => 1004,
            'patron_barcode' => '20000001004',
            'notification_type_id' => 2,
            'delivery_option_id' => 2,
            'status' => 'pending',
            'raw_payload' => json_encode([[]]),
        ]);

        $issue->setRelation('notification', $notification);
        $itemInfo = IssueDisplayHelper::getItemInfo($issue);

        $this->assertEquals('', $itemInfo['title']);
        $this->assertEquals('', $itemInfo['barcode']);
    }

    /**
     * Test extraction with multiple items in payload.
     */
    public function test_extraction_with_multiple_items(): void
    {
        // Payload with multiple items - should extract from first item
        $notification = Notification::create([
            'patron_id' => 1005,
            'patron_barcode' => '20000001005',
            'notification_type_id' => 2,
            'delivery_option_id' => 2,
            'status' => 'pending',
            'raw_payload' => json_encode([
                [
                    'patron_first_name' => 'John',
                    'patron_last_name' => 'Doe',
                    'item_barcode' => '30000001001',
                    'title' => 'Book 1',
                ],
                [
                    'patron_first_name' => 'John',
                    'patron_last_name' => 'Doe',
                    'item_barcode' => '30000001002',
                    'title' => 'Book 2',
                ],
            ]),
        ]);

        $issue = new PatronDeliveryIssue();
        $issue->setRelation('notification', $notification);

        // Should extract from first item
        $itemInfo = IssueDisplayHelper::getItemInfo($issue);
        $this->assertEquals('Book 1', $itemInfo['title']);
        $this->assertEquals('30000001001', $itemInfo['barcode']);

        // Patron name should be same from first item
        $name = IssueDisplayHelper::getPatronName($issue);
        $this->assertEquals('John', $name['first_name']);
        $this->assertEquals('Doe', $name['last_name']);
    }

    /**
     * Test phone number extraction from issue details.
     */
    public function test_extract_phone_from_details(): void
    {
        // Test with valid phone number
        $details = 'Barcode 20000001001 is set to Phone 1, but newer barcode(s) 20000001002 on phone 5555312217 are set to TXT Messaging.';
        $phone = IssueDisplayHelper::extractPhoneFromDetails($details);
        $this->assertEquals('5555312217', $phone);

        // Test with no phone number
        $details = 'Barcode 20000001001 is set to Phone 1.';
        $phone = IssueDisplayHelper::extractPhoneFromDetails($details);
        $this->assertNull($phone);

        // Test with null
        $phone = IssueDisplayHelper::extractPhoneFromDetails(null);
        $this->assertNull($phone);

        // Test with empty string
        $phone = IssueDisplayHelper::extractPhoneFromDetails('');
        $this->assertNull($phone);

        // Test with phone at different positions
        $details = 'phone 1234567890 is invalid';
        $phone = IssueDisplayHelper::extractPhoneFromDetails($details);
        $this->assertEquals('1234567890', $phone);
    }
}
