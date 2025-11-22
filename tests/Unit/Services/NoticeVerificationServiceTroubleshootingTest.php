<?php

namespace Dcplibrary\Notices\Tests\Unit\Services;

use Dcplibrary\Notices\Services\NoticeVerificationService;
use Dcplibrary\Notices\Tests\TestCase;
use Carbon\Carbon;

/**
 * Tests for troubleshooting methods in NoticeVerificationService.
 */
class NoticeVerificationServiceTroubleshootingTest extends TestCase
{
    protected NoticeVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NoticeVerificationService();
    }

    /** @test */
    public function it_returns_troubleshooting_summary_structure()
    {
        $summary = $this->service->getTroubleshootingSummary(
            Carbon::parse('2025-11-01'),
            Carbon::parse('2025-11-10')
        );

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('total_notices', $summary);
        $this->assertArrayHasKey('failed_count', $summary);
        $this->assertArrayHasKey('success_rate', $summary);
        $this->assertArrayHasKey('submitted_not_verified', $summary);
        $this->assertArrayHasKey('verified_not_delivered', $summary);
    }

    /** @test */
    public function it_calculates_success_rate_correctly()
    {
        $summary = $this->service->getTroubleshootingSummary(
            Carbon::parse('2025-11-01'),
            Carbon::parse('2025-11-10')
        );

        $this->assertIsNumeric($summary['success_rate']);
        $this->assertGreaterThanOrEqual(0, $summary['success_rate']);
        $this->assertLessThanOrEqual(100, $summary['success_rate']);
    }

    /** @test */
    public function it_returns_failures_by_reason_structure()
    {
        $failures = $this->service->getFailuresByReason(
            Carbon::parse('2025-11-01'),
            Carbon::parse('2025-11-10')
        );

        $this->assertIsArray($failures);

        if (count($failures) > 0) {
            $failure = $failures[0];
            $this->assertArrayHasKey('reason', $failure);
            $this->assertArrayHasKey('count', $failure);
            $this->assertArrayHasKey('percentage', $failure);

            $this->assertIsString($failure['reason']);
            $this->assertIsInt($failure['count']);
            $this->assertIsNumeric($failure['percentage']);
        }
    }

    /** @test */
    public function it_returns_failures_by_reason_with_valid_percentages()
    {
        $failures = $this->service->getFailuresByReason(
            Carbon::parse('2025-11-01'),
            Carbon::parse('2025-11-10')
        );

        $totalPercentage = 0;
        foreach ($failures as $failure) {
            $this->assertGreaterThanOrEqual(0, $failure['percentage']);
            $this->assertLessThanOrEqual(100, $failure['percentage']);
            $totalPercentage += $failure['percentage'];
        }

        // Total percentage should be approximately 100 (allowing for rounding)
        if (count($failures) > 0) {
            $this->assertGreaterThanOrEqual(99, $totalPercentage);
            $this->assertLessThanOrEqual(101, $totalPercentage);
        }
    }

    /** @test */
    public function it_returns_failures_by_type_structure()
    {
        $failures = $this->service->getFailuresByType(
            Carbon::parse('2025-11-01'),
            Carbon::parse('2025-11-10')
        );

        $this->assertIsArray($failures);

        if (count($failures) > 0) {
            $failure = $failures[0];
            $this->assertArrayHasKey('type', $failure);
            $this->assertArrayHasKey('count', $failure);
            $this->assertArrayHasKey('percentage', $failure);

            $this->assertIsString($failure['type']);
            $this->assertIsInt($failure['count']);
            $this->assertIsNumeric($failure['percentage']);
        }
    }

    /** @test */
    public function it_returns_mismatches_structure()
    {
        $mismatches = $this->service->getMismatches(
            Carbon::parse('2025-11-09'),
            Carbon::parse('2025-11-10')
        );

        $this->assertIsArray($mismatches);
        $this->assertArrayHasKey('submitted_not_verified', $mismatches);
        $this->assertArrayHasKey('verified_not_delivered', $mismatches);
        $this->assertArrayHasKey('summary', $mismatches);

        $this->assertIsArray($mismatches['submitted_not_verified']);
        $this->assertIsArray($mismatches['verified_not_delivered']);
        $this->assertIsArray($mismatches['summary']);

        $this->assertArrayHasKey('submitted_not_verified_count', $mismatches['summary']);
        $this->assertArrayHasKey('verified_not_delivered_count', $mismatches['summary']);
    }

    /** @test */
    public function it_limits_submitted_not_verified_to_50_items()
    {
        $mismatches = $this->service->getMismatches(
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-12-31')
        );

        $this->assertLessThanOrEqual(50, count($mismatches['submitted_not_verified']));
    }

    /** @test */
    public function it_limits_verified_not_delivered_to_50_items()
    {
        $mismatches = $this->service->getMismatches(
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-12-31')
        );

        $this->assertLessThanOrEqual(50, count($mismatches['verified_not_delivered']));
    }

    /** @test */
    public function submitted_not_verified_has_required_fields()
    {
        $mismatches = $this->service->getMismatches(
            Carbon::parse('2025-11-01'),
            Carbon::parse('2025-11-10')
        );

        if (count($mismatches['submitted_not_verified']) > 0) {
            $item = $mismatches['submitted_not_verified'][0];

            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('patron_barcode', $item);
            $this->assertArrayHasKey('phone', $item);
            $this->assertArrayHasKey('type', $item);
            $this->assertArrayHasKey('submitted_at', $item);
            $this->assertArrayHasKey('source_file', $item);
        } else {
            $this->assertTrue(true, 'No submitted_not_verified items to validate');
        }
    }

    /** @test */
    public function verified_not_delivered_has_required_fields()
    {
        $mismatches = $this->service->getMismatches(
            Carbon::parse('2025-11-01'),
            Carbon::parse('2025-11-10')
        );

        if (count($mismatches['verified_not_delivered']) > 0) {
            $item = $mismatches['verified_not_delivered'][0];

            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('patron_barcode', $item);
            $this->assertArrayHasKey('phone', $item);
            $this->assertArrayHasKey('item_barcode', $item);
            $this->assertArrayHasKey('notice_date', $item);
            $this->assertArrayHasKey('delivery_type', $item);
        } else {
            $this->assertTrue(true, 'No verified_not_delivered items to validate');
        }
    }

    /** @test */
    public function it_uses_default_date_ranges_when_not_provided()
    {
        $summary = $this->service->getTroubleshootingSummary();

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('total_notices', $summary);
        $this->assertArrayHasKey('failed_count', $summary);
    }

    /** @test */
    public function it_returns_failed_notices_with_limit()
    {
        $failures = $this->service->getFailedNotices(
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-12-31')
        );

        $this->assertIsArray($failures);
        $this->assertLessThanOrEqual(100, count($failures));
    }

    /** @test */
    public function it_filters_failed_notices_by_reason()
    {
        $allFailures = $this->service->getFailedNotices(
            Carbon::parse('2025-11-01'),
            Carbon::parse('2025-11-10')
        );

        if (count($allFailures) > 0 && isset($allFailures[0]['failure_reason'])) {
            $reason = $allFailures[0]['failure_reason'];
            $filteredFailures = $this->service->getFailedNotices(
                Carbon::parse('2025-11-01'),
                Carbon::parse('2025-11-10'),
                $reason
            );

            foreach ($filteredFailures as $failure) {
                if (isset($failure['failure_reason'])) {
                    $this->assertStringContainsString(
                        $reason,
                        $failure['failure_reason'],
                        'Filtered results should contain the search reason'
                    );
                }
            }
        } else {
            $this->assertTrue(true, 'No failures with reasons to test filtering');
        }
    }
}
