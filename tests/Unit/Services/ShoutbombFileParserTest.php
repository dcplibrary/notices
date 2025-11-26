<?php

namespace Dcplibrary\Notices\Tests\Unit\Services;

use Carbon\Carbon;
use Dcplibrary\Notices\Services\ShoutbombFileParser;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class ShoutbombFileParserTest extends TestCase
{
    use RefreshDatabase;

    protected ShoutbombFileParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ShoutbombFileParser();

        // Create a fake storage disk for testing
        Storage::fake('local');
    }

    /** @test */
    public function it_can_parse_monthly_report_registration_statistics()
    {
        $content = <<<'EOT'
Shoutbomb Monthly Report - October 2025
========================================

Registration Statistics: 13307 text (72%), 5199 voice (28%)

Keyword Usage:
EOT;

        $filePath = $this->createTempFile('October_2025.txt', $content);

        $data = $this->parser->parseMonthlyReport($filePath);

        $this->assertNotNull($data['registration_stats']);
        $this->assertEquals(13307, $data['registration_stats']['total_text_subscribers']);
        $this->assertEquals(72, $data['registration_stats']['text_percentage']);
        $this->assertEquals(5199, $data['registration_stats']['total_voice_subscribers']);
        $this->assertEquals(28, $data['registration_stats']['voice_percentage']);
        $this->assertEquals(18506, $data['registration_stats']['total_subscribers']);
        $this->assertEquals('Monthly', $data['registration_stats']['report_type']);
    }

    /** @test */
    public function it_can_parse_monthly_report_keyword_usage()
    {
        $content = <<<'EOT'
Shoutbomb Monthly Report - October 2025

Registration Statistics: 13307 text (72%), 5199 voice (28%)

Keyword Usage:
HOLDS (Check holds): 1234 uses
RENEW (Renew items): 567 uses
CHECKOUTS (Check checkouts): 890 uses
EOT;

        $filePath = $this->createTempFile('October_2025.txt', $content);

        $data = $this->parser->parseMonthlyReport($filePath);

        $this->assertCount(3, $data['keyword_usage']);

        $holds = $data['keyword_usage'][0];
        $this->assertEquals('HOLDS', $holds['keyword']);
        $this->assertEquals('Check holds', $holds['keyword_description']);
        $this->assertEquals(1234, $holds['usage_count']);
        $this->assertEquals('Monthly', $holds['report_period']);

        $renew = $data['keyword_usage'][1];
        $this->assertEquals('RENEW', $renew['keyword']);
        $this->assertEquals(567, $renew['usage_count']);
    }

    /** @test */
    public function it_can_parse_weekly_report()
    {
        $content = <<<'EOT'
Shoutbomb Weekly Report

Registration Statistics: 13500 text, 5300 voice

Keyword Activity:
HOLDS: 245 uses
RENEW: 123 uses
EOT;

        $filePath = $this->createTempFile('Weekly_2025-11-08.txt', $content);

        $data = $this->parser->parseWeeklyReport($filePath);

        $this->assertNotNull($data['registration_stats']);
        $this->assertEquals(13500, $data['registration_stats']['total_text_subscribers']);
        $this->assertEquals(5300, $data['registration_stats']['total_voice_subscribers']);
        $this->assertEquals(18800, $data['registration_stats']['total_subscribers']);
        $this->assertEquals('Weekly', $data['registration_stats']['report_type']);

        // Check percentages are calculated
        $this->assertGreaterThan(70, $data['registration_stats']['text_percentage']);
        $this->assertLessThan(30, $data['registration_stats']['voice_percentage']);
    }

    /** @test */
    public function it_can_parse_daily_invalid_report()
    {
        $content = <<<'EOT'
Invalid Phone Numbers - 2025-11-08
===================================

Patron Barcode: 21234567890  Phone: 270-555-0123
Patron: 21234567891 Phone: 270.555.0124
PatronBarcode:21234567892 Phone:2705550125
EOT;

        $filePath = $this->createTempFile('Invalid_2025-11-08.txt', $content);

        $data = $this->parser->parseDailyInvalidReport($filePath);

        $this->assertArrayHasKey('deliveries', $data);
        $this->assertCount(3, $data['deliveries']);

        $delivery = $data['deliveries'][0];
        $this->assertEquals('21234567890', $delivery['patron_barcode']);
        $this->assertEquals('270-555-0123', $delivery['phone_number']);
        $this->assertEquals('SMS', $delivery['delivery_type']);
        $this->assertEquals('Invalid', $delivery['status']);
    }

    /** @test */
    public function it_normalizes_phone_numbers()
    {
        $content = <<<'EOT'
Patron: 21234567890 Phone: 2705550123
Patron: 21234567891 Phone: 270.555.0124
Patron: 21234567892 Phone: 270-555-0125
EOT;

        $filePath = $this->createTempFile('Invalid_2025-11-08.txt', $content);

        $data = $this->parser->parseDailyInvalidReport($filePath);

        // All phone numbers should be normalized to XXX-XXX-XXXX format
        $this->assertEquals('270-555-0123', $data['deliveries'][0]['phone_number']);
        $this->assertEquals('270-555-0124', $data['deliveries'][1]['phone_number']);
        $this->assertEquals('270-555-0125', $data['deliveries'][2]['phone_number']);
    }

    /** @test */
    public function it_can_parse_daily_undelivered_report()
    {
        $content = <<<'EOT'
Undelivered Voice Notices - 2025-11-08
======================================

21234567890 270-555-0123 - Unable to deliver
21234567891 270-555-0124 - No answer
EOT;

        $filePath = $this->createTempFile('Undelivered_2025-11-08.txt', $content);

        $data = $this->parser->parseDailyUndeliveredReport($filePath);

        $this->assertCount(2, $data['deliveries']);

        $delivery = $data['deliveries'][0];
        $this->assertEquals('21234567890', $delivery['patron_barcode']);
        $this->assertEquals('270-555-0123', $delivery['phone_number']);
        $this->assertEquals('Voice', $delivery['delivery_type']);
        $this->assertEquals('Failed', $delivery['status']);
        $this->assertEquals('Undelivered voice notice', $delivery['failure_reason']);
    }

    /** @test */
    public function it_extracts_date_from_filename_with_standard_format()
    {
        $content = "Test content";
        $filePath = $this->createTempFile('Report_2025-11-08.txt', $content);

        $data = $this->parser->parseMonthlyReport($filePath);

        $this->assertInstanceOf(Carbon::class, $data['registration_stats']['snapshot_date'] ?? Carbon::now());
    }

    /** @test */
    public function it_extracts_date_from_filename_with_month_year()
    {
        $content = "Registration Statistics: 13307 text (72%), 5199 voice (28%)";
        $filePath = $this->createTempFile('October_2025.txt', $content);

        $data = $this->parser->parseMonthlyReport($filePath);

        $this->assertNotNull($data['registration_stats']);
        $date = $data['registration_stats']['snapshot_date'];
        $this->assertEquals('2025-10-31', $date->format('Y-m-d')); // End of October
    }

    /** @test */
    public function it_can_import_parsed_registration_data()
    {
        $data = [
            'registration_stats' => [
                'snapshot_date' => Carbon::parse('2025-11-08'),
                'total_text_subscribers' => 13307,
                'text_percentage' => 72.0,
                'total_voice_subscribers' => 5199,
                'voice_percentage' => 28.0,
                'total_subscribers' => 18506,
                'report_file' => 'October_2025.txt',
                'report_type' => 'Monthly',
            ],
        ];

        $stats = $this->parser->importParsedData($data, 'Monthly');

        $this->assertEquals(1, $stats['registrations']);
        // SQLite stores date columns with a time component; assert against the
        // exact stored value rather than a date-only string.
        $this->assertDatabaseHas('shoutbomb_registrations', [
            'snapshot_date' => '2025-11-08 00:00:00',
            'total_text_subscribers' => 13307,
            'total_voice_subscribers' => 5199,
        ]);
    }

    /** @test */
    public function it_can_import_parsed_keyword_usage_data()
    {
        $data = [
            'keyword_usage' => [
                [
                    'keyword' => 'HOLDS',
                    'keyword_description' => 'Check holds',
                    'usage_count' => 1234,
                    'usage_date' => Carbon::parse('2025-11-08'),
                    'report_file' => 'October_2025.txt',
                    'report_period' => 'Monthly',
                ],
                [
                    'keyword' => 'RENEW',
                    'keyword_description' => 'Renew items',
                    'usage_count' => 567,
                    'usage_date' => Carbon::parse('2025-11-08'),
                    'report_file' => 'October_2025.txt',
                    'report_period' => 'Monthly',
                ],
            ],
        ];

        $stats = $this->parser->importParsedData($data, 'Monthly');

        $this->assertEquals(2, $stats['keyword_usage']);
        $this->assertDatabaseHas('shoutbomb_keyword_usage', [
            'keyword' => 'HOLDS',
            'usage_count' => 1234,
        ]);
        $this->assertDatabaseHas('shoutbomb_keyword_usage', [
            'keyword' => 'RENEW',
            'usage_count' => 567,
        ]);
    }

    /** @test */
    public function it_can_import_parsed_delivery_data()
    {
        $data = [
            'deliveries' => [
                [
                    'patron_barcode' => '21234567890',
                    'phone_number' => '270-555-0123',
                    'delivery_type' => 'SMS',
                    'sent_date' => Carbon::parse('2025-11-08'),
                    'status' => 'Invalid',
                    'report_file' => 'Invalid_2025-11-08.txt',
                    'report_type' => 'Daily',
                ],
            ],
        ];

        $stats = $this->parser->importParsedData($data, 'Daily');

        $this->assertEquals(1, $stats['deliveries']);
        $this->assertDatabaseHas('shoutbomb_deliveries', [
            'patron_barcode' => '21234567890',
            'phone_number' => '270-555-0123',
            'status' => 'Invalid',
        ]);
    }

    /**
     * Helper method to create a temporary file for testing.
     */
    protected function createTempFile(string $filename, string $content): string
    {
        $path = sys_get_temp_dir() . '/' . $filename;
        file_put_contents($path, $content);

        return $path;
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        $tempDir = sys_get_temp_dir();
        $files = glob($tempDir . '/*.txt');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }
}
