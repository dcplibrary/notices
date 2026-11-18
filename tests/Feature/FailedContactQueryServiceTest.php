<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Dcplibrary\Notices\Models\FailedDeliveryString;
use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Services\FailedContactQueryService;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Verify failed-contact enrichment preserves legacy matches without needless payload scans.
 *
 * @internal
 */
#[CoversNothing]
class FailedContactQueryServiceTest extends TestCase
{
    /**
     * Fully indexed channel data must not trigger a legacy payload search.
     */
    #[DataProvider('contacts')]
    public function testSkipsPayloadSearchWhenChannelHasNoLegacyRows(int $channel, string $contact, string $column): void
    {
        $this->createNotification($channel, [$column => 'another-contact']);
        // Unindexed rows in another channel must not enable this channel's fallback.
        $this->createNotification(2 === $channel ? 3 : 2);

        [$result, $queries] = $this->lookupContact($channel, $contact);

        $this->assertNull($result->patron);
        $this->assertCount(0, $queries->filter(fn (string $sql): bool => str_contains($sql, ' like ')));
        $guards = $queries->filter(fn (string $sql): bool => str_contains($sql, 'exists('));
        $this->assertCount(1, $guards);
        $this->assertStringContainsString($column, $guards->first());
        $this->assertStringNotContainsString('order by', $guards->first());
    }

    /**
     * Both legacy payload and failure-reason matches must remain available.
     */
    #[DataProvider('contacts')]
    public function testPreservesLegacyMatches(int $channel, string $contact, string $column): void
    {
        foreach (['raw_payload', 'failure_reason'] as $matchColumn) {
            Notification::query()->delete();
            $attributes               = [$column => null];
            $attributes[$matchColumn] = 'raw_payload' === $matchColumn
                ? json_encode([['patron_first_name' => 'Example', 'contact' => $contact]])
                : 'Delivery failed for ' . $contact;
            $this->createNotification($channel, $attributes);

            [$result, $queries] = $this->lookupContact($channel, $contact);

            $this->assertSame('20000000000001', $result->patron->patron_barcode);
            $this->assertCount(1, $queries->filter(fn (string $sql): bool => str_contains($sql, ' like ')));
        }
    }

    /**
     * An indexed match must bypass both the legacy guard and payload search.
     */
    #[DataProvider('contacts')]
    public function testPrefersIndexedMatches(int $channel, string $contact, string $column): void
    {
        $this->createNotification($channel, [$column => $contact]);
        $this->createNotification($channel, [$column => null]);

        [$result, $queries] = $this->lookupContact($channel, $contact);

        $this->assertSame('20000000000001', $result->patron->patron_barcode);
        $this->assertCount(1, $queries);
        $this->assertStringContainsString($column, $queries->first());
        $this->assertStringNotContainsString(' like ', $queries->first());
    }

    /**
     * Supply synthetic contacts for each channel using notification fallbacks.
     *
     * @return array<string, array{int, string, string}>
     */
    public static function contacts(): array
    {
        return [
            'email' => [2, 'patron@example.test', 'indexed_patron_email'],
            'voice' => [3, '2025550100', 'indexed_patron_phone'],
            'sms'   => [8, '2025550100', 'indexed_patron_phone'],
        ];
    }

    /**
     * Create a synthetic notification without raw Polaris export rows.
     *
     * @param int                  $channel    Polaris delivery option ID
     * @param array<string, mixed> $attributes Notification attribute overrides
     */
    private function createNotification(int $channel, array $attributes = []): Notification
    {
        return Notification::factory()->create(array_merge([
            'delivery_option_id' => $channel,
            'patron_barcode'     => '20000000000001',
        ], $attributes));
    }

    /**
     * Run the public enrichment path and capture only its notification queries.
     *
     * @return array{FailedDeliveryString, \Illuminate\Support\Collection<int, string>}
     */
    private function lookupContact(int $channel, string $contact): array
    {
        $failedString = FailedDeliveryString::create([
            'delivery_option_id' => $channel,
            'delivery_string'    => $contact,
            'failure_source'     => 'shoutbomb_invalid_phone',
            'first_failed_at'    => now(),
            'last_failed_at'     => now(),
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $result  = app(FailedContactQueryService::class)->getFailedDeliveryString($failedString->id);
            $queries = collect(DB::getQueryLog())
                ->pluck('query')
                ->filter(fn (string $sql): bool => str_contains($sql, 'from "notifications"') || str_contains($sql, 'from `notifications`'))
                ->values()
            ;
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $failedString->delete();

        return [$result, $queries];
    }
}
