<?php

namespace Dcplibrary\Notices\Services;

use Dcplibrary\Notices\Models\PatronDeliveryPreference;
use Dcplibrary\Notices\Models\ProcessedFile;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * PatronDeliveryPreferenceImporter
 * 
 * Optimized bulk import service for voice_patrons and text_patrons files.
 * Tracks processed files to skip duplicates and provides progress feedback.
 */
class PatronDeliveryPreferenceImporter
{
    protected ShoutbombSubmissionParser $parser;
    protected ShoutbombFTPService $ftpService;

    public function __construct(ShoutbombSubmissionParser $parser, ShoutbombFTPService $ftpService)
    {
        $this->parser = $parser;
        $this->ftpService = $ftpService;
    }

    /**
     * Import patron delivery preferences from FTP for a single date.
     */
    public function importFromFTP(?Carbon $date = null, ?callable $progressCallback = null): array
    {
        $date = $date ?? now()->subDays(1);

        Log::info("Starting patron delivery preference import", [
            'date' => $date->format('Y-m-d'),
        ]);

        $results = [
            'voice_patrons' => 0,
            'text_patrons' => 0,
            'voice_new' => 0,
            'voice_changed' => 0,
            'voice_unchanged' => 0,
            'text_new' => 0,
            'text_changed' => 0,
            'text_unchanged' => 0,
            'voice_skipped' => false,
            'text_skipped' => false,
            'errors' => 0,
        ];

        try {
            if (!$this->ftpService->connect()) {
                throw new \Exception('Failed to connect to FTP');
            }

            // Import voice patrons
            $voiceResults = $this->importPatronType('voice', $date, $progressCallback);
            $results['voice_patrons'] = $voiceResults['total'];
            $results['voice_new'] = $voiceResults['new'];
            $results['voice_changed'] = $voiceResults['changed'];
            $results['voice_unchanged'] = $voiceResults['unchanged'];
            $results['voice_skipped'] = $voiceResults['skipped'];

            // Import text patrons
            $textResults = $this->importPatronType('text', $date, $progressCallback);
            $results['text_patrons'] = $textResults['total'];
            $results['text_new'] = $textResults['new'];
            $results['text_changed'] = $textResults['changed'];
            $results['text_unchanged'] = $textResults['unchanged'];
            $results['text_skipped'] = $textResults['skipped'];

            $this->ftpService->disconnect();

        } catch (\Exception $e) {
            Log::error("Patron delivery preference import failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $results['errors']++;
        }

        return $results;
    }

    /**
     * Import patron preferences from FTP across multiple dates.
     */
    public function importAllFromFTP(?Carbon $from = null, ?Carbon $to = null, ?callable $progressCallback = null): array
    {
        $config = config('notices.shoutbomb_submissions');
        $root = rtrim($config['root'] ?? '/', '/');
        $directory = $root === '' ? '/' : $root;

        if (!$this->ftpService->connect()) {
            Log::error('Patron import-all: failed to connect to FTP');
            return [
                'dates' => [],
                'totals' => [
                    'voice_patrons' => 0,
                    'text_patrons' => 0,
                    'errors' => 1,
                ],
            ];
        }

        // Discover all dates
        $files = $this->ftpService->listFiles($directory);
        $this->ftpService->disconnect();

        $dates = [];
        foreach ($files as $file) {
            $basename = basename($file);
            
            if (preg_match('/(?:voice|text)_patrons_submitted_(\d{4}-\d{2}-\d{2})_/', $basename, $m)) {
                $dates[$m[1]] = true;
            } elseif (preg_match('/(?:voice|text)_patrons_submitted_(\d{4})(\d{2})(\d{2})_/', $basename, $m)) {
                $dates["{$m[1]}-{$m[2]}-{$m[3]}"] = true;
            }
        }

        if (empty($dates)) {
            return [
                'dates' => [],
                'totals' => [
                    'voice_patrons' => 0,
                    'text_patrons' => 0,
                    'errors' => 0,
                ],
            ];
        }

        ksort($dates);

        // Filter by date range
        if ($from || $to) {
            $dates = array_filter(array_keys($dates), function ($dateString) use ($from, $to) {
                $d = Carbon::parse($dateString)->startOfDay();
                if ($from && $d->lt($from->copy()->startOfDay())) {
                    return false;
                }
                if ($to && $d->gt($to->copy()->startOfDay())) {
                    return false;
                }
                return true;
            });
        } else {
            $dates = array_keys($dates);
        }

        $aggregate = [
            'voice_patrons' => 0,
            'text_patrons' => 0,
            'voice_new' => 0,
            'text_new' => 0,
            'voice_changed' => 0,
            'text_changed' => 0,
            'errors' => 0,
        ];

        foreach ($dates as $dateString) {
            $date = Carbon::parse($dateString);

            Log::info('Patron import-all: importing date', ['date' => $dateString]);

            $results = $this->importFromFTP($date, $progressCallback);

            $aggregate['voice_patrons'] += $results['voice_patrons'];
            $aggregate['text_patrons'] += $results['text_patrons'];
            $aggregate['voice_new'] += $results['voice_new'];
            $aggregate['text_new'] += $results['text_new'];
            $aggregate['voice_changed'] += $results['voice_changed'];
            $aggregate['text_changed'] += $results['text_changed'];
            $aggregate['errors'] += $results['errors'];
        }

        return [
            'dates' => $dates,
            'totals' => $aggregate,
        ];
    }

    /**
     * Import a specific patron type (voice or text).
     */
    protected function importPatronType(string $type, Carbon $date, ?callable $progressCallback = null): array
    {
        $config = config('notices.shoutbomb_submissions');
        $root = rtrim($config['root'] ?? '/', '/');
        $directory = $root === '' ? '/' : $root;

        $patternDashed = "{$type}_patrons_submitted_{$date->format('Y-m-d')}";
        $patternNoDash = "{$type}_patrons_submitted_{$date->format('Ymd')}";

        $files = $this->ftpService->listFiles($directory);

        foreach ($files as $file) {
            $basename = basename($file);

            if (str_contains($basename, $patternDashed) || str_contains($basename, $patternNoDash)) {
                // Check if already processed
                if ($this->isFileProcessed($basename)) {
                    if ($progressCallback) {
                        $progressCallback(0, 0, $basename, true, true); // isNewFile=true, skipped=true
                    }

                    Log::info("Skipping already processed patron file", [
                        'file' => $basename,
                        'type' => $type,
                    ]);

                    return [
                        'total' => 0,
                        'new' => 0,
                        'changed' => 0,
                        'unchanged' => 0,
                        'skipped' => true,
                    ];
                }

                // Notify callback that we're starting
                if ($progressCallback) {
                    $progressCallback(0, 0, $basename, true, false); // isNewFile=true, skipped=false
                }

                $localPath = $this->ftpService->downloadFile($file);

                if ($localPath) {
                    $results = $this->processPatronFile($localPath, $basename, $type, $progressCallback);
                    
                    // Mark file as processed
                    $this->markFileAsProcessed($basename, $type, $results);

                    return $results;
                }
            }
        }

        Log::warning("No {$type} patron file found", [
            'patterns' => [$patternDashed, $patternNoDash],
        ]);

        return [
            'total' => 0,
            'new' => 0,
            'changed' => 0,
            'unchanged' => 0,
            'skipped' => false,
        ];
    }

    /**
     * Process patron file with optimized bulk operations.
     */
    protected function processPatronFile(
        string $filePath,
        string $filename,
        string $type,
        ?callable $progressCallback = null
    ): array {
        $patrons = $this->parser->parsePatronList($filePath);
        
        $deliveryMethod = match($type) {
            'voice' => 'voice',
            'text' => 'text',
            default => null,
        };

        if (!$deliveryMethod || empty($patrons)) {
            return [
                'total' => 0,
                'new' => 0,
                'changed' => 0,
                'unchanged' => 0,
                'skipped' => false,
            ];
        }

        $barcodes = array_keys($patrons);
        $timestamp = now();
        $total = count($patrons);

        // Load all existing preferences in one query
        $existingPreferences = PatronDeliveryPreference::whereIn('patron_barcode', $barcodes)
            ->get()
            ->keyBy('patron_barcode');

        $toInsert = [];
        $toUpdate = [];
        $unchangedIds = [];
        
        $newCount = 0;
        $changedCount = 0;
        $unchangedCount = 0;
        $processed = 0;

        // Process all patrons in memory
        foreach ($patrons as $barcode => $phone) {
            $existing = $existingPreferences->get($barcode);

            if (!$existing) {
                // NEW patron
                $toInsert[] = [
                    'patron_barcode' => $barcode,
                    'phone_number' => $phone,
                    'current_delivery_method' => $deliveryMethod,
                    'previous_delivery_method' => null,
                    'first_seen_at' => $timestamp,
                    'last_seen_at' => $timestamp,
                    'source_file' => $filename,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
                $newCount++;
            } elseif ($existing->current_delivery_method !== $deliveryMethod) {
                // CHANGED preference
                $toUpdate[] = [
                    'id' => $existing->id,
                    'previous_delivery_method' => $existing->current_delivery_method,
                    'current_delivery_method' => $deliveryMethod,
                    'phone_number' => $phone,
                    'last_seen_at' => $timestamp,
                    'source_file' => $filename,
                    'updated_at' => $timestamp,
                ];
                $changedCount++;
            } else {
                // UNCHANGED
                $unchangedIds[] = $existing->id;
                $unchangedCount++;
            }

            $processed++;

            // Progress callback every 1000 records
            if ($progressCallback && ($processed % 1000 === 0 || $processed === $total)) {
                $progressCallback($processed, $total, $filename, false, false);
            }
        }

        // Bulk insert new preferences
        if (!empty($toInsert)) {
            foreach (array_chunk($toInsert, 1000) as $chunk) {
                PatronDeliveryPreference::insert($chunk);
            }
        }

        // Bulk update changed preferences
        if (!empty($toUpdate)) {
            DB::transaction(function () use ($toUpdate) {
                foreach ($toUpdate as $update) {
                    PatronDeliveryPreference::where('id', $update['id'])->update([
                        'previous_delivery_method' => $update['previous_delivery_method'],
                        'current_delivery_method' => $update['current_delivery_method'],
                        'phone_number' => $update['phone_number'],
                        'last_seen_at' => $update['last_seen_at'],
                        'source_file' => $update['source_file'],
                        'updated_at' => $update['updated_at'],
                    ]);
                }
            });
        }

        // Bulk update unchanged (just timestamps)
        if (!empty($unchangedIds)) {
            foreach (array_chunk($unchangedIds, 1000) as $chunk) {
                PatronDeliveryPreference::whereIn('id', $chunk)->update([
                    'last_seen_at' => $timestamp,
                    'source_file' => $filename,
                    'updated_at' => $timestamp,
                ]);
            }
        }

        Log::info("Patron delivery preferences processed", [
            'type' => $type,
            'file' => $filename,
            'total' => $total,
            'new' => $newCount,
            'changed' => $changedCount,
            'unchanged' => $unchangedCount,
        ]);

        return [
            'total' => $total,
            'new' => $newCount,
            'changed' => $changedCount,
            'unchanged' => $unchangedCount,
            'skipped' => false,
        ];
    }

    /**
     * Check if a file has already been processed.
     */
    protected function isFileProcessed(string $filename): bool
    {
        return ProcessedFile::where('filename', $filename)
            ->where('file_type', 'patron_list')
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * Mark a file as processed.
     */
    protected function markFileAsProcessed(string $filename, string $type, array $results): void
    {
        ProcessedFile::create([
            'filename' => $filename,
            'file_type' => 'patron_list',
            'category' => $type,
            'status' => 'completed',
            'records_processed' => $results['total'],
            'records_new' => $results['new'],
            'records_updated' => $results['changed'],
            'records_unchanged' => $results['unchanged'],
            'processed_at' => now(),
        ]);
    }

    /**
     * Get import statistics.
     */
    public function getStats(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = PatronDeliveryPreference::query();

        if ($startDate && $endDate) {
            $query->whereBetween('last_seen_at', [$startDate, $endDate]);
        }

        return [
            'total' => $query->count(),
            'by_delivery_method' => $query->clone()
                ->select('current_delivery_method', DB::raw('count(*) as count'))
                ->groupBy('current_delivery_method')
                ->pluck('count', 'current_delivery_method')
                ->toArray(),
            'recent_changes' => PatronDeliveryPreference::whereNotNull('previous_delivery_method')
                ->where('updated_at', '>=', $startDate ?? now()->subDays(30))
                ->count(),
        ];
    }
}
