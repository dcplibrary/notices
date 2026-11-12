<?php

namespace Dcplibrary\Notices\Services\Import;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use League\Flysystem\Filesystem;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;

/**
 * Thin wrapper around Flysystem's FTP adapter used by the notices
 * package to browse and download export files from the Polaris FTP
 * server.
 *
 * Connection details (host, port, credentials, root, SSL, passive
 * mode, timeout) are driven entirely from the notices.polaris.ftp
 * configuration array so host applications can override them via env.
 */
class FtpImportService
{
    protected Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = $this->createFilesystem();
    }

    /**
     * List available files in the FTP root, optionally filtered by a simple pattern (e.g. holds_*_*.txt).
     */
    public function listAvailableFiles(?string $pattern = null): array
    {
        try {
            $listing = $this->filesystem->listContents('/', false);

            $files = [];
            foreach ($listing as $item) {
                if ($item->isFile()) {
                    $path = $item->path();

                    if ($pattern && !$this->matchesPattern(basename($path), $pattern)) {
                        continue;
                    }

                    $files[] = [
                        'path'      => $path,
                        'name'      => basename($path),
                        'size'      => $item->fileSize()     ?? 0,
                        'timestamp' => $item->lastModified() ?? null,
                    ];
                }
            }

            return $files;
        } catch (\Throwable $e) {
            \Log::warning('FTP connection failed while listing files', [
                'pattern'   => $pattern,
                'error'     => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return [];
        }
    }

    /**
     * Download all files matching a pattern into a local directory.
     *
     * @return array<int, array{remote:string,local:string,size:int}>
     */
    public function downloadFilesByPattern(string $pattern, string $localDirectory): array
    {
        $files      = $this->listAvailableFiles($pattern);
        $downloaded = [];

        foreach ($files as $file) {
            $localPath = rtrim($localDirectory, '/') . '/' . $file['name'];

            if ($this->downloadFile($file['path'], $localPath)) {
                $downloaded[] = [
                    'remote' => $file['path'],
                    'local'  => $localPath,
                    'size'   => $file['size'],
                ];
            }
        }

        return $downloaded;
    }

    /**
     * Download a single file from FTP to a local path.
     */
    public function downloadFile(string $remotePath, string $localPath): bool
    {
        try {
            $contents = $this->filesystem->read($remotePath);

            $directory = \dirname($localPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($localPath, $contents);

            Log::info('FTP file downloaded', [
                'remote' => $remotePath,
                'local'  => $localPath,
                'size'   => \is_string($contents) ? strlen($contents) : 0,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('FTP download failed', [
                'remote' => $remotePath,
                'error'  => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get all export files for a specific date (YYYY-MM-DD) by filename substring.
     *
     * This is a helper if we later want to constrain by date in the filename.
     */
    public function getExportsForDate(Carbon $date, string $pattern): array
    {
        $dateStr = $date->format('Y-m-d');
        $files   = $this->listAvailableFiles($pattern);

        return \array_values(\array_filter($files, static function (array $file) use ($dateStr): bool {
            return str_contains($file['name'], $dateStr);
        }));
    }

    /**
     * Create an FTP filesystem connection from config('notices.polaris.ftp').
     */
    protected function createFilesystem(): Filesystem
    {
        $options = FtpConnectionOptions::fromArray([
            'host'     => config('notices.polaris.ftp.host'),
            'port'     => config('notices.polaris.ftp.port', 21),
            'username' => config('notices.polaris.ftp.username'),
            'password' => config('notices.polaris.ftp.password'),
            'root'     => config('notices.polaris.ftp.root', '/'),
            'ssl'      => config('notices.polaris.ftp.ssl', false),
            'passive'  => config('notices.polaris.ftp.passive', true),
            'timeout'  => config('notices.polaris.ftp.timeout', 30),
        ]);

        $adapter = new FtpAdapter($options);

        return new Filesystem($adapter);
    }

    protected function matchesPattern(string $filename, string $pattern): bool
    {
        // Convert simple glob-style pattern to regex (only * supported here).
        $regex = '/^' . str_replace(['.', '*'], ['\.', '.*'], $pattern) . '$/i';

        return (bool) preg_match($regex, $filename);
    }
}
