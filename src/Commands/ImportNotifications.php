    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notices:import
                            {--days=1 : Number of days to import}
                            {--start-date= : Start date for import (Y-m-d format)}
                            {--end-date= : End date for import (Y-m-d format)}
                            {--full : Import all historical data (deprecated, use --all)}
                            {--all : Import all historical data}';

    /**
     * The console command description.
     */
    protected $description = 'Import notifications from Polaris ILS database';

    /**
     * Execute the console command.
     */
    public function handle(PolarisImportService $importer, NotificationAggregatorService $aggregator): int
    {
        $this->info('🚀 Starting Polaris notification import...');
        $this->newLine();

        try {
            $result = null;

            $full = $this->option('full') || $this->option('all');

            if ($full) {
                // Full historical import
                $this->warn('⚠️  Full historical import requested. This may take a while...');

                $startDate = $this->ask('Enter start date (Y-m-d)', '2020-01-01');
                $endDate = $this->ask('Enter end date (Y-m-d, or leave empty for today)', now()->format('Y-m-d'));

                if ($this->confirm('Are you sure you want to import all data from ' . $startDate . ' to ' . $endDate . '?', true)) {
                    $result = $importer->importHistorical(
                        Carbon::parse($startDate),
                        $endDate ? Carbon::parse($endDate) : null
                    );
                } else {
                    $this->info('Import cancelled.');

                    return Command::SUCCESS;
                }

            } elseif ($this->option('start-date') && $this->option('end-date')) {
                // Date range import
                $startDate = Carbon::parse($this->option('start-date'));
                $endDate = Carbon::parse($this->option('end-date'));

                $this->info("Importing notifications from {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}...");

                $result = $importer->importNotifications(null, $startDate, $endDate);

            } else {
                // Days-based import (default)
                $days = (int) $this->option('days');

                $this->info("Importing notifications from the last {$days} day(s)...");

                $result = $importer->importNotifications($days);
            }

            // Display results
            $this->newLine();
            $this->line('─────────────────────────────────────────');
            $this->info('✅ Import completed successfully!');
            $this->line('─────────────────────────────────────────');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Imported', $result['imported']],
                    ['Skipped (duplicates)', $result['skipped']],
                    ['Errors', $result['errors']],
                ]
            );
            $this->line('─────────────────────────────────────────');
            $this->info("📅 Date range: {$result['start_date']} to {$result['end_date']}");
            $this->newLine();

            // Automatically aggregate imported data
            if ($result['imported'] > 0) {
                $this->info('📊 Aggregating imported data for analytics...');

                $startDate = Carbon::parse($result['start_date'])->startOfDay();
                $endDate = Carbon::parse($result['end_date'])->startOfDay();

                $aggResult = $aggregator->aggregateDateRange($startDate, $endDate);

                $this->info("✅ Aggregated {$aggResult['combinations_aggregated']} combinations");
                $this->newLine();
            }

            // Show import stats
            if ($this->option('verbose')) {
                $this->info('📊 Current database statistics:');
                $stats = $importer->getImportStats();

                $this->table(
                    ['Statistic', 'Value'],
                    [
                        ['Total records', number_format($stats['total_records'])],
                        ['Latest import', $stats['latest_import'] ?? 'N/A'],
                        ['Latest notification', $stats['latest_notification'] ?? 'N/A'],
                        ['Oldest notification', $stats['oldest_notification'] ?? 'N/A'],
                    ]
                );
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('❌ Import failed: ' . $e->getMessage());

            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }
}
