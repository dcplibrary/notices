<?php

namespace Dcplibrary\Notices\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizePhonesCommand extends Command
{
    protected $signature = 'notices:normalize-phones
                            {--dry-run : Show what would change without writing}
                            {--fast-sql : Use a single SQL UPDATE (MySQL 8+ REGEXP_REPLACE)}';

    protected $description = 'Normalize Voice/SMS phone values from delivery_string (trim at @, digits-only, last 10)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $fast = (bool) $this->option('fast-sql');

        $this->info('Normalizing Voice/SMS phone values in notification_logs...');

        if ($fast) {
            $sql = "UPDATE notification_logs\n" .
                   "SET phone = RIGHT(REGEXP_REPLACE(SUBSTRING_INDEX(delivery_string, '@', 1), '[^0-9]', ''), 10)\n" .
                   "WHERE delivery_option_id IN (3,8)\n" .
                   "  AND delivery_string IS NOT NULL";

            if ($dry) {
                $this->line('DRY RUN (fast-sql):');
                $this->line($sql);
                return Command::SUCCESS;
            }

            try {
                $affected = DB::update($sql);
                $this->info("Updated {$affected} rows using fast SQL.");
                return Command::SUCCESS;
            } catch (\Throwable $e) {
                $this->warn('Fast SQL path failed (missing REGEXP_REPLACE or incompatible SQL). Falling back to chunked PHP mode.');
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
                $this->line("Prepared updates in chunk: " . count($updates));
            });

        if ($dry) {
            $this->info("DRY RUN: would update {$total} rows.");
        } else {
            $this->info("Updated {$total} rows.");
        }

        return Command::SUCCESS;
    }
}