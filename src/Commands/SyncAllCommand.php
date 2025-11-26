<?php

namespace Dcplibrary\Notices\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncAllCommand extends Command
{
    protected $signature = 'notices:sync-all
                            {--days=30 : Number of days to sync for Shoutbomb data}
                            {--skip-polaris : Skip Polaris import}
                            {--skip-shoutbomb : Skip Shoutbomb import}
                            {--skip-aggregate : Skip aggregation step}';

    protected $description = 'Run all sync operations: Polaris import, Shoutbomb import, sync to logs, and aggregation';

    public function handle(): int
    {
        $this->info('');
        $this->info('  ╔═══════════════════════════════════════╗');
        $this->info('  ║     Running Full Data Sync            ║');
        $this->info('  ╚═══════════════════════════════════════╝');
        $this->info('');

        $results = [];
        $hasErrors = false;
        $totalRecords = 0;

        // Step 1: Import from Polaris
        if (!$this->option('skip-polaris')) {
            $this->line('→ Step 1: Importing from Polaris...');
            try {
                $exitCode = Artisan::call('notices:import-polaris');
                $output = Artisan::output();

                preg_match('/Imported (\d+) notification/', $output, $matches);
                $records = isset($matches[1]) ? (int) $matches[1] : 0;
                $totalRecords += $records;

                if ($exitCode === 0) {
                    $this->info("  ✓ Polaris import completed ({$records} records)");
                    $results['polaris'] = ['status' => 'success', 'records' => $records];
                } else {
                    $this->error("  ✗ Polaris import failed");
                    $results['polaris'] = ['status' => 'error', 'message' => trim($output)];
                    $hasErrors = true;
                }
            } catch (Exception $e) {
                $this->error("  ✗ Polaris import error: {$e->getMessage()}");
                $results['polaris'] = ['status' => 'error', 'message' => $e->getMessage()];
                $hasErrors = true;
            }
        } else {
            $this->line('→ Step 1: Skipping Polaris import (--skip-polaris)');
            $results['polaris'] = ['status' => 'skipped'];
        }

        // Step 2: Import from Shoutbomb
        if (!$this->option('skip-shoutbomb')) {
            $this->newLine();
            $this->line('→ Step 2: Importing Shoutbomb data...');
            try {
                $exitCode = Artisan::call('notices:import-shoutbomb');
                $output = Artisan::output();

                preg_match('/Imported (\d+)/', $output, $matches);
                $records = isset($matches[1]) ? (int) $matches[1] : 0;
                $totalRecords += $records;

                if ($exitCode === 0) {
                    $this->info("  ✓ Shoutbomb import completed ({$records} records)");
                    $results['shoutbomb'] = ['status' => 'success', 'records' => $records];
                } else {
                    $this->error("  ✗ Shoutbomb import failed");
                    $results['shoutbomb'] = ['status' => 'error', 'message' => trim($output)];
                    $hasErrors = true;
                }
            } catch (Exception $e) {
                $this->error("  ✗ Shoutbomb import error: {$e->getMessage()}");
                $results['shoutbomb'] = ['status' => 'error', 'message' => $e->getMessage()];
                $hasErrors = true;
            }

            // Step 3: Sync Shoutbomb phone notices to notification_logs
            $this->newLine();
            $this->line('→ Step 3: Syncing Shoutbomb data to notification_logs...');
            try {
                $days = (int) $this->option('days');
                $exitCode = Artisan::call('notices:sync-shoutbomb-to-logs', [
                    '--days' => $days,
                    '--force' => true,
                ]);
                $output = Artisan::output();

                preg_match('/Synced.*?(\d+)/i', $output, $matches);
                $records = isset($matches[1]) ? (int) $matches[1] : 0;
                $totalRecords += $records;

                if ($exitCode === 0) {
                    $this->info("  ✓ Shoutbomb sync completed ({$records} records)");
                    $results['shoutbomb_sync'] = ['status' => 'success', 'records' => $records];
                } else {
                    $this->error("  ✗ Shoutbomb sync failed");
                    $results['shoutbomb_sync'] = ['status' => 'error', 'message' => trim($output)];
                    $hasErrors = true;
                }
            } catch (Exception $e) {
                $this->error("  ✗ Shoutbomb sync error: {$e->getMessage()}");
                $results['shoutbomb_sync'] = ['status' => 'error', 'message' => $e->getMessage()];
                $hasErrors = true;
            }
        } else {
            $this->line('→ Step 2-3: Skipping Shoutbomb import and sync (--skip-shoutbomb)');
            $results['shoutbomb'] = ['status' => 'skipped'];
            $results['shoutbomb_sync'] = ['status' => 'skipped'];
        }

        // Step 4: Run aggregation
        if (!$this->option('skip-aggregate')) {
            $this->newLine();
            $this->line('→ Step 4: Running aggregation...');
            try {
                $exitCode = Artisan::call('notices:aggregate');
                $output = Artisan::output();

                if ($exitCode === 0) {
                    $this->info("  ✓ Aggregation completed");
                    $results['aggregate'] = ['status' => 'success'];
                } else {
                    $this->error("  ✗ Aggregation failed");
                    $results['aggregate'] = ['status' => 'error', 'message' => trim($output)];
                    $hasErrors = true;
                }
            } catch (Exception $e) {
                $this->error("  ✗ Aggregation error: {$e->getMessage()}");
                $results['aggregate'] = ['status' => 'error', 'message' => $e->getMessage()];
                $hasErrors = true;
            }
        } else {
            $this->line('→ Step 4: Skipping aggregation (--skip-aggregate)');
            $results['aggregate'] = ['status' => 'skipped'];
        }

        // Display summary
        $this->newLine();
        $this->info('═══════════════════════════════════════════');
        $this->info('  Sync Summary');
        $this->info('═══════════════════════════════════════════');
        $this->newLine();

        $rows = [];
        foreach ($results as $step => $result) {
            $status = $result['status'] ?? 'unknown';
            $icon = match ($status) {
                'success' => '✓',
                'skipped' => '○',
                'error' => '✗',
                default => '?',
            };
            $records = isset($result['records']) ? " ({$result['records']} records)" : '';
            $rows[] = [ucfirst(str_replace('_', ' ', $step)), "{$icon} " . ucfirst($status) . $records];
        }

        $this->table(['Step', 'Status'], $rows);

        $this->newLine();
        $this->line("  Total records processed: " . number_format($totalRecords));
        $this->newLine();

        if ($hasErrors) {
            $this->warn('  ⚠ Sync completed with some errors');

            return Command::FAILURE;
        }

        $this->info('  ✓ Full sync completed successfully!');
        $this->newLine();

        return Command::SUCCESS;
    }
}
