<?php

namespace Dcplibrary\Notices\Tests\Unit\Services;

use Dcplibrary\Notices\Services\NoticeExportService;
use Dcplibrary\Notices\Services\NoticeVerificationService;
use Dcplibrary\Notices\Services\VerificationResult;
use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Support\Collection;

/**
 * Tests for the NoticeExportService.
 */
class NoticeExportServiceTest extends TestCase
{
    protected NoticeExportService $exportService;
    protected NoticeVerificationService $verificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verificationService = $this->createMock(NoticeVerificationService::class);
        $this->exportService = new NoticeExportService($this->verificationService);
    }

    /** @test */
    public function it_exports_verification_results_to_csv()
    {
        $notices = $this->createSampleNotices();

        // Mock verification results
        $this->verificationService->method('verify')
            ->willReturn($this->createSampleVerificationResult());

        $csv = $this->exportService->exportVerificationToCSV($notices);

        $this->assertIsString($csv);
        $this->assertStringContainsString('Date', $csv);
        $this->assertStringContainsString('Patron Barcode', $csv);
        $this->assertStringContainsString('Phone', $csv);
        $this->assertStringContainsString('Email', $csv);
    }

    /** @test */
    public function verification_csv_has_correct_headers()
    {
        $notices = collect();
        $csv = $this->exportService->exportVerificationToCSV($notices);

        $lines = explode("\n", $csv);
        $headers = str_getcsv($lines[0]);

        $expectedHeaders = [
            'Date',
            'Patron Barcode',
            'Patron Name',
            'Phone',
            'Email',
            'Notification Type',
            'Delivery Method',
            'Item Barcode',
            'Title',
            'Status',
            'Created',
            'Submitted',
            'Verified',
            'Delivered',
            'Failure Reason',
        ];

        $this->assertEquals($expectedHeaders, $headers);
    }

    /** @test */
    public function verification_csv_formats_data_correctly()
    {
        $notice = $this->createSampleNotice();
        $notices = collect([$notice]);

        $this->verificationService->method('verify')
            ->willReturn($this->createSampleVerificationResult());

        $csv = $this->exportService->exportVerificationToCSV($notices);
        $lines = explode("\n", $csv);

        // Should have header + 1 data row + empty line at end
        $this->assertGreaterThanOrEqual(2, count($lines));

        // Parse the data row
        $dataRow = str_getcsv($lines[1]);

        $this->assertNotEmpty($dataRow[0]); // Date
        $this->assertEquals('TEST123', $dataRow[1]); // Patron Barcode
    }

    /** @test */
    public function it_exports_patron_history_to_csv()
    {
        $results = $this->createSamplePatronHistoryResults();

        $csv = $this->exportService->exportPatronHistoryToCSV($results);

        $this->assertIsString($csv);
        $this->assertStringContainsString('Date', $csv);
        $this->assertStringContainsString('Patron Barcode', $csv);
        $this->assertStringContainsString('Status', $csv);
    }

    /** @test */
    public function patron_history_csv_has_correct_headers()
    {
        $results = [];
        $csv = $this->exportService->exportPatronHistoryToCSV($results);

        $lines = explode("\n", $csv);
        $headers = str_getcsv($lines[0]);

        $expectedHeaders = [
            'Date',
            'Patron Barcode',
            'Phone',
            'Email',
            'Notification Type',
            'Delivery Method',
            'Item Barcode',
            'Title',
            'Status',
            'Created',
            'Submitted',
            'Verified',
            'Delivered',
            'Failure Reason',
        ];

        $this->assertEquals($expectedHeaders, $headers);
    }

    /** @test */
    public function it_exports_failures_to_csv()
    {
        $failures = $this->createSampleFailures();

        $csv = $this->exportService->exportFailuresToCSV($failures);

        $this->assertIsString($csv);
        $this->assertStringContainsString('Date', $csv);
        $this->assertStringContainsString('Patron Barcode', $csv);
        $this->assertStringContainsString('Failure Reason', $csv);
    }

    /** @test */
    public function failures_csv_has_correct_headers()
    {
        $failures = [];
        $csv = $this->exportService->exportFailuresToCSV($failures);

        $lines = explode("\n", $csv);
        $headers = str_getcsv($lines[0]);

        $expectedHeaders = [
            'Date',
            'Patron Barcode',
            'Phone Number',
            'Delivery Type',
            'Notification Type',
            'Status',
            'Failure Reason',
        ];

        $this->assertEquals($expectedHeaders, $headers);
    }

    /** @test */
    public function csv_export_handles_empty_collections()
    {
        $csv = $this->exportService->exportVerificationToCSV(collect());

        $lines = explode("\n", trim($csv));

        // Should only have header row
        $this->assertEquals(1, count($lines));
    }

    /** @test */
    public function csv_export_handles_null_values()
    {
        $notice = new NotificationLog();
        $notice->notification_date = now();
        $notice->patron_barcode = 'TEST123';
        $notice->notification_type_id = 1;
        $notice->delivery_option_id = 1;
        // Leave other fields null

        $notices = collect([$notice]);

        $this->verificationService->method('verify')
            ->willReturn($this->createSampleVerificationResult());

        $csv = $this->exportService->exportVerificationToCSV($notices);

        $this->assertIsString($csv);
        $lines = explode("\n", $csv);
        $this->assertGreaterThanOrEqual(2, count($lines));
    }

    /** @test */
    public function csv_export_escapes_special_characters()
    {
        $notice = $this->createSampleNotice();
        $notice->title = 'Book with "quotes" and, commas';
        $notice->patron_name = "O'Brien, Patrick";

        $notices = collect([$notice]);

        $this->verificationService->method('verify')
            ->willReturn($this->createSampleVerificationResult());

        $csv = $this->exportService->exportVerificationToCSV($notices);

        $this->assertIsString($csv);
        // CSV should properly escape quotes and commas
        $this->assertStringContainsString('Book with ""quotes"" and, commas', $csv);
    }

    /** @test */
    public function failures_csv_formats_data_correctly()
    {
        $failures = [
            [
                'sent_date' => now()->toDateTimeString(),
                'patron_barcode' => 'TEST123',
                'phone_number' => '555-1234',
                'delivery_type' => 'voice',
                'notification_type' => 'holds',
                'status' => 'Failed',
                'failure_reason' => 'Invalid phone number',
            ],
        ];

        $csv = $this->exportService->exportFailuresToCSV($failures);
        $lines = explode("\n", $csv);

        // Should have header + 1 data row
        $this->assertGreaterThanOrEqual(2, count($lines));

        $dataRow = str_getcsv($lines[1]);

        $this->assertEquals('TEST123', $dataRow[1]); // Patron Barcode
        $this->assertEquals('555-1234', $dataRow[2]); // Phone Number
        $this->assertEquals('voice', $dataRow[3]); // Delivery Type
    }

    /** @test */
    public function csv_export_uses_utf8_encoding()
    {
        $notice = $this->createSampleNotice();
        $notice->title = "Café René's Book © 2025";

        $notices = collect([$notice]);

        $this->verificationService->method('verify')
            ->willReturn($this->createSampleVerificationResult());

        $csv = $this->exportService->exportVerificationToCSV($notices);

        // UTF-8 BOM should be present
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Café René', $csv);
    }

    /**
     * Helper method to create sample notices.
     */
    protected function createSampleNotices(): Collection
    {
        $notices = collect();

        for ($i = 0; $i < 3; $i++) {
            $notices->push($this->createSampleNotice());
        }

        return $notices;
    }

    /**
     * Helper method to create a single sample notice.
     */
    protected function createSampleNotice(): NotificationLog
    {
        $notice = new NotificationLog();
        $notice->notification_date = now();
        $notice->patron_barcode = 'TEST123';
        $notice->patron_name = 'Test Patron';
        $notice->phone = '555-1234';
        $notice->email = 'test@example.com';
        $notice->notification_type_id = 1;
        $notice->delivery_option_id = 1;
        $notice->item_barcode = 'ITEM123';
        $notice->title = 'Test Book';

        return $notice;
    }

    /**
     * Helper method to create a sample verification result.
     */
    protected function createSampleVerificationResult(): VerificationResult
    {
        return new VerificationResult([
            'created' => true,
            'created_at' => now(),
            'submitted' => true,
            'submitted_at' => now(),
            'verified' => true,
            'verified_at' => now(),
            'delivered' => true,
            'delivered_at' => now(),
        ]);
    }

    /**
     * Helper method to create sample patron history results.
     */
    protected function createSamplePatronHistoryResults(): array
    {
        return [
            [
                'notice' => $this->createSampleNotice(),
                'verification' => $this->createSampleVerificationResult(),
            ],
        ];
    }

    /**
     * Helper method to create sample failures.
     */
    protected function createSampleFailures(): array
    {
        return [
            [
                'sent_date' => now()->toDateTimeString(),
                'patron_barcode' => 'TEST123',
                'phone_number' => '555-1234',
                'delivery_type' => 'voice',
                'notification_type' => 'holds',
                'status' => 'Failed',
                'failure_reason' => 'Invalid phone number',
            ],
        ];
    }
}
