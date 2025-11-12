<?php

namespace Dcplibrary\Notices\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NormalizePhonesCommand extends Command
{
    protected $signature = 'notices:normalize-phones
                            {--dry-run : Show what would change without writing}
                            {--fast-sql : Use a single SQL UPDATE (MySQL 8+ REGEXP_REPLACE)}
                            {--tables=* : Tables to normalize (notification_logs, polaris_phone_notices, shoutbomb_submissions, shoutbomb_deliveries)}';

    protected $description = 'Normalize phone numbers across notice tables (digits-only, last 10). For notification_logs, derives from delivery_string.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $fast = (bool) $this->option('fast-sql');
        $tables = $this->option('tables');

        // Default to all supported tables if none specified
        if (empty($tables)) {
            $tables = [
                'notification_logs',
                'polaris_phone_notices',
                'shoutbomb_submissions',
                'shoutbomb_deliveries',
            ];
        }

        $ok = true;
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                $this->warn("Skipping {$table}: table does not exist.");
                continue;
            }

            switch ($table) {
                case 'notification_logs':
                    $ok = $this->normalizeNotificationLogs($dry, $fast) && $ok;
                    break;
                case 'polaris_phone_notices':
                    $ok = $this->normalizeGenericPhoneColumn('polaris_phone_notices', 'phone_number', $dry, $fast) && $ok;
                    break;
                case 'shoutbomb_submissions':
                    $ok = $this->normalizeGenericPhoneColumn('shoutbomb_submissions', 'phone_number', $dry, $fast) && $ok;
                    break;
                case 'shoutbomb_deliveries':
                    $ok = $this->normalizeGenericPhoneColumn('shoutbomb_deliveries', 'phone_number', $dry, $fast) && $ok;
                    break;
                default:
                    $this->warn("Unknown table {$table}, skipping.");
            }
        }

        return $ok ? Command::SUCCESS : Command::FAILURE;
    }

    protected function normalizeNotificationLogs(bool $dry, bool $fast): bool
    {
        $this->info('Normalizing phone in notification_logs from delivery_string...');

        if ($fast) {
            $sql = "UPDATE notification_logs\n" .
                   "SET phone = RIGHT(REGEXP_REPLACE(SUBSTRING_INDEX(delivery_string, '@', 1), '[^0-9]', ''), 10)\n" .
                   "WHERE delivery_option_id IN (3,8)\n" .
                   "  AND delivery_string IS NOT NULL";

            if ($dry) {
                $this->line('DRY RUN (fast-sql) [notification_logs]:');
                $this->line($sql);
                return true;
            }

            try {
                $affected = DB::update($sql);
                $this->info("notification_logs: updated {$affected} rows using fast SQL.");
                return true;
            } catch (\Throwable $e) {
                $this->warn('Fast SQL path failed for notification_logs. Falling back to chunked PHP mode.');
            }
        }

        // Chunked PHP fallback
        $chunk = 1000;
        $total = 0;
        DB::table('notification_logs')
            ->whereIn('delivery_option_id', [3, 8])
            ->whereNotNull('delivery_string')
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use (&$total, $dry) {
                $updates = [];
                foreach ($rows as $row) {
                    $raw = (string) $row->delivery_string;
                    $local = str_contains($raw, '@') ? substr($raw, 0, strpos($raw, '@')) : $raw;
                    $digits = preg_replace('/[^0-9]/', '', $local);
                    $normalized = $digits ? (strlen($digits) > 10 ? substr($digits, -10) : $digits) : null;

                    if ($normalized !== $row->phone) {
                        $updates[$row->id] = $normalized;
                    }
                }

                if (!$dry && !empty($updates)) {
                    foreach (array_chunk($updates, 500, true) as $batch) {
                        $ids = array_keys($batch);
                        // Build CASE update
                        $case = 'CASE id ';
                        foreach ($batch as $id => $phone) {
                            $val = $phone === null ? 'NULL' : ("'" . addslashes($phone) . "'");
                            $case .= "WHEN {$id} THEN {$val} ";
                        }
                        $case .= 'END';
                        DB::table('notification_logs')
                            ->whereIn('id', $ids)
                            ->update(['phone' => DB::raw($case)]);
                    }
                }

                $total += count($updates);
                $this->line("notification_logs: prepared updates in chunk: " . count($updates));
            });

        if ($dry) {
            $this->info("notification_logs DRY RUN: would update {$total} rows.");
        } else {
            $this->info("notification_logs: updated {$total} rows.");
        }

        return true;
    }

    protected function normalizeGenericPhoneColumn(string $table, string $column, bool $dry, bool $fast): bool
    {
        $this->info("Normalizing {$column} in {$table}...");

        if ($fast) {
            $sql = "UPDATE {$table} SET {$column} = NULLIF(RIGHT(REGEXP_REPLACE({$column}, '[^0-9]', ''), 10), '')";

            if ($dry) {
                $this->line("DRY RUN (fast-sql) [{$table}]:");
                $this->line($sql);
                return true;
            }

            try {
                $affected = DB::update($sql);
                $this->info("{$table}: updated {$affected} rows using fast SQL.");
                return true;
            } catch (\Throwable $e) {
                $this->warn("Fast SQL path failed for {$table}. Falling back to chunked PHP mode.");
            }
        }

        // Chunked PHP fallback
        $chunk = 1000;
        $total = 0;
        DB::table($table)
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use (&$total, $dry, $table, $column) {
                $updates = [];
                foreach ($rows as $row) {
                    $raw = (string) ($row->{$column} ?? '');
                    $digits = preg_replace('/[^0-9]/', '', $raw);
                    $normalized = $digits ? (strlen($digits) > 10 ? substr($digits, -10) : $digits) : null;

                    if ($normalized !== ($row->{$column} ?? null)) {
                        $updates[$row->id] = $normalized;
                    }
                }

                if (!$dry && !empty($updates)) {
                    foreach (array_chunk($updates, 500, true) as $batch) {
                        $ids = array_keys($batch);
                        $case = 'CASE id ';
                        foreach ($batch as $id => $phone) {
                            $val = $phone === null ? 'NULL' : ("'" . addslashes($phone) . "'");
                            $case .= "WHEN {$id} THEN {$val} ";
                        }
                        $case .= 'END';
                        DB::table($table)
                            ->whereIn('id', $ids)
                            ->update([$column => DB::raw($case)]);
                    }
                }

                $total += count($updates);
                $this->line("{$table}: prepared updates in chunk: " . count($updates));
            });

        if ($dry) {
            $this->info("{$table} DRY RUN: would update {$total} rows.");
        } else {
            $this->info("{$table}: updated {$total} rows.");
        }

        return true;
    }
}
