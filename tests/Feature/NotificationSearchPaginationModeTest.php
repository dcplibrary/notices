<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Services\NotificationService;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use PHPUnit\Framework\Attributes\Test;

class NotificationSearchPaginationModeTest extends TestCase
{
    protected NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NotificationService::class);
    }

    #[Test]
    public function it_uses_simple_pagination_without_totals_when_disabled(): void
    {
        $this->seedNotifications(3);

        $notifications = $this->service->searchNotifications(
            [],
            'first_exported_at',
            'desc',
            2,
            false
        );

        $this->assertNotInstanceOf(LengthAwarePaginatorContract::class, $notifications);
        $this->assertSame(2, $notifications->perPage());
        $this->assertTrue($notifications->hasMorePages());

        $first = $notifications->items()[0];
        $attrs = $first->getAttributes();

        $this->assertArrayNotHasKey('raw_payload', $attrs);
    }

    #[Test]
    public function it_uses_length_aware_pagination_when_totals_enabled(): void
    {
        $this->seedNotifications(3);

        $notifications = $this->service->searchNotifications(
            [],
            'first_exported_at',
            'desc',
            2,
            true
        );

        $this->assertInstanceOf(LengthAwarePaginatorContract::class, $notifications);
        $this->assertSame(3, $notifications->total());
        $this->assertSame(2, $notifications->perPage());
    }

    protected function seedNotifications(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Notification::factory()->create([
                'patron_barcode'    => '2330705000' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'status'            => 'pending',
                'notification_type' => 'hold',
                'first_exported_at' => now()->subMinutes($i),
                'raw_payload'       => json_encode([
                    [
                        'title'             => 'Long payload title ' . $i,
                        'patron_first_name' => 'First' . $i,
                        'patron_last_name'  => 'Last' . $i,
                    ],
                ]),
            ]);
        }
    }
}
