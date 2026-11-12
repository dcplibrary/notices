<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\DeliveryConfirmation;
use Dcplibrary\Notices\Models\EmailReportType;
use Dcplibrary\Notices\Models\IngestedEmail;
use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\NotificationImportBatch;
use Dcplibrary\Notices\Services\GraphEmailIngestionService;
use Dcplibrary\Notices\Services\NotificationFailureDetector;
use Dcplibrary\Notices\Services\NotificationReconciler;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

/**
 * Failed Voice/SMS notices must survive the full notices:import pipeline
 * (ingest -> reconcile -> detect failures -> integrity-check --fix) and the next
 * day's reconciliation, so they stay visible under the viewer's "Failed" filter.
 */
class ShoutbombFailedNoticesPipelineTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_phone_only_call_error_keeps_voice_notice_failed_through_pipeline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 10:00:00'));

        $failed    = $this->voiceNotification('20000000000001', '5555673101');
        $delivered = $this->voiceNotification('20000000000002', '2705550000');

        NotificationImportBatch::create([
            'source'        => 'ftp_holds',
            'date_for'      => '2026-10-27',
            'imported_at'   => Carbon::parse('2026-10-28 09:00:00'),
            'identifier'    => 'ftp_holds_2026-10-27',
            'records_count' => 2,
        ]);

        (new PipelineGraphEmailIngestionService())->ingestEmail([
            'id'               => 'MSG-calls-2026-10-27',
            'subject'          => 'Calls that generated errors Tue, October 27th 2026',
            'from'             => ['emailAddress' => ['address' => 'no_reply@shoutbomb.com']],
            'toRecipients'     => [['emailAddress' => ['address' => 'notices@example.com']]],
            'receivedDateTime' => '2026-10-27T11:02:02Z',
            'body'             => [
                'contentType' => 'text',
                'content'     => "Hello,\n\nPatron number:: 5555673101 :: Error reason  :: Destination not found :: Library number 5555078405 :: 2026-10-26 :: 18:13:07 :: \n",
            ],
            'hasAttachments'   => false,
        ]);

        $this->runPipelineFor('2026-10-26');

        $this->assertSame('failed', $failed->fresh()->status);
        $this->assertNull($failed->fresh()->delivered_at);
        $this->assertSame('delivered', $delivered->fresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-10-29 10:00:00'));
        $this->runPipelineFor('2026-10-27');

        $this->assertSame('failed', $failed->fresh()->status, 'Next-day reconciliation must not flip the call error back to delivered');
        $this->assertSame('delivered', $delivered->fresh()->status);
        $this->assertSame(1, Notification::where('status', 'failed')->count());
    }

    public function test_call_error_for_a_different_export_date_does_not_fail_newer_notice(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 10:00:00'));

        $notification = $this->voiceNotification('20000000000001', '5555673101');

        NotificationImportBatch::create([
            'source'        => 'ftp_holds',
            'date_for'      => '2026-10-27',
            'imported_at'   => Carbon::parse('2026-10-28 09:00:00'),
            'identifier'    => 'ftp_holds_2026-10-27',
            'records_count' => 1,
        ]);

        DeliveryConfirmation::create([
            'confirmation_source' => 'shoutbomb_voice_system_error',
            'delivery_option_id'  => 3,
            'notice_date'         => '2026-10-20',
            'delivery_string'     => '5555673101',
            'details'             => 'Patron number:: 5555673101 :: Error reason :: Destination not found ::',
        ]);

        IngestedEmail::create([
            'message_id'           => 'MSG-invalid-2026-10-27',
            'email_report_type_id' => EmailReportType::where('code', 'shoutbomb_invalid_phone')->value('id'),
            'subject'              => 'Invalid patron phone number Tue, October 27th 2026',
            'report_date'          => Carbon::parse('2026-10-26'),
            'parsed_record_count'  => 0,
        ]);

        $this->runPipelineFor('2026-10-26');

        $this->assertSame('delivered', $notification->fresh()->status);
    }

    public function test_voice_failure_subject_without_on_still_yields_notice_date(): void
    {
        (new PipelineGraphEmailIngestionService())->ingestEmail([
            'id'               => 'MSG-voice-no-on',
            'subject'          => 'Voice notices that were not delivered Mon, October 26th 2026',
            'from'             => ['emailAddress' => ['address' => 'no_reply@shoutbomb.com']],
            'toRecipients'     => [['emailAddress' => ['address' => 'notices@example.com']]],
            'receivedDateTime' => '2026-10-26T23:00:00Z',
            'body'             => ['contentType' => 'text', 'content' => '5555312217 | 20000001001 | Main Library | John Doe | Hold'],
            'hasAttachments'   => false,
        ]);

        $confirmation = DeliveryConfirmation::where('confirmation_source', 'shoutbomb_voice_failure')->firstOrFail();
        $this->assertSame('2026-10-25', Carbon::parse($confirmation->notice_date)->toDateString());
        $this->assertSame('20000001001', $confirmation->patron_barcode);
    }

    public function test_undated_shoutbomb_subject_falls_back_to_received_date(): void
    {
        (new PipelineGraphEmailIngestionService())->ingestEmail([
            'id'               => 'MSG-calls-undated',
            'subject'          => 'Calls that generated errors',
            'from'             => ['emailAddress' => ['address' => 'no_reply@shoutbomb.com']],
            'toRecipients'     => [['emailAddress' => ['address' => 'notices@example.com']]],
            'receivedDateTime' => '2026-10-27T11:02:02Z',
            'body'             => [
                'contentType' => 'text',
                'content'     => 'Patron number:: 5555673101 :: Error reason :: Destination not found :: Library number 5555078405 :: 2026-10-26 :: 18:13:07 ::',
            ],
            'hasAttachments'   => false,
        ]);

        $email = IngestedEmail::where('message_id', 'MSG-calls-undated')->firstOrFail();
        $this->assertSame('2026-10-26', $email->report_date->toDateString());
        $this->assertSame(
            '2026-10-26',
            Carbon::parse(DeliveryConfirmation::firstOrFail()->notice_date)->toDateString()
        );
    }

    public function test_zero_record_voice_failure_report_is_refilled_on_reimport(): void
    {
        IngestedEmail::create([
            'message_id'           => 'MSG-voice-empty',
            'email_report_type_id' => EmailReportType::where('code', 'shoutbomb_voice_failure')->value('id'),
            'subject'              => 'Voice notices that were not delivered on Mon, October 26th 2026',
            'report_date'          => Carbon::parse('2026-10-25'),
            'parsed_record_count'  => 0,
        ]);

        (new PipelineGraphEmailIngestionService())->ingestEmail([
            'id'               => 'MSG-voice-full',
            'subject'          => 'Voice notices that were not delivered on Mon, October 26th 2026',
            'from'             => ['emailAddress' => ['address' => 'no_reply@shoutbomb.com']],
            'toRecipients'     => [['emailAddress' => ['address' => 'notices@example.com']]],
            'receivedDateTime' => '2026-10-26T23:00:00Z',
            'body'             => ['contentType' => 'text', 'content' => '5555312217 | 20000001001 | Main Library | John Doe | Hold'],
            'hasAttachments'   => false,
        ]);

        $this->assertSame(1, IngestedEmail::count());
        $this->assertSame(1, IngestedEmail::firstOrFail()->parsed_record_count);
        $this->assertSame(1, DeliveryConfirmation::where('confirmation_source', 'shoutbomb_voice_failure')->count());
    }

    protected function voiceNotification(string $barcode, string $phone): Notification
    {
        return Notification::create([
            'notification_key'     => 'voice-' . $barcode,
            'patron_id'            => (int) substr($barcode, -4),
            'patron_barcode'       => $barcode,
            'notification_type'    => 'Hold',
            'delivery_option_id'   => 3,
            'status'               => 'pending',
            'generated_at'         => Carbon::parse('2026-10-26 09:05:01'),
            'first_exported_at'    => Carbon::parse('2026-10-26 09:05:01'),
            'export_filename'      => 'holds_2026-10-26_09-05-01.txt',
            'indexed_patron_phone' => $phone,
            'raw_payload'          => json_encode([]),
            'is_combined'          => false,
        ]);
    }

    protected function runPipelineFor(string $date): void
    {
        $day = Carbon::parse($date);

        app(NotificationReconciler::class)->reconcile($day);
        app(NotificationFailureDetector::class)->detectFailures($day);
        Artisan::call('notices:integrity-check', ['--days' => 30, '--fix' => true]);
    }
}

class PipelineGraphEmailIngestionService extends GraphEmailIngestionService
{
    public function ingestEmail(array $message): void
    {
        $this->processEmailMessage($message);
    }
}
