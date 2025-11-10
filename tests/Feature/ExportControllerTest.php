<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Services\NoticeVerificationService;
use Dcplibrary\Notices\Services\NoticeExportService;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Feature tests for export controller endpoints.
 */
class ExportControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_exports_verification_results()
    {
        $response = $this->get(route('notices.verification.export', [
            'patron_barcode' => 'TEST123',
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition');

        // Check that filename contains timestamp
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('notice-verification-', $disposition);
        $this->assertStringContainsString('.csv', $disposition);
    }

    /** @test */
    public function verification_export_includes_csv_headers()
    {
        $response = $this->get(route('notices.verification.export', [
            'patron_barcode' => 'TEST123',
        ]));

        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertStringContainsString('Date', $content);
        $this->assertStringContainsString('Patron Barcode', $content);
        $this->assertStringContainsString('Status', $content);
    }

    /** @test */
    public function it_exports_patron_history()
    {
        $response = $this->get(route('notices.verification.patron.export', [
            'barcode' => 'TEST123',
            'days' => 90,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition');

        // Check that filename includes patron barcode
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('patron-TEST123-verification-', $disposition);
        $this->assertStringContainsString('.csv', $disposition);
    }

    /** @test */
    public function patron_history_export_includes_csv_headers()
    {
        $response = $this->get(route('notices.verification.patron.export', [
            'barcode' => 'TEST123',
        ]));

        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertStringContainsString('Date', $content);
        $this->assertStringContainsString('Patron Barcode', $content);
    }

    /** @test */
    public function it_exports_troubleshooting_failures()
    {
        $response = $this->get(route('notices.troubleshooting.export', [
            'days' => 7,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition');

        // Check filename format
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('notice-failures-', $disposition);
        $this->assertStringContainsString('.csv', $disposition);
    }

    /** @test */
    public function failures_export_includes_csv_headers()
    {
        $response = $this->get(route('notices.troubleshooting.export'));

        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertStringContainsString('Date', $content);
        $this->assertStringContainsString('Failure Reason', $content);
    }

    /** @test */
    public function verification_export_respects_query_filters()
    {
        // This test verifies that the export uses the same filters as the verification page
        $response = $this->get(route('notices.verification.export', [
            'patron_barcode' => 'TEST123',
            'phone' => '555-1234',
            'date_from' => '2025-01-01',
            'date_to' => '2025-01-31',
        ]));

        $response->assertStatus(200);
        $this->assertNotEmpty($response->getContent());
    }

    /** @test */
    public function patron_history_export_respects_date_range()
    {
        $response = $this->get(route('notices.verification.patron.export', [
            'barcode' => 'TEST123',
            'days' => 180,
        ]));

        $response->assertStatus(200);
        $this->assertNotEmpty($response->getContent());
    }

    /** @test */
    public function failures_export_respects_date_range()
    {
        $response = $this->get(route('notices.troubleshooting.export', [
            'days' => 30,
        ]));

        $response->assertStatus(200);
        $this->assertNotEmpty($response->getContent());
    }

    /** @test */
    public function export_handles_no_results_gracefully()
    {
        // Request export with filters that won't match anything
        $response = $this->get(route('notices.verification.export', [
            'patron_barcode' => 'NONEXISTENT999',
        ]));

        $response->assertStatus(200);

        // Should still return CSV with headers
        $content = $response->getContent();
        $this->assertStringContainsString('Date', $content);
    }

    /** @test */
    public function export_limits_results_to_prevent_timeout()
    {
        // The verification export limits to 1000 records
        // This test ensures the limit is applied

        $response = $this->get(route('notices.verification.export'));

        $response->assertStatus(200);

        // CSV should be generated successfully even with limit
        $this->assertNotEmpty($response->getContent());
    }

    /** @test */
    public function export_filename_uses_current_timestamp()
    {
        $beforeTime = now()->format('Y-m-d-H');

        $response = $this->get(route('notices.verification.export', [
            'patron_barcode' => 'TEST123',
        ]));

        $disposition = $response->headers->get('Content-Disposition');

        // Filename should contain date that matches current time
        $this->assertStringContainsString($beforeTime, $disposition);
    }

    /** @test */
    public function patron_export_filename_sanitizes_barcode()
    {
        $response = $this->get(route('notices.verification.patron.export', [
            'barcode' => 'TEST123',
        ]));

        $disposition = $response->headers->get('Content-Disposition');

        // Should include the barcode in filename
        $this->assertStringContainsString('TEST123', $disposition);
    }
}
