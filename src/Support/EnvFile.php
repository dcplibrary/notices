<?php

namespace Dcplibrary\Notices\Support;

/**
 * Reads and updates a .env file without disturbing the lines it does not own.
 *
 * Existing keys are changed in place, new keys are appended, and comments,
 * blank lines and unrelated settings are left exactly as they were.
 */
class EnvFile
{
    /** @var list<string> */
    private array $lines;

    public function __construct(private readonly string $path)
    {
        $contents    = is_file($path) ? (string) file_get_contents($path) : '';
        $this->lines = '' === $contents ? [] : preg_split('/\r\n|\n|\r/', rtrim($contents, "\r\n"));
    }

    public function has(string $key): bool
    {
        return null !== $this->lineIndex($key);
    }

    /**
     * The value as written in the file, with surrounding quotes removed.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $index = $this->lineIndex($key);
        if (null === $index) {
            return $default;
        }

        $value = trim(substr($this->lines[$index], strpos($this->lines[$index], '=') + 1));

        if (strlen($value) >= 2 && '"' === $value[0] && '"' === substr($value, -1)) {
            return str_replace(['\"', '\\\\'], ['"', '\\'], substr($value, 1, -1));
        }

        if (strlen($value) >= 2 && "'" === $value[0] && "'" === substr($value, -1)) {
            return substr($value, 1, -1);
        }

        // An unquoted value ends at an inline comment.
        $value = preg_replace('/\s+#.*$/', '', $value) ?? $value;

        return '' === $value ? $default : $value;
    }

    public function set(string $key, string $value): void
    {
        $line  = $key . '=' . self::quote($value);
        $index = $this->lineIndex($key);

        if (null === $index) {
            $this->lines[] = $line;

            return;
        }

        $this->lines[$index] = $line;
    }

    public function save(): void
    {
        file_put_contents($this->path, implode("\n", $this->lines) . "\n");
    }

    /**
     * Quote a value when leaving it bare would change its meaning.
     */
    public static function quote(string $value): string
    {
        if ('' === $value) {
            return '';
        }

        if (!preg_match('/[\s#"\'$\\\=]/', $value)) {
            return $value;
        }

        return '"' . str_replace(['\\', '"', '$'], ['\\\\', '\"', '\$'], $value) . '"';
    }

    private function lineIndex(string $key): ?int
    {
        foreach ($this->lines as $index => $line) {
            if (preg_match('/^\s*(?:export\s+)?' . preg_quote($key, '/') . '\s*=/', $line)) {
                return $index;
            }
        }

        return null;
    }
}
