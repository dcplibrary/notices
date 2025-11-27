<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Dcplibrary\Notices\Models\SyncLog;
use Dcplibrary\Notices\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;

class SyncLogResultsTest extends TestCase
{
    use WithFaker;

    /** @test */
    public function it_persists_and_exposes_polaris_import_results(): void
    {
        $results = [
            'operation' => 'import_polaris',
            'status'    => 'success',
            'stats'     => ['records' => 1234],
            'errors'    => [],
            'raw'       => [
                'status'  => 'success',
                'message' => 'Imported 1234 notifications from Polaris.',
                'records' => 1234,
            ],
        ];

        /** @var SyncLog $log */
        $log = SyncLog::factory()
            ->operation('import_polaris')
            ->withResults($results, 1234)
            ->create();

        $this->assertSame('import_polaris', $log->results['operation']);
        $this->assertSame('success', $log->results['status']);
        $this->assertSame(1234, $log->results['stats']['records']);

        $response = $this->getJson('/notices/sync/log/' . $log->id);

        $response->assertOk();
        $payload = $response->json();

        $this->assertSame('import_polaris', $payload['results']['operation']);
        $this->assertSame('success', $payload['results']['status']);
        $this->assertSame(1234, $payload['results']['stats']['records']);
    }

    /** @test */
    public function it_persists_and_exposes_ftp_import_results_with_stats_and_errors(): void
    {
        $results = [
            'operation' => 'import_ftp_files',
            'status'    => 'error',
            'stats'     => [
                'PhoneNotices' => 320,
                'Holds'        => 0,
                'Overdues'     => 87,
                'Renewals'     => 42,
                'Patrons'      => 210,
            ],
            'errors'    => [
                'Holds import failed: malformed header on holds_2025-11-25.txt',
            ],
            'raw'       => [
                'status'           => 'error',
                'message'          => 'Holds import failed: malformed header on holds_2025-11-25.txt',
                'records'          => 659,
                'phone_notices'    => 320,
                'holds'            => 0,
                'overdues'         => 87,
                'renewals'         => 42,
                'patrons_imported' => 210,
            ],
        ];

        /** @var SyncLog $log */
        $log = SyncLog::factory()
            ->operation('import_ftp_files')
            ->withResults($results, 659)
            ->create();

        $this->assertSame('import_ftp_files', $log->results['operation']);
        $this->assertSame('error', $log->results['status']);
        $this->assertSame(320, $log->results['stats']['PhoneNotices']);
        $this->assertSame(0, $log->results['stats']['Holds']);
        $this->assertSame('Holds import failed: malformed header on holds_2025-11-25.txt', $log->results['errors'][0]);

        $response = $this->getJson('/notices/sync/log/' . $log->id);

        $response->assertOk();
        $payload = $response->json();

        $this->assertSame('import_ftp_files', $payload['results']['operation']);
        $this->assertSame('error', $payload['results']['status']);
        $this->assertSame(320, $payload['results']['stats']['PhoneNotices']);
        $this->assertSame(0, $payload['results']['stats']['Holds']);
        $this->assertSame(
            'Holds import failed: malformed header on holds_2025-11-25.txt',
            $payload['results']['errors'][0]
        );
    }

    /** @test */
    public function it_persists_and_exposes_sync_all_component_results(): void
    {
        $components = [
            'polaris' => [
                'status'  => 'success',
                'message' => 'Imported 800 notifications from Polaris.',
                'records' => 800,
            ],
            'shoutbomb_sync' => [
                'status'  => 'error',
                'message' => 'Synced 0 Shoutbomb notifications: FTP connection failed.',
                'records' => 0,
            ],
            'aggregate' => [
                'status'  => 'success',
                'message' => 'Aggregation completed.',
            ],
        ];

        $results = [
            'operation'  => 'sync_all',
            'status'     => 'error',
            'components' => $components,
            'errors'     => [
                'SHOUTBOMB_SYNC: Synced 0 Shoutbomb notifications: FTP connection failed.',
            ],
        ];

        /** @var SyncLog $log */
        $log = SyncLog::factory()
            ->operation('sync_all')
            ->withResults($results, 800)
            ->create();

        $this->assertSame('sync_all', $log->results['operation']);
        $this->assertSame('error', $log->results['status']);
        $this->assertArrayHasKey('shoutbomb_sync', $log->results['components']);

        $response = $this->getJson('/notices/sync/log/' . $log->id);

        $response->assertOk();
        $payload = $response->json();

        $this->assertSame('sync_all', $payload['results']['operation']);
        $this->assertSame('error', $payload['results']['status']);
        $this->assertSame(
            'Synced 0 Shoutbomb notifications: FTP connection failed.',
            $payload['results']['components']['shoutbomb_sync']['message']
        );
        $this->assertSame(
            'SHOUTBOMB_SYNC: Synced 0 Shoutbomb notifications: FTP connection failed.',
            $payload['results']['errors'][0]
        );
    }
}
