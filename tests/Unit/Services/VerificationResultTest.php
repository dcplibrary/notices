<?php

namespace Dcplibrary\Notices\Tests\Unit\Services;

use Dcplibrary\Notices\Services\VerificationResult;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class VerificationResultTest extends TestCase
{
    /** @test */
    public function it_can_be_instantiated_with_default_values()
    {
        $result = new VerificationResult();

        $this->assertFalse($result->created);
        $this->assertFalse($result->submitted);
        $this->assertFalse($result->verified);
        $this->assertFalse($result->delivered);
        $this->assertNull($result->created_at);
        $this->assertNull($result->submitted_at);
        $this->assertNull($result->verified_at);
        $this->assertNull($result->delivered_at);
    }

    /** @test */
    public function it_can_be_instantiated_with_data()
    {
        $now = Carbon::now();
        $result = new VerificationResult([
            'created' => true,
            'created_at' => $now,
            'submitted' => true,
            'submitted_at' => $now->copy()->addMinutes(5),
        ]);

        $this->assertTrue($result->created);
        $this->assertTrue($result->submitted);
        $this->assertEquals($now, $result->created_at);
        $this->assertEquals($now->copy()->addMinutes(5), $result->submitted_at);
    }

    /** @test */
    public function it_determines_success_status_when_delivered()
    {
        $result = new VerificationResult([
            'created' => true,
            'submitted' => true,
            'verified' => true,
            'delivered' => true,
            'delivery_status' => 'Delivered',
        ]);

        $this->assertEquals('success', $result->overall_status);
    }

    /** @test */
    public function it_determines_failed_status_when_delivery_fails()
    {
        $result = new VerificationResult([
            'created' => true,
            'submitted' => true,
            'verified' => true,
            'delivered' => true,
            'delivery_status' => 'Failed',
            'failure_reason' => 'Invalid phone number',
        ]);

        $this->assertEquals('failed', $result->overall_status);
    }

    /** @test */
    public function it_determines_pending_status_when_not_submitted()
    {
        $result = new VerificationResult([
            'created' => true,
            'submitted' => false,
        ]);

        $this->assertEquals('pending', $result->overall_status);
    }

    /** @test */
    public function it_determines_partial_status_when_submitted_but_not_delivered()
    {
        $result = new VerificationResult([
            'created' => true,
            'submitted' => true,
            'verified' => false,
            'delivered' => false,
        ]);

        $this->assertEquals('partial', $result->overall_status);
    }

    /** @test */
    public function it_can_add_timeline_events()
    {
        $result = new VerificationResult();
        $timestamp = Carbon::now();

        $result->addTimelineEvent('created', $timestamp, 'notification_logs', [
            'id' => 123,
        ]);

        $this->assertCount(1, $result->timeline);
        $this->assertEquals('created', $result->timeline[0]['step']);
        $this->assertEquals($timestamp->toISOString(), $result->timeline[0]['timestamp']);
        $this->assertEquals('notification_logs', $result->timeline[0]['source']);
        $this->assertEquals(123, $result->timeline[0]['details']['id']);
    }

    /** @test */
    public function it_can_add_multiple_timeline_events()
    {
        $result = new VerificationResult();
        $now = Carbon::now();

        $result->addTimelineEvent('created', $now, 'notification_logs');
        $result->addTimelineEvent('submitted', $now->copy()->addMinutes(2), 'shoutbomb_submissions');
        $result->addTimelineEvent('verified', $now->copy()->addMinutes(3), 'polaris_phone_notices');
        $result->addTimelineEvent('delivered', $now->copy()->addMinutes(5), 'shoutbomb_deliveries');

        $this->assertCount(4, $result->timeline);
        $this->assertEquals('created', $result->timeline[0]['step']);
        $this->assertEquals('submitted', $result->timeline[1]['step']);
        $this->assertEquals('verified', $result->timeline[2]['step']);
        $this->assertEquals('delivered', $result->timeline[3]['step']);
    }

    /** @test */
    public function it_converts_to_array_correctly()
    {
        $now = Carbon::now();
        $result = new VerificationResult([
            'created' => true,
            'created_at' => $now,
            'submitted' => true,
            'submitted_at' => $now->copy()->addMinutes(2),
            'verified' => true,
            'verified_at' => $now->copy()->addMinutes(3),
            'delivered' => true,
            'delivered_at' => $now->copy()->addMinutes(5),
            'delivery_status' => 'Delivered',
            'submission_file' => 'holds_submitted.txt',
            'verification_file' => 'PhoneNotices.csv',
        ]);

        $array = $result->toArray();

        $this->assertArrayHasKey('verification', $array);
        $this->assertArrayHasKey('timestamps', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('files', $array);
        $this->assertArrayHasKey('timeline', $array);

        $this->assertTrue($array['verification']['created']);
        $this->assertTrue($array['verification']['submitted']);
        $this->assertTrue($array['verification']['verified']);
        $this->assertTrue($array['verification']['delivered']);
        $this->assertEquals('success', $array['verification']['overall_status']);

        $this->assertEquals('Delivered', $array['status']['delivery_status']);
        $this->assertEquals('holds_submitted.txt', $array['files']['submission_file']);
        $this->assertEquals('PhoneNotices.csv', $array['files']['verification_file']);
    }

    /** @test */
    public function it_provides_human_readable_status_messages()
    {
        $successResult = new VerificationResult([
            'created' => true,
            'submitted' => true,
            'verified' => true,
            'delivered' => true,
            'delivery_status' => 'Delivered',
        ]);

        $failedResult = new VerificationResult([
            'created' => true,
            'submitted' => true,
            'verified' => true,
            'delivered' => true,
            'delivery_status' => 'Failed',
            'failure_reason' => 'Invalid phone number',
        ]);

        $pendingResult = new VerificationResult([
            'created' => true,
            'submitted' => false,
        ]);

        $partialResult = new VerificationResult([
            'created' => true,
            'submitted' => true,
            'delivered' => false,
        ]);

        $this->assertStringContainsString('✅', $successResult->getStatusMessage());
        $this->assertStringContainsString('delivered successfully', $successResult->getStatusMessage());

        $this->assertStringContainsString('❌', $failedResult->getStatusMessage());
        $this->assertStringContainsString('failed', $failedResult->getStatusMessage());
        $this->assertStringContainsString('Invalid phone number', $failedResult->getStatusMessage());

        $this->assertStringContainsString('⏳', $pendingResult->getStatusMessage());
        $this->assertStringContainsString('not yet submitted', $pendingResult->getStatusMessage());

        $this->assertStringContainsString('🔄', $partialResult->getStatusMessage());
        $this->assertStringContainsString('delivery pending', $partialResult->getStatusMessage());
    }

    /** @test */
    public function it_handles_null_timestamps_in_timeline()
    {
        $result = new VerificationResult();

        $result->addTimelineEvent('created', null, 'notification_logs');

        $this->assertCount(1, $result->timeline);
        $this->assertNull($result->timeline[0]['timestamp']);
    }

    /** @test */
    public function it_handles_empty_details_in_timeline()
    {
        $result = new VerificationResult();
        $now = Carbon::now();

        $result->addTimelineEvent('created', $now, 'notification_logs');

        $this->assertCount(1, $result->timeline);
        $this->assertEmpty($result->timeline[0]['details']);
    }
}
