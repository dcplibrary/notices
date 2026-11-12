<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\PatronDeliveryIssue;
use Dcplibrary\Notices\Services\NotificationService;
use Dcplibrary\Notices\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class NotificationSearchMaterializedColumnsTest extends TestCase
{
    protected NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NotificationService::class);
    }

    #[Test]
    public function notification_search_uses_materialized_columns_without_raw_payload(): void
    {
        Notification::factory()->create([
            'patron_barcode'            => '20000016660001',
            'search_patron_first_name'  => 'Margaret',
            'search_patron_last_name'   => 'Blashbrook',
            'search_title'              => 'A Traitor in Whitehall',
            'search_item_barcode'       => '30000007876758',
            'raw_payload'               => null,
            'first_exported_at'         => now()->subMinute(),
        ]);

        $results = $this->service->searchNotifications([
            'search' => 'Whitehall',
        ]);

        $this->assertSame(1, $results->total());
        $this->assertSame('A Traitor in Whitehall', $results->items()[0]->search_title);
    }

    #[Test]
    public function issue_search_uses_materialized_notification_columns_without_raw_payload(): void
    {
        $notification = Notification::factory()->create([
            'patron_barcode'            => '20000016660002',
            'search_patron_first_name'  => 'Margaret',
            'search_patron_last_name'   => 'Blashbrook',
            'search_title'              => 'Library Holds Report',
            'search_item_barcode'       => '30000007870000',
            'raw_payload'               => null,
            'first_exported_at'         => now()->subMinutes(2),
        ]);

        PatronDeliveryIssue::create([
            'issue_type'      => 'manual_review',
            'patron_id'       => $notification->patron_id,
            'patron_barcode'  => $notification->patron_barcode,
            'notification_id' => $notification->id,
            'issue_details'   => 'Requires review',
            'detected_at'     => now()->subMinute(),
            'resolved_at'     => null,
        ]);

        $results = $this->service->searchIssues(
            null,
            ['search' => 'Library Holds'],
            'detected_at',
            'desc',
            false,
            50,
            true
        );

        $this->assertSame(1, $results->total());
        $this->assertSame($notification->id, $results->items()[0]->notification_id);
    }
}
