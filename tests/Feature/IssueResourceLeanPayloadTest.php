<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Dcplibrary\Notices\Http\Resources\IssueResource;
use Dcplibrary\Notices\Models\FailedDeliveryString;
use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\PatronDeliveryIssue;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;

class IssueResourceLeanPayloadTest extends TestCase
{
    #[Test]
    public function it_omits_heavy_fields_by_default(): void
    {
        $issue = $this->makeIssueWithLoadedRelations();
        $request = Request::create('/api/v1/notices/issues', 'GET', ['include_details' => '0']);

        $payload = (new IssueResource($issue))->response($request)->getData(true);
        $data = $payload['data'];

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('issue_type', $data);
        $this->assertArrayNotHasKey('issue_details', $data);
        $this->assertArrayNotHasKey('resolution_notes', $data);
        $this->assertArrayNotHasKey('notification', $data);
        $this->assertArrayNotHasKey('failed_delivery_string', $data);
    }

    #[Test]
    public function it_includes_heavy_fields_when_requested(): void
    {
        $issue = $this->makeIssueWithLoadedRelations();
        $request = Request::create('/api/v1/notices/issues', 'GET', ['include_details' => '1']);

        $payload = (new IssueResource($issue))->response($request)->getData(true);
        $data = $payload['data'];

        $this->assertSame('Delivery string failed repeatedly.', $data['issue_details']);
        $this->assertSame('Resolved after patron contact update.', $data['resolution_notes']);
        $this->assertArrayHasKey('notification', $data);
        $this->assertArrayHasKey('failed_delivery_string', $data);
        $this->assertSame(42, $data['notification']['id']);
        $this->assertSame('test@example.org', $data['failed_delivery_string']['delivery_string']);
    }

    protected function makeIssueWithLoadedRelations(): PatronDeliveryIssue
    {
        $notification = new Notification([
            'id'                 => 42,
            'patron_id'          => 12345,
            'patron_barcode'     => '20000000000001',
            'notification_type'  => 'hold',
            'notification_type_id' => 2,
            'delivery_option_id' => 2,
            'status'             => 'failed',
        ]);

        $failedString = new FailedDeliveryString([
            'id'                  => 77,
            'delivery_string'     => 'test@example.org',
            'delivery_option_id'  => 2,
            'failure_source'      => 'lx_starter_failure',
            'failure_count'       => 3,
            'affected_patron_count' => 1,
            'is_resolved'         => false,
        ]);

        $issue = new PatronDeliveryIssue([
            'id'                        => 99,
            'issue_type'                => 'failed_delivery_string',
            'patron_id'                 => 12345,
            'patron_barcode'            => '20000000000001',
            'failed_delivery_string_id' => 77,
            'notification_id'           => 42,
            'issue_details'             => 'Delivery string failed repeatedly.',
            'resolution_notes'          => 'Resolved after patron contact update.',
        ]);

        $issue->setRelation('notification', $notification);
        $issue->setRelation('failedDeliveryString', $failedString);

        return $issue;
    }
}
