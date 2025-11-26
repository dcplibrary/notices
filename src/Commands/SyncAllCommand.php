<?php

namespace Dcplibrary\Notices\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncAllCommand extends Command
{
   protected $signature = 'notices:sync-all
                            {--date= : Run for a single YYYY-MM-DD date}
                            {--start= : Start date (YYYY-MM-DD)}
                            {--end= : End date (YYYY-MM-DD)}
                            {--days=30 : Number of days to sync when using rolling windows}
                            {--all : Run for all available history (use with care)}
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
        $results = [];
        $hasErrors = false;
        $totalRecords = 0;

        // Normalize range options into a canonical array we can pass through
        $rangeOptions = $this->buildRangeOptions();

        // Step 1: Import from Polaris
        if (!$this->option('skip-polaris')) {
          $this->line('→ Step 1: Importing from Polaris...');
            try {
                $exitCode = Artisan::call('notices:import-polaris', $rangeOptions);
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
            $this->newLine();
            $this->line('→ Step 2: Importing Shoutbomb data (FTP files)...');
            try {
              // Use unified FTP importer so future file types are automatically included
                $exitCode = Artisan::call('notices:import-ftp-files', $rangeOptions);
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
                // For sync-shoutbomb-to-logs we currently only support --days; derive from range options
                $days = $this->resolveDaysFromRange($rangeOptions, (int) $this->option('days'));
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
          $aggregateOptions = [];
          if (!empty($rangeOptions)) {
          // Reuse the same range semantics for aggregation where supported
          if (isset($rangeOptions['--all'])) {
            $aggregateOptions['--all'] = $rangeOptions['--all'];
          } elseif (isset($rangeOptions['--date'])) {
            $aggregateOptions['--date'] = $rangeOptions['--date'];
          } elseif (isset($rangeOptions['--start']) || isset($rangeOptions['--end'])) {
              if (isset($rangeOptions['--start'])) {
                $aggregateOptions['--start'] = $rangeOptions['--start'];
               }
               if (isset($rangeOptions['--end'])) {
                  $aggregateOptions['--end'] = $rangeOptions['--end'];
                }
            } elseif (isset($rangeOptions['--days'])) {
                $aggregateOptions['--days'] = $rangeOptions['--days'];
            }
          }

          $exitCode = Artisan::call('notices:aggregate', $aggregateOptions);
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

    /**
     * Build canonical range options (Artisan-style) from this command's flags.
     *
     * @return array<string,mixed>
     */
    private function buildRangeOptions(): array
    {
        $options = [];

        if ($this->option('all')) {
            $options['--all'] = true;

            return $options;
        }

        $date = $this->option('date');
        $start = $this->option('start');
        $end = $this->option('end');
        $days = $this->option('days');

        if (!empty($date)) {
            $options['--date'] = $date;
        } elseif (!empty($start) || !empty($end)) {
            if (!empty($start)) {
                $options['--start'] = $start;
            }
            if (!empty($end)) {
                $options['--end'] = $end;
            }
        } elseif (!empty($days)) {
            $options['--days'] = (int) $days;
        }

        return $options;
    }

    /**
     * Derive a sensible --days value for sync-shoutbomb-to-logs from range options.
     */
    private function resolveDaysFromRange(array $rangeOptions, int $defaultDays): int
    {
        if (isset($rangeOptions['--days'])) {
            return (int) $rangeOptions['--days'];
        }

        // For now, fall back to the explicit --days option (default 30)
        return $defaultDays > 0 ? $defaultDays : 30;
    }
}
