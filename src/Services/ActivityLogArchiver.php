<?php

namespace Dcplibrary\Notices\Services;

use Dcplibrary\Notices\Models\ActivityLog;
use Illuminate\Support\Facades\DB;

/**
 * Copies per-notification activity logs older than the retention window
 * into a gzipped archive, then deletes those rows from the live table.
 *
 * The live table is what the activity screens read. Staff backups do not
 * include this table, because a full copy of it does not fit in PHP memory.
 */
class ActivityLogArchiver
{
    /**
     * Archive activity logs older than $retentionDays.
     *
     * @return array{eligible: int, archived: int, path: null|string, dry_run: bool}
     */
    public function archive(int $retentionDays, bool $dryRun = false): array
    {
        $retentionDays = max(1, $retentionDays);
        $cutoff        = now()->subDays($retentionDays);
        $query         = $this->eligibleQuery($cutoff);
        $eligible      = (clone $query)->count();

        if ($dryRun || 0 === $eligible) {
            $this->record($dryRun, $eligible, 0, null, $retentionDays, $cutoff->toIso8601String());

            return [
                'eligible' => $eligible,
                'archived' => 0,
                'path'     => null,
                'dry_run'  => $dryRun,
            ];
        }

        $directory = storage_path('app/notices/activity-archives');
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Could not create the activity archive directory.');
        }

        $path = $directory . '/notification-activity-logs-' . now()->format('Ymd_His') . '.ndjson.gz';
        $gz   = gzopen($path, 'wb9');
        if (false === $gz) {
            throw new \RuntimeException('Could not open the activity archive for writing.');
        }

        $archived = 0;

        try {
            $query->orderBy('id')->chunkById(1000, function ($rows) use ($gz, &$archived): void {
                $ids = [];
                foreach ($rows as $row) {
                    $written = gzwrite($gz, json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
                    if (false === $written) {
                        throw new \RuntimeException('Could not write an activity log row to the archive.');
                    }
                    $ids[] = $row->id;
                }

                DB::table('notification_activity_logs')->whereIn('id', $ids)->delete();
                $archived += count($ids);
            });
        } finally {
            gzclose($gz);
        }

        $this->record(false, $eligible, $archived, $path, $retentionDays, $cutoff->toIso8601String());

        return [
            'eligible' => $eligible,
            'archived' => $archived,
            'path'     => $path,
            'dry_run'  => false,
        ];
    }

    /**
     * Rows whose event time, or created time when the event time is empty, is before $cutoff.
     */
    protected function eligibleQuery(\DateTimeInterface $cutoff)
    {
        return DB::table('notification_activity_logs')->where(function ($query) use ($cutoff): void {
            $query->where('occurred_at', '<', $cutoff)
                ->orWhere(function ($query) use ($cutoff): void {
                    $query->whereNull('occurred_at')->where('created_at', '<', $cutoff);
                })
            ;
        });
    }

    protected function record(bool $dryRun, int $eligible, int $archived, ?string $path, int $retentionDays, string $cutoff): void
    {
        ActivityLog::create([
            'action'      => $dryRun ? 'archive.activity_logs.dry_run' : 'archive.activity_logs',
            'description' => $dryRun
                ? 'Planned archive of notification activity logs (dry run)'
                : 'Archived notification activity logs older than the retention window',
            'meta' => [
                'dry_run'        => $dryRun,
                'eligible'       => $eligible,
                'archived'       => $archived,
                'path'           => $path,
                'retention_days' => $retentionDays,
                'cutoff'         => $cutoff,
            ],
        ]);
    }
}
