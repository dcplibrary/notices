<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Dcplibrary\Notices\Models\PatronDeliveryIssue;
use Dcplibrary\Notices\Services\NotificationService;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use PHPUnit\Framework\Attributes\Test;

class IssueSearchPaginationModeTest extends TestCase
{
    protected NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NotificationService::class);
    }

    #[Test]
    public function it_uses_simple_pagination_and_lean_columns_when_totals_are_disabled(): void
    {
        $this->seedIssues(3);

        $issues = $this->service->searchIssues(
            null,
            [],
            'detected_at',
            'desc',
            false,
            2,
            false
        );

        $this->assertNotInstanceOf(LengthAwarePaginatorContract::class, $issues);
        $this->assertSame(2, $issues->perPage());
        $this->assertTrue($issues->hasMorePages());

        $first = $issues->items()[0];
        $attrs = $first->getAttributes();

        $this->assertArrayNotHasKey('issue_details', $attrs);
        $this->assertArrayNotHasKey('resolution_notes', $attrs);
    }

    #[Test]
    public function it_uses_length_aware_pagination_when_totals_are_enabled(): void
    {
        $this->seedIssues(3);

        $issues = $this->service->searchIssues(
            null,
            [],
            'detected_at',
            'desc',
            false,
            2,
            true
        );

        $this->assertInstanceOf(LengthAwarePaginatorContract::class, $issues);
        $this->assertSame(3, $issues->total());
        $this->assertSame(2, $issues->perPage());
    }

    protected function seedIssues(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            PatronDeliveryIssue::create([
                'issue_type'       => 'manual_review',
                'patron_id'        => 1000 + $i,
                'patron_barcode'   => '2330700000' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'issue_details'    => 'Issue details #' . $i,
                'resolution_notes' => 'Resolution notes #' . $i,
                'detected_at'      => now()->subMinutes($i),
                'resolved_at'      => null,
            ]);
        }
    }
}
