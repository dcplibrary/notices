<?php

namespace Dcplibrary\Notices\Services;

use Dcplibrary\Notices\Models\PolarisNotificationLog;
use Dcplibrary\Notices\Models\NotificationLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PolarisImportService
{
    /**
     * Import notifications from Polaris for a specific date range.
     */
    public function importNotifications(?int $days = null, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        // Determine date range
        if ($days !== null) {
            $endDate = now();
            $startDate = now()->subDays($days);
        } elseif (!$startDate || !$endDate) {
            // Default to yesterday's data
            $days = config('notices.import.default_days', 1);
            $endDate = now();
            $startDate = now()->subDays($days);
        }

        Log::info("Starting Polaris notification import", [
            'start_date' => $startDate->format('Y-m-d H:i:s'),
            'end_date' => $endDate->format('Y-m-d H:i:s'),
        ]);

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        try {
            // Get organization ID from config
            $orgId = config('notices.reporting_org_id');

            // Query Polaris database
            $query = PolarisNotificationLog::dateRange($startDate, $endDate);

            if ($orgId) {
                $query->forOrganization($orgId);
            }

            $notifications = $query->orderBy('NotificationDateTime')->get();

            Log::info("Found {$notifications->count()} notifications to import");

            $batchSize = config('notices.import.batch_size', 500);
            $skipDuplicates = config('notices.import.skip_duplicates', true);

            // Process in batches
            foreach ($notifications->chunk($batchSize) as $batch) {
                $records = [];

                foreach ($batch as $notification) {
                    try {
                        // Check if already imported
                        if ($skipDuplicates && $notification->NotificationLogID) {
                            $exists = NotificationLog::where('polaris_log_id', $notification->NotificationLogID)->exists();
                            if ($exists) {
                                $skipped++;
                                continue;
                            }
                        }

                        $records[] = $notification->toLocalFormat();
                        $imported++;
                    } catch (\Exception $e) {
                        Log::error("Error processing notification {$notification->NotificationLogID}", [
                            'error' => $e->getMessage(),
                        ]);
                        $errors++;
                    }
                }

                // Bulk insert the batch
                if (!empty($records)) {
                    NotificationLog::insert($records);
                }
            }

            Log::info("Polaris import completed", [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            Log::error("Polaris import failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }

        return [
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'start_date' => $startDate->format('Y-m-d H:i:s'),
            'end_date' => $endDate->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Import full historical data.
     */
    public function importHistorical(Carbon $startDate, ?Carbon $endDate = null): array
    {
        $endDate = $endDate ?? now();

        Log::info("Starting historical import", [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ]);

        $totalImported = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        // Process month by month to avoid memory issues
        $currentStart = $startDate->copy();

        while ($currentStart->lte($endDate)) {
            $currentEnd = $currentStart->copy()->endOfMonth();
            if ($currentEnd->gt($endDate)) {
                $currentEnd = $endDate->copy();
            }

            Log::info("Importing month: {$currentStart->format('Y-m')}");

            $result = $this->importNotifications(null, $currentStart, $currentEnd);

            $totalImported += $result['imported'];
            $totalSkipped += $result['skipped'];
            $totalErrors += $result['errors'];

            $currentStart = $currentEnd->copy()->addDay()->startOfDay();
        }

        Log::info("Historical import completed", [
            'total_imported' => $totalImported,
            'total_skipped' => $totalSkipped,
            'total_errors' => $totalErrors,
        ]);

        return [
            'success' => true,
            'imported' => $totalImported,
            'skipped' => $totalSkipped,
            'errors' => $totalErrors,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ];
    }

    /**
     * Get import statistics.
     */
    public function getImportStats(): array
    {
        return [
            'total_records' => NotificationLog::count(),
            'latest_import' => NotificationLog::max('imported_at'),
            'latest_notification' => NotificationLog::max('notification_date'),
            'oldest_notification' => NotificationLog::min('notification_date'),
            'by_type' => NotificationLog::selectRaw('notification_type_id, COUNT(*) as count')
                ->groupBy('notification_type_id')
                ->pluck('count', 'notification_type_id')
                ->toArray(),
            'by_delivery' => NotificationLog::selectRaw('delivery_option_id, COUNT(*) as count')
                ->groupBy('delivery_option_id')
                ->pluck('count', 'delivery_option_id')
                ->toArray(),
        ];
    }

    /**
     * Test Polaris database connection.
     */
    public function testConnection(): array
    {
        try {
            DB::connection('polaris')->getPdo();

            $count = PolarisNotificationLog::count();

            return [
                'success' => true,
                'message' => 'Successfully connected to Polaris database',
                'total_notifications' => $count,
            ];
        } catch (\PDOException $e) {
            // Check if this is a driver issue
            if (str_contains($e->getMessage(), 'could not find driver')) {
                $driver = config('notices.polaris_connection.driver', 'sqlsrv');
                $errorMessage = $this->getDriverInstallationHelp($driver);

                return [
                    'success' => false,
                    'message' => 'Database driver not found',
                    'error' => $errorMessage,
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to connect to Polaris database',
                'error' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to connect to Polaris database',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get helpful installation instructions for the missing driver.
     */
    protected function getDriverInstallationHelp(string $driver): string
    {
        $os = PHP_OS_FAMILY;

        if ($driver === 'sqlsrv') {
            if ($os === 'Linux') {
                return "The 'sqlsrv' driver is not installed.\n\n" .
                    "On Linux, you have two options:\n\n" .
                    "Option 1 - Microsoft ODBC Driver (recommended for production):\n" .
                    "  1. Install Microsoft ODBC Driver for SQL Server\n" .
                    "  2. Install PHP sqlsrv extension: pecl install sqlsrv pdo_sqlsrv\n" .
                    "  See: https://docs.microsoft.com/en-us/sql/connect/php/installation-tutorial-linux-mac\n\n" .
                    "Option 2 - FreeTDS (easier installation):\n" .
                    "  1. Install FreeTDS: sudo apt-get install php-sybase\n" .
                    "  2. Change driver in config/notifications.php to 'dblib'\n" .
                    "  3. Restart PHP-FPM: sudo service php-fpm restart";
            } elseif ($os === 'Windows') {
                return "The 'sqlsrv' driver is not installed.\n\n" .
                    "Download and install the Microsoft Drivers for PHP for SQL Server:\n" .
                    "https://docs.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server";
            }
        } elseif ($driver === 'dblib' || $driver === 'mssql') {
            return "The '{$driver}' driver (FreeTDS) is not installed.\n\n" .
                "Install FreeTDS:\n" .
                "  Ubuntu/Debian: sudo apt-get install php-sybase freetds-common\n" .
                "  CentOS/RHEL: sudo yum install php-mssql freetds\n" .
                "  macOS: brew install freetds && pecl install pdo_dblib\n\n" .
                "After installation, restart PHP-FPM.";
        }

        return "The '{$driver}' driver is not installed. Please install the appropriate PDO driver for SQL Server.";
    }
}
