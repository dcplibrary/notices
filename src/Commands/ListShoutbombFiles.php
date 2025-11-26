<?php

namespace Dcplibrary\Notices\Commands;

use Dcplibrary\Notices\Services\ShoutbombFTPService;
use Exception;
use Illuminate\Console\Command;

class ListShoutbombFiles extends Command
{
    protected $signature = 'notices:list-shoutbomb-files
                            {--path=/ : Directory path to list}
                            {--recursive : List files recursively}';

    protected $description = 'List files on the Shoutbomb FTP server for debugging';

    public function handle(ShoutbombFTPService $ftpService): int
    {
        $this->info('📁 Listing files on Shoutbomb FTP server...');
        $this->newLine();

        if (!config('notices.shoutbomb.enabled')) {
            $this->warn('⚠️  Shoutbomb is disabled in configuration.');

            return Command::SUCCESS;
        }

        if (!$ftpService->connect()) {
            $this->error('❌ Failed to connect to FTP server');

            return Command::FAILURE;
        }

        try {
            $path = $this->option('path');
            $this->line("→ Listing files in: {$path}");
            $this->newLine();

            $files = $ftpService->listFiles($path);

            if (empty($files)) {
                $this->warn('No files found in this directory');

                return Command::SUCCESS;
            }

            $this->info("Found " . count($files) . " files/directories:");
            $this->newLine();

            // Group files by pattern
            $patterns = [
                'text_patrons_submitted' => [],
                'voice_patrons_submitted' => [],
                'holds_submitted' => [],
                'overdue_submitted' => [],
                'renew_submitted' => [],
                'monthly' => [],
                'weekly' => [],
                'daily' => [],
                'other' => [],
            ];

            foreach ($files as $file) {
                $basename = basename($file);

                $matched = false;
                foreach ($patterns as $pattern => &$group) {
                    if (str_contains($basename, $pattern)) {
                        $group[] = $basename;
                        $matched = true;
                        break;
                    }
                }

                if (!$matched) {
                    $patterns['other'][] = $basename;
                }
            }

            // Display grouped files
            foreach ($patterns as $pattern => $fileList) {
                if (!empty($fileList)) {
                    $this->line("<fg=yellow>" . ucwords(str_replace('_', ' ', $pattern)) . ":</>");
                    foreach ($fileList as $file) {
                        $this->line("  • {$file}");
                    }
                    $this->newLine();
                }
            }

            // Show full paths if verbose
            if ($this->option('verbose')) {
                $this->line('─────────────────────────────────────────');
                $this->info('Full paths:');
                foreach ($files as $file) {
                    $this->line($file);
                }
            }

        } catch (Exception $e) {
            $this->error("Error: " . $e->getMessage());

            return Command::FAILURE;
        } finally {
            $ftpService->disconnect();
        }

        return Command::SUCCESS;
    }
}
