<?php

namespace Dcplibrary\Notices\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The docs must name settings the package actually reads.
 *
 * Any POLARIS_*, MICROSOFT_* or NOTICES_* variable shown in the README or
 * docs has to appear in an env() call in config/ or src/.
 */
class DocumentedSettingsTest extends TestCase
{
    #[Test]
    public function documented_package_settings_exist_in_code(): void
    {
        $root = dirname(__DIR__, 2);

        $read = [];
        foreach ($this->files($root . '/config', 'php') + $this->files($root . '/src', 'php') as $file) {
            preg_match_all("/env\\('([A-Z0-9_]+)'/", (string) file_get_contents($file), $m);
            $read = array_merge($read, $m[1]);
        }
        // Set by the test suite itself, not read through env().
        $read[] = 'NOTICES_TEST_DB';

        $documented = [];
        $docs       = $this->files($root . '/docs', 'md') + $this->files($root . '/docs', 'example');
        $docs[]     = $root . '/README.md';
        foreach ($docs as $file) {
            $text = (string) file_get_contents($file);
            // KEY=value lines, and env('KEY') calls in quoted config snippets.
            preg_match_all('/\b((?:POLARIS|MICROSOFT|NOTICES)_[A-Z0-9_]*[A-Z0-9])=/', $text, $m);
            preg_match_all("/env\\('((?:POLARIS|MICROSOFT|NOTICES)_[A-Z0-9_]+)'/", $text, $e);
            $documented = array_merge($documented, $m[1], $e[1]);
        }

        $unknown = array_values(array_unique(array_diff($documented, $read)));
        sort($unknown);

        $this->assertSame([], $unknown, 'The docs mention settings the package never reads.');
    }

    /** @return array<string, string> */
    private function files(string $dir, string $extension): array
    {
        $found = [];
        $it    = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.' . $extension)) {
                $found[$file->getPathname()] = $file->getPathname();
            }
        }

        return $found;
    }
}
