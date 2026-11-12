<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\DeliveryConfirmation;
use Dcplibrary\Notices\Models\EmailReportType;
use Dcplibrary\Notices\Models\IngestedEmail;
use Dcplibrary\Notices\Services\GraphEmailIngestionService;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Tests for GraphEmailIngestionService single-email ingestion behavior.
 *
 * These tests exercise the protected processEmailMessage() workflow via a
 * small test subclass that exposes a public ingestEmail(array $message)
 * shim, matching the terminology used in documentation/commands.
 */
class GraphEmailIngestionServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Lightweight test harness that exposes ingestEmail() for a single
     * Microsoft Graph message payload.
     */
    protected function makeService(): TestableGraphEmailIngestionService
    {
        return new TestableGraphEmailIngestionService();
    }

    /**
     * 1. GraphEmailIngestionService::ingestEmail skips ingestion if a report
     *    of the same type and report_date has already been ingested.
     */
    public function test_ingest_email_skips_when_structured_report_of_same_type_and_date_exists(): void
    {
        $service = $this->makeService();

        $reportType = EmailReportType::where('code', 'shoutbomb_voice_failure')->firstOrFail();

        // Existing structured report for 2026-01-04 of type shoutbomb_voice_failure
        $existingReportDate = Carbon::create(2026, 1, 4)->startOfDay();

        IngestedEmail::create([
            'message_id'           => 'existing-structured-message',
            'email_report_type_id' => $reportType->id,
            'subject'              => 'Voice notices that were not delivered on January 5th 2026',
            'body_full'            => 'Existing failure report body',
            'report_date'          => $existingReportDate,
        ]);

        $this->assertEquals(1, IngestedEmail::count(), 'Precondition: one structured report exists');

        // New message with same logical report (same type + same report_date)
        $body = '5555312217 | 20000001001 | Main Library | John Doe | Hold';

        $message = [
            'id'                => 'MSG-new-structured',
            'subject'           => 'Voice notices that were not delivered on January 5th 2026',
            'from'              => ['emailAddress' => ['address' => 'reports@example.com']],
            'toRecipients'      => [
                ['emailAddress' => ['address' => 'notices@example.com']],
            ],
            'receivedDateTime'  => '2026-01-05T08:00:00Z',
            'body'              => [
                'contentType' => 'text',
                'content'     => $body,
            ],
            'hasAttachments'    => false,
        ];

        $service->ingestEmail($message);

        // Idempotency: no additional row should be created.
        $this->assertEquals(1, IngestedEmail::count(), 'Structured report with same type+date should be skipped');
    }

    /**
     * 2. GraphEmailIngestionService::ingestEmail skips ingestion when the
     *    Microsoft Graph message ID has already been seen for an
     *    unstructured email (e.g., notice files / other).
     */
    public function test_ingest_email_skips_when_unstructured_message_id_already_exists(): void
    {
        $service = $this->makeService();

        // Existing unstructured email stored by message_id
        IngestedEmail::create([
            'message_id'           => 'MSG-unstructured-1',
            'email_report_type_id' => EmailReportType::where('code', 'notice_files')->firstOrFail()->id,
            'subject'              => 'Status of notice files for 1/2/2026',
            'body_full'            => 'Previous notice files status email',
        ]);

        $this->assertEquals(1, IngestedEmail::count(), 'Precondition: one unstructured email exists');

        // New message with same message_id but classified as unstructured
        $message = [
            'id'               => 'MSG-unstructured-1', // same ID as existing row
            'subject'          => 'Status of notice files for 1/2/2026',
            'from'             => ['emailAddress' => ['address' => 'reports@example.com']],
            'toRecipients'     => [
                ['emailAddress' => ['address' => 'notices@example.com']],
            ],
            'receivedDateTime' => '2026-01-02T07:00:00Z',
            'body'             => [
                'contentType' => 'text',
                'content'     => 'Hello, here is the status of your notice files...',
            ],
            'hasAttachments'   => false,
        ];

        $service->ingestEmail($message);

        // Unstructured emails should dedupe on message_id.
        $this->assertEquals(1, IngestedEmail::count(), 'Unstructured email with same message_id should be skipped');
    }

    /**
     * 3. GraphEmailIngestionService::ingestEmail correctly classifies and
     *    parses emails based solely on the subject line, wiring them to the
     *    appropriate report type and parser output.
     */
    public function test_ingest_email_classifies_and_parses_based_on_subject(): void
    {
        $service = $this->makeService();

        // Shoutbomb voice failure
        $voiceFailureMessage = [
            'id'               => 'MSG-voice-failure-1',
            'subject'          => 'Voice notices that were not delivered on January 5th 2026',
            'from'             => ['emailAddress' => ['address' => 'reports@example.com']],
            'toRecipients'     => [
                ['emailAddress' => ['address' => 'notices@example.com']],
            ],
            'receivedDateTime' => '2026-01-05T08:00:00Z',
            'body'             => [
                'contentType' => 'text',
                'content'     => '5555312217 | 20000001001 | Main Library | John Doe | Hold',
            ],
            'hasAttachments'   => false,
        ];

        $service->ingestEmail($voiceFailureMessage);

        $voiceEmail = IngestedEmail::where('message_id', 'MSG-voice-failure-1')->firstOrFail();
        $this->assertEquals('shoutbomb_voice_failure', $voiceEmail->emailReportType->code);
        $this->assertIsArray($voiceEmail->parsed_data);
        $this->assertEquals('shoutbomb_voice_failure', $voiceEmail->parsed_data['report_type']);
        $this->assertEquals(1, $voiceEmail->parsed_data['failures_count']);

        // Shoutbomb invalid phone
        $invalidPhoneMessage = [
            'id'               => 'MSG-invalid-phone-1',
            'subject'          => 'Invalid patron phone number Wed, January 7th 2026',
            'from'             => ['emailAddress' => ['address' => 'reports@example.com']],
            'toRecipients'     => [
                ['emailAddress' => ['address' => 'notices@example.com']],
            ],
            'receivedDateTime' => '2026-01-07T08:00:00Z',
            'body'             => [
                'contentType' => 'text',
                // No explicit section headers; parser will treat all as "invalid".
                'content'     => '5555312217 :: 20000001001 :: 123 :: 3 :: SMS',
            ],
            'hasAttachments'   => false,
        ];

        $service->ingestEmail($invalidPhoneMessage);

        $invalidEmail = IngestedEmail::where('message_id', 'MSG-invalid-phone-1')->firstOrFail();
        $this->assertEquals('shoutbomb_invalid_phone', $invalidEmail->emailReportType->code);
        $this->assertIsArray($invalidEmail->parsed_data);
        $this->assertEquals('shoutbomb_invalid_phone', $invalidEmail->parsed_data['report_type']);
        $this->assertEquals(1, $invalidEmail->parsed_data['invalid_count']);

        // Polaris summary report
        $summaryBody = <<<BODY
The following email notices were sent out from Daviess County Public Library.
Start time: 12/29/2025 9:01 AM
End time: 12/30/2025 8:01 AM

1 Hold E-mail notices sent out to:
John Doe  20000001001  john@example.com

1 Hold E-mail notices failed to:
Jane Smith  20000001002  Email Failed
BODY;

        $summaryMessage = [
            'id'               => 'MSG-summary-1',
            'subject'          => 'Summary Report - Daviess County Public Library',
            'from'             => ['emailAddress' => ['address' => 'reports@example.com']],
            'toRecipients'     => [
                ['emailAddress' => ['address' => 'notices@example.com']],
            ],
            'receivedDateTime' => '2025-12-30T08:05:00Z',
            'body'             => [
                'contentType' => 'text',
                'content'     => $summaryBody,
            ],
            'hasAttachments'   => false,
        ];

        $service->ingestEmail($summaryMessage);

        $summaryEmail = IngestedEmail::where('message_id', 'MSG-summary-1')->firstOrFail();
        $this->assertEquals('polaris_summary_report', $summaryEmail->emailReportType->code);
        $this->assertIsArray($summaryEmail->parsed_data);
        $this->assertEquals('polaris_summary_report', $summaryEmail->parsed_data['report_type']);
        $this->assertEquals(1, $summaryEmail->parsed_data['total_sent']);
        $this->assertEquals(1, $summaryEmail->parsed_data['total_failed']);
    }

    /**
     * 4. GraphEmailIngestionService::ingestEmail calculates parsed_record_count
     *    correctly based on the shape of the parsed report.
     */
    public function test_ingest_email_sets_parsed_record_count_from_parsed_report(): void
    {
        $service = $this->makeService();

        // Polaris summary report: total_sent + total_failed
        $summaryBody = <<<BODY
The following email notices were sent out from Daviess County Public Library.
Start time: 12/29/2025 9:01 AM
End time: 12/30/2025 8:01 AM

2 Hold E-mail notices sent out to:
John Doe  20000001001  john@example.com
Jane Smith  20000001002  jane@example.com

1 Hold E-mail notices failed to:
Bob Brown  20000001003  Email Failed
BODY;

        $summaryMessage = [
            'id'               => 'MSG-summary-count',
            'subject'          => 'Summary Report - Daviess County Public Library',
            'from'             => ['emailAddress' => ['address' => 'reports@example.com']],
            'toRecipients'     => [
                ['emailAddress' => ['address' => 'notices@example.com']],
            ],
            'receivedDateTime' => '2025-12-30T08:05:00Z',
            'body'             => [
                'contentType' => 'text',
                'content'     => $summaryBody,
            ],
            'hasAttachments'   => false,
        ];

        $service->ingestEmail($summaryMessage);

        $summaryEmail = IngestedEmail::where('message_id', 'MSG-summary-count')->firstOrFail();
        $this->assertEquals(3, $summaryEmail->parsed_record_count, '2 successes + 1 failure = 3 records');

        // Shoutbomb mismatched preference: mismatches_count
        $mismatchSubject = 'Mismatched patron preference, please switch to text Sun, January 4th 2026';
        $mismatchBody = <<<BODY
Home Library	Patron Phone	Cards set for voice notice preference	These cards are already registered for text with this phone number
3	5555666612	20000679026178	20000415540455
BODY;

        $mismatchMessage = [
            'id'               => 'MSG-mismatch-1',
            'subject'          => $mismatchSubject,
            'from'             => ['emailAddress' => ['address' => 'reports@example.com']],
            'toRecipients'     => [
                ['emailAddress' => ['address' => 'notices@example.com']],
            ],
            'receivedDateTime' => '2026-01-04T08:00:00Z',
            'body'             => [
                'contentType' => 'text',
                'content'     => $mismatchBody,
            ],
            'hasAttachments'   => false,
        ];

        $service->ingestEmail($mismatchMessage);

        $mismatchEmail = IngestedEmail::where('message_id', 'MSG-mismatch-1')->firstOrFail();
        $this->assertEquals(1, $mismatchEmail->parsed_record_count, 'One mismatch row -> record count 1');

        // Shoutbomb invalid phone: invalid_count (falls back through failures_count/invalid_count/errors_count)
        $invalidPhoneMessage = [
            'id'               => 'MSG-invalid-phone-count',
            'subject'          => 'Invalid patron phone number Thu, January 8th 2026',
            'from'             => ['emailAddress' => ['address' => 'reports@example.com']],
            'toRecipients'     => [
                ['emailAddress' => ['address' => 'notices@example.com']],
            ],
            'receivedDateTime' => '2026-01-08T08:00:00Z',
            'body'             => [
                'contentType' => 'text',
                'content'     => "5555312217 :: 20000001001 :: 123 :: 3 :: SMS\n2705551212 :: 20000001002 :: 124 :: 3 :: SMS",
            ],
            'hasAttachments'   => false,
        ];

        $service->ingestEmail($invalidPhoneMessage);

        $invalidEmail = IngestedEmail::where('message_id', 'MSG-invalid-phone-count')->firstOrFail();
        $this->assertEquals(2, $invalidEmail->parsed_record_count, 'Two invalid phone entries -> record count 2');
    }

    /**
     * 5. GraphEmailIngestionService::ingestEmail handles emails that do not
     *    match any known report classification (no parser) gracefully by
     *    still storing them with a generic report type and zero parsed
     *    records, without creating DeliveryConfirmation rows.
     */
    public function test_ingest_email_handles_unclassified_email_without_parser_gracefully(): void
    {
        $service = $this->makeService();

        $message = [
            'id'               => 'MSG-unclassified-1',
            'subject'          => 'Random service notification about backups',
            'from'             => ['emailAddress' => ['address' => 'alerts@example.com']],
            'toRecipients'     => [
                ['emailAddress' => ['address' => 'notices@example.com']],
            ],
            'receivedDateTime' => '2026-01-10T10:00:00Z',
            'body'             => [
                'contentType' => 'text',
                'content'     => 'This is a generic notification not related to Shoutbomb or Polaris reports.',
            ],
            'hasAttachments'   => false,
        ];

        $service->ingestEmail($message);

        $this->assertEquals(1, IngestedEmail::count());

        $email = IngestedEmail::firstOrFail();

        // Should be categorized as "other" via fallback classification logic.
        $this->assertNotNull($email->emailReportType);
        $this->assertEquals('other', $email->emailReportType->code);

        // No parser -> no parsed data, zero record count, but still processed.
        $this->assertNull($email->parsed_data);
        $this->assertSame(0, $email->parsed_record_count);
        $this->assertEquals('processed', $email->processing_status);

        // No DeliveryConfirmation rows should be created for unclassified emails.
        $this->assertEquals(0, DeliveryConfirmation::count());
    }

    /**
     * 6. For structured reports, if the raw Graph message_id collides with an
     *    existing row (e.g., an older notice_files email), we synthesize a
     *    new message_id of the form "<original-id>#YYYY-MM-DD" where
     *    YYYY-MM-DD is the logical report_date, so we can insert the
     *    structured report without violating the unique index.
     */
    public function test_ingest_email_generates_synthetic_message_id_for_structured_collision(): void
    {
        $service = $this->makeService();

        // Existing unstructured email using a Graph message_id that will be
        // reused by a later structured report.
        $existing = IngestedEmail::create([
            'message_id'           => 'MSG-shared-id',
            'email_report_type_id' => EmailReportType::where('code', 'notice_files')->firstOrFail()->id,
            'subject'              => 'Notice files for 2025-12-30 processed successfully',
            'body_full'            => 'Unstructured notice files status email',
        ]);

        $this->assertEquals('MSG-shared-id', $existing->message_id);
        $this->assertEquals(1, IngestedEmail::count(), 'Precondition: one unstructured email exists');

        // Structured Polaris summary report that reuses the same Graph
        // message_id but has a distinct report_type + report_date. The
        // parser will derive report_date = 2025-12-30 from the End time.
        $summaryBody = <<<BODY
The following email notices were sent out from Daviess County Public Library.
Start time: 12/29/2025 9:01 AM
End time: 12/30/2025 8:01 AM

1 Hold E-mail notices sent out to:
John Doe  20000001001  john@example.com
BODY;

        $summaryMessage = [
            'id'               => 'MSG-shared-id', // same Graph ID as existing row
            'subject'          => 'Summary Report - Daviess County Public Library',
            'from'             => ['emailAddress' => ['address' => 'reports@example.com']],
            'toRecipients'     => [
                ['emailAddress' => ['address' => 'notices@example.com']],
            ],
            'receivedDateTime' => '2025-12-30T08:05:00Z',
            'body'             => [
                'contentType' => 'text',
                'content'     => $summaryBody,
            ],
            'hasAttachments'   => false,
        ];

        $service->ingestEmail($summaryMessage);

        // We should now have two rows: the original unstructured email and a
        // new structured report row.
        $this->assertEquals(2, IngestedEmail::count());

        $summaryEmail = IngestedEmail::where('subject', 'like', 'Summary Report - Daviess County Public Library%')->firstOrFail();

        // The structured report should have a synthetic message_id that
        // includes the logical report_date (derived from End time).
        $this->assertEquals('MSG-shared-id#2025-12-30', $summaryEmail->message_id);
        $this->assertEquals('polaris_summary_report', $summaryEmail->emailReportType->code);
    }

    /**
     * 7. Summary Reports with the same message_id but a different coverage
     *    date (report_date derived from body) are NOT treated as duplicates.
     */
    public function test_summary_reports_with_same_message_id_and_different_report_date_are_both_ingested(): void
    {
        $service = $this->makeService();

        $bodyDay1 = <<<BODY
The following email notices were sent out from Daviess County Public Library.
Start time: 12/29/2025 9:01 AM
End time: 12/30/2025 8:01 AM

1 Hold E-mail notices sent out to:
John Doe  20000001001  john@example.com
BODY;

        $bodyDay2 = <<<BODY
The following email notices were sent out from Daviess County Public Library.
Start time: 12/30/2025 9:01 AM
End time: 12/31/2025 8:01 AM

1 Hold E-mail notices sent out to:
Jane Smith  20000001002  jane@example.com
BODY;

        $messageId = 'MSG-summary-same-id';

        $msgDay1 = [
            'id'               => $messageId,
            'subject'          => 'Summary Report - Daviess County Public Library',
            'from'             => ['emailAddress' => ['address' => 'reports@example.com']],
            'toRecipients'     => [
                ['emailAddress' => ['address' => 'notices@example.com']],
            ],
            'receivedDateTime' => '2025-12-30T08:05:00Z',
            'body'             => [
                'contentType' => 'text',
                'content'     => $bodyDay1,
            ],
            'hasAttachments'   => false,
        ];

        $msgDay2 = [
            'id'               => $messageId, // same Graph ID
            'subject'          => 'Summary Report - Daviess County Public Library',
            'from'             => ['emailAddress' => ['address' => 'reports@example.com']],
            'toRecipients'     => [
                ['emailAddress' => ['address' => 'notices@example.com']],
            ],
            'receivedDateTime' => '2025-12-31T08:05:00Z',
            'body'             => [
                'contentType' => 'text',
                'content'     => $bodyDay2,
            ],
            'hasAttachments'   => false,
        ];

        $service->ingestEmail($msgDay1);
        $service->ingestEmail($msgDay2);

        $this->assertEquals(2, IngestedEmail::count(), 'Both coverage dates should be ingested even with same message_id');

        $dates = IngestedEmail::orderBy('report_date')->pluck('report_date')->map(fn ($d) => $d?->format('Y-m-d'))->all();
        $this->assertSame(['2025-12-30', '2025-12-31'], $dates);
    }

    /**
     * 8. Non-duplicate Polaris Summary Reports must not parse to zero
     *    records. If parsed_record_count ends up as 0, the email is still
     *    stored for audit, but flagged by parsed_record_count and must NOT
     *    count as valid coverage for that date.
     */
    public function test_non_duplicate_polaris_summary_report_with_zero_records_is_stored_but_marked_zero(): void
    {
        $service = $this->makeService();

        // Body with no sections/entries -> parser will produce 0 successes + 0 failures.
        $emptyBody = <<<BODY
The following email notices were sent out from Daviess County Public Library.
Start time: 12/29/2025 9:01 AM
End time: 12/30/2025 8:01 AM

No Hold E-mail notices sent.
BODY;

        $message = [
            'id'               => 'MSG-summary-zero-records',
            'subject'          => 'Summary Report - Daviess County Public Library',
            'from'             => ['emailAddress' => ['address' => 'reports@example.com']],
            'toRecipients'     => [
                ['emailAddress' => ['address' => 'notices@example.com']],
            ],
            'receivedDateTime' => '2025-12-30T08:05:00Z',
            'body'             => [
                'contentType' => 'text',
                'content'     => $emptyBody,
            ],
            'hasAttachments'   => false,
        ];

        $service->ingestEmail($message);

        $this->assertEquals(1, IngestedEmail::count());
        $email = IngestedEmail::firstOrFail();

        $this->assertEquals('polaris_summary_report', $email->emailReportType->code);
        $this->assertSame(0, $email->parsed_record_count, 'Zero-record summary report must be stored with parsed_record_count = 0');
    }

    /**
     * Calls-that-generated-errors mail switched to a shorter line in August 2026.
     * Those lines must become delivery confirmations, and a previously stored
     * zero-record row for the same report date must be filled in rather than skipped.
     */
    public function test_voice_system_error_current_format_refills_empty_report(): void
    {
        $service = $this->makeService();
        $reportType = EmailReportType::where('code', 'shoutbomb_voice_system_error')->firstOrFail();

        IngestedEmail::create([
            'message_id'           => 'MSG-calls-empty',
            'email_report_type_id' => $reportType->id,
            'subject'              => 'Calls that generated errors Mon, October 26th 2026',
            'body_full'            => 'Hello,',
            'report_date'          => Carbon::create(2026, 10, 25)->startOfDay(),
            'parsed_record_count'  => 0,
        ]);

        $message = [
            'id'               => 'MSG-calls-current',
            'subject'          => 'Calls that generated errors Mon, October 26th 2026',
            'from'             => ['emailAddress' => ['address' => 'no_reply@shoutbomb.com']],
            'toRecipients'     => [
                ['emailAddress' => ['address' => 'staff@example.org']],
            ],
            'receivedDateTime' => '2026-10-27T11:02:02Z',
            'body'             => [
                'contentType' => 'text',
                'content'     => "Hello,\n\nPatron number:: 5555673101 :: Error reason  :: Destination not found :: Library number 5555078405 :: 2026-10-26 :: 06:13:07 :: \n",
            ],
            'hasAttachments'   => false,
        ];

        $service->ingestEmail($message);

        $this->assertEquals(1, IngestedEmail::count(), 'Empty system-error report should be refilled, not duplicated');

        $email = IngestedEmail::firstOrFail();
        $this->assertSame(1, $email->parsed_record_count);
        $this->assertSame('5555673101', $email->parsed_data['errors'][0]['patron_phone']);
        $this->assertSame('Destination not found', $email->parsed_data['errors'][0]['error_message']);

        $confirmation = DeliveryConfirmation::where('confirmation_source', 'shoutbomb_voice_system_error')->firstOrFail();
        $this->assertSame('5555673101', $confirmation->delivery_string);
        $this->assertSame($email->id, $confirmation->ingested_email_id);

        // A second pass must not add another confirmation.
        $service->ingestEmail($message);
        $this->assertEquals(1, DeliveryConfirmation::where('confirmation_source', 'shoutbomb_voice_system_error')->count());
    }

    public function test_voice_system_error_legacy_bandwidth_line_still_parses(): void
    {
        $service = $this->makeService();

        $message = [
            'id'               => 'MSG-calls-legacy',
            'subject'          => 'Calls that generated errors Fri, January 9th 2026',
            'from'             => ['emailAddress' => ['address' => 'no_reply@shoutbomb.com']],
            'toRecipients'     => [
                ['emailAddress' => ['address' => 'staff@example.org']],
            ],
            'receivedDateTime' => '2026-01-10T12:00:26Z',
            'body'             => [
                'contentType' => 'text',
                'content'     => 'BANDWIDTH :: c-3c0f9aac-46e880dd-fb14-42b1-809f-4adbc79970f7 :: Patron number 5555312217 :: Home Library 3 :: error :: disconnect ::: Service unavailable ::: 41564922-8a3d-46b2-acc4-6f4b00a15c6a :: Library number 5555078405 :: 2026-01-09 :: 06:13:07 ::',
            ],
            'hasAttachments'   => false,
        ];

        $service->ingestEmail($message);

        $email = IngestedEmail::where('message_id', 'MSG-calls-legacy')->firstOrFail();
        $this->assertSame('shoutbomb_voice_system_error', $email->emailReportType->code);
        $this->assertSame(1, $email->parsed_record_count);
        $this->assertSame('5555312217', $email->parsed_data['errors'][0]['patron_phone']);
        $this->assertSame('Service unavailable', $email->parsed_data['errors'][0]['error_message']);

        $confirmation = DeliveryConfirmation::where('confirmation_source', 'shoutbomb_voice_system_error')->firstOrFail();
        $this->assertSame('5555312217', $confirmation->delivery_string);
    }
}

/**
 * Test-only subclass that exposes a public ingestEmail() method, delegating
 * to the protected processEmailMessage() implementation.
 */
class TestableGraphEmailIngestionService extends GraphEmailIngestionService
{
    public function ingestEmail(array $message): void
    {
        $this->processEmailMessage($message);
    }
}
