<?php

namespace Dcplibrary\Notices\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ShoutbombFTPService
{
    protected $connection;
    protected $parser;

    public function __construct(ShoutbombFileParser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Connect to Shoutbomb FTP server.
     */
    public function connect(): bool
    {
        if (!config('notices.shoutbomb.enabled')) {
            Log::info('Shoutbomb FTP is disabled');
            return false;
        }

        $config = config('notices.shoutbomb.ftp');

        try {
            if ($config['ssl']) {
                $this->connection = ftp_ssl_connect($config['host'], $config['port'], $config['timeout']);
            } else {
                $this->connection = ftp_connect($config['host'], $config['port'], $config['timeout']);
            }

            if (!$this->connection) {
                throw new \Exception('Could not create FTP connection');
            }

            $login = ftp_login($this->connection, $config['username'], $config['password']);

            if (!$login) {
                throw new \Exception('FTP login failed');
            }

            if ($config['passive']) {
                ftp_pasv($this->connection, true);
            }

            Log::info('Successfully connected to Shoutbomb FTP');
            return true;

        } catch (\Exception $e) {
            Log::error('Failed to connect to Shoutbomb FTP', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Disconnect from FTP server.
     */
    public function disconnect(): void
    {
        if ($this->connection) {
            ftp_close($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Download and import monthly reports.
     */
    public function importMonthlyReports(): array
    {
        if (!$this->connect()) {
            return ['success' => false, 'error' => 'Connection failed'];
        }

        try {
            $remotePath = config('notices.shoutbomb.paths.monthly_reports');
            $files = ftp_nlist($this->connection, $remotePath);

            if ($files === false) {
                throw new \Exception('Could not list files in monthly reports directory');
            }

            $stats = ['files_processed' => 0, 'total_imported' => []];

            foreach ($files as $file) {
                // Skip . and ..
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $remoteFile = $remotePath . '/' . basename($file);
                $localPath = $this->downloadFile($remoteFile);

                if ($localPath) {
                    $data = $this->parser->parseMonthlyReport($localPath);
                    $imported = $this->parser->importParsedData($data, 'Monthly');

                    $stats['files_processed']++;
                    $stats['total_imported'][] = $imported;

                    Log::info("Imported monthly report: {$file}", $imported);
                }
            }

            return ['success' => true, 'stats' => $stats];

        } catch (\Exception $e) {
            Log::error('Error importing monthly reports', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Download and import weekly reports.
     */
    public function importWeeklyReports(): array
    {
        if (!$this->connect()) {
            return ['success' => false, 'error' => 'Connection failed'];
        }

        try {
            $remotePath = config('notices.shoutbomb.paths.weekly_reports');
            $files = ftp_nlist($this->connection, $remotePath);

            if ($files === false) {
                throw new \Exception('Could not list files in weekly reports directory');
            }

            $stats = ['files_processed' => 0, 'total_imported' => []];

            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $remoteFile = $remotePath . '/' . basename($file);
                $localPath = $this->downloadFile($remoteFile);

                if ($localPath) {
                    $data = $this->parser->parseWeeklyReport($localPath);
                    $imported = $this->parser->importParsedData($data, 'Weekly');

                    $stats['files_processed']++;
                    $stats['total_imported'][] = $imported;

                    Log::info("Imported weekly report: {$file}", $imported);
                }
            }

            return ['success' => true, 'stats' => $stats];

        } catch (\Exception $e) {
            Log::error('Error importing weekly reports', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Download and import daily invalid phone reports.
     */
    public function importDailyInvalidReports(): array
    {
        if (!$this->connect()) {
            return ['success' => false, 'error' => 'Connection failed'];
        }

        try {
            $remotePath = config('notices.shoutbomb.paths.daily_invalid');
            $files = ftp_nlist($this->connection, $remotePath);

            if ($files === false) {
                throw new \Exception('Could not list files in daily invalid directory');
            }

            $stats = ['files_processed' => 0, 'total_imported' => []];

            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $remoteFile = $remotePath . '/' . basename($file);
                $localPath = $this->downloadFile($remoteFile);

                if ($localPath) {
                    $data = $this->parser->parseDailyInvalidReport($localPath);
                    $imported = $this->parser->importParsedData($data, 'Daily');

                    $stats['files_processed']++;
                    $stats['total_imported'][] = $imported;

                    Log::info("Imported daily invalid report: {$file}", $imported);
                }
            }

            return ['success' => true, 'stats' => $stats];

        } catch (\Exception $e) {
            Log::error('Error importing daily invalid reports', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Download and import daily undelivered voice reports.
     */
    public function importDailyUndeliveredReports(): array
    {
        if (!$this->connect()) {
            return ['success' => false, 'error' => 'Connection failed'];
        }

        try {
            $remotePath = config('notices.shoutbomb.paths.daily_undelivered');
            $files = ftp_nlist($this->connection, $remotePath);

            if ($files === false) {
                throw new \Exception('Could not list files in daily undelivered directory');
            }

            $stats = ['files_processed' => 0, 'total_imported' => []];

            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $remoteFile = $remotePath . '/' . basename($file);
                $localPath = $this->downloadFile($remoteFile);

                if ($localPath) {
                    $data = $this->parser->parseDailyUndeliveredReport($localPath);
                    $imported = $this->parser->importParsedData($data, 'Daily');

                    $stats['files_processed']++;
                    $stats['total_imported'][] = $imported;

                    Log::info("Imported daily undelivered report: {$file}", $imported);
                }
            }

            return ['success' => true, 'stats' => $stats];

        } catch (\Exception $e) {
            Log::error('Error importing daily undelivered reports', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Download a file from FTP to local storage.
     */
    public function downloadFile(string $remoteFile): ?string
    {
        try {
            $localDir = config('notices.shoutbomb.local_storage_path');

            if (!is_dir($localDir)) {
                mkdir($localDir, 0755, true);
            }

            $localFile = $localDir . '/' . basename($remoteFile);

            $downloaded = ftp_get($this->connection, $localFile, $remoteFile, FTP_BINARY);

            if (!$downloaded) {
                throw new \Exception("Failed to download {$remoteFile}");
            }

            Log::info("Downloaded file: {$remoteFile} to {$localFile}");
            return $localFile;

        } catch (\Exception $e) {
            Log::error("Error downloading file: {$remoteFile}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * List files in a directory on the FTP server.
     */
    public function listFiles(string $directory = '/'): array
    {
        try {
            if (!$this->connection) {
                throw new \Exception('Not connected to FTP server');
            }

            $files = ftp_nlist($this->connection, $directory);

            if ($files === false) {
                return [];
            }

            // Filter out . and ..
            return array_filter($files, function($file) {
                $basename = basename($file);
                return $basename !== '.' && $basename !== '..';
            });

        } catch (\Exception $e) {
            Log::error("Error listing files in {$directory}", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Test FTP connection.
     */
    public function testConnection(): array
    {
        if ($this->connect()) {
            $this->disconnect();
            return [
                'success' => true,
                'message' => 'Successfully connected to Shoutbomb FTP',
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to connect to Shoutbomb FTP',
        ];
    }
}
