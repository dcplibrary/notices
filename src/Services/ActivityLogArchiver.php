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
     * When $keepUnresolved is true, logs for a notification that still has an
     * open patron delivery issue (resolved_at is empty) are left in the live table.
     *
     * @return array{eligible: int, archived: int, path: null|string, dry_run: bool, keep_unresolved: bool}
     */
    public function archive(int $retentionDays, bool $dryRun = false, bool $keepUnresolved = false): array
    {
        $retentionDays = max(1, $retentionDays);
        $cutoff        = now()->subDays($retentionDays);
        $query         = $this->eligibleQuery($cutoff, $keepUnresolved);
        $eligible      = (clone $query)->count();

        if ($dryRun || 0 === $eligible) {
            $this->record($dryRun, $eligible, 0, null, $retentionDays, $cutoff->toIso8601String(), $keepUnresolved);

            return [
                'eligible'        => $eligible,
                'archived'        => 0,
                'path'            => null,
                'dry_run'         => $dryRun,
                'keep_unresolved' => $keepUnresolved,
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

        $this->record(false, $eligible, $archived, $path, $retentionDays, $cutoff->toIso8601String(), $keepUnresolved);

        return [
            'eligible'        => $eligible,
            'archived'        => $archived,
            'path'            => $path,
            'dry_run'         => false,
            'keep_unresolved' => $keepUnresolved,
        ];
    }

    /**
     * Rows whose event time, or created time when the event time is empty, is before $cutoff.
     *
     * With $keepUnresolved, a row is skipped when its notification still has a
     * patron delivery issue with no resolved_at.
     */
    protected function eligibleQuery(\DateTimeInterface $cutoff, bool $keepUnresolved)
    {
        $query = DB::table('notification_activity_logs')->where(function ($query) use ($cutoff): void {
            $query->where('occurred_at', '<', $cutoff)
                ->orWhere(function ($query) use ($cutoff): void {
                    $query->whereNull('occurred_at')->where('created_at', '<', $cutoff);
                })
            ;
        });

        if ($keepUnresolved) {
            $query->whereNotExists(function ($open) {
                $open->select(DB::raw('1'))
                    ->from('patron_delivery_issues')
                    ->whereColumn('patron_delivery_issues.notification_id', 'notification_activity_logs.notification_id')
                    ->whereNull('patron_delivery_issues.resolved_at')
                ;
            });
        }

        return $query;
    }

    protected function record(bool $dryRun, int $eligible, int $archived, ?string $path, int $retentionDays, string $cutoff, bool $keepUnresolved): void
    {
        ActivityLog::create([
            'action'      => $dryRun ? 'archive.activity_logs.dry_run' : 'archive.activity_logs',
            'description' => $dryRun
                ? 'Planned archive of notification activity logs (dry run)'
                : 'Archived notification activity logs older than the retention window',
            'meta' => [
                'dry_run'         => $dryRun,
                'eligible'        => $eligible,
                'archived'        => $archived,
                'path'            => $path,
                'retention_days'  => $retentionDays,
                'cutoff'          => $cutoff,
                'keep_unresolved' => $keepUnresolved,
            ],
        ]);
    }
}
