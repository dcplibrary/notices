<?php

namespace Dcplibrary\Notices\Tests\Unit;

use Dcplibrary\Notices\Support\EnvFile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EnvFileTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'env');
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    #[Test]
    public function it_updates_existing_keys_in_place_and_leaves_other_lines_alone(): void
    {
        file_put_contents($this->path, "APP_NAME=\"My App\"\n# mail\nMICROSOFT_MAILBOX=old@example.org\n\nDB_HOST=db\n");

        $env = new EnvFile($this->path);
        $env->set('MICROSOFT_MAILBOX', 'new@example.org');
        $env->set('POLARIS_USE_FTP', 'true');
        $env->save();

        $this->assertSame(
            "APP_NAME=\"My App\"\n# mail\nMICROSOFT_MAILBOX=new@example.org\n\nDB_HOST=db\nPOLARIS_USE_FTP=true\n",
            file_get_contents($this->path)
        );
    }

    #[Test]
    public function it_reads_quoted_bare_and_commented_values(): void
    {
        file_put_contents($this->path, "A=\"two words\"\nB='single'\nC=bare # note\nD=\n");

        $env = new EnvFile($this->path);

        $this->assertSame('two words', $env->get('A'));
        $this->assertSame('single', $env->get('B'));
        $this->assertSame('bare', $env->get('C'));
        $this->assertNull($env->get('D'));
        $this->assertTrue($env->has('D'));
        $this->assertSame('fallback', $env->get('MISSING', 'fallback'));
        $this->assertFalse($env->has('MISSING'));
    }

    #[Test]
    public function it_quotes_values_that_would_otherwise_break_the_file(): void
    {
        $env = new EnvFile($this->path);
        $env->set('NAME', 'Meeting Room Signs');
        $env->set('SECRET', 'p@ss#w0rd$"x');
        $env->set('PLAIN', 'abc-123_/.:');
        $env->save();

        $again = new EnvFile($this->path);
        $this->assertSame('Meeting Room Signs', $again->get('NAME'));
        $this->assertSame('abc-123_/.:', $again->get('PLAIN'));
        $this->assertStringContainsString('NAME="Meeting Room Signs"', (string) file_get_contents($this->path));
        $this->assertStringContainsString('PLAIN=abc-123_/.:', (string) file_get_contents($this->path));

        // What matters is that Laravel's own parser reads back exactly what was set.
        $parsed = \Dotenv\Dotenv::parse((string) file_get_contents($this->path));
        $this->assertSame('p@ss#w0rd$"x', $parsed['SECRET']);
        $this->assertSame('Meeting Room Signs', $parsed['NAME']);
    }

    #[Test]
    public function it_creates_the_file_when_it_does_not_exist(): void
    {
        unlink($this->path);

        $env = new EnvFile($this->path);
        $env->set('NOTICES_TIMEZONE', 'America/Chicago');
        $env->save();

        $this->assertSame("NOTICES_TIMEZONE=America/Chicago\n", file_get_contents($this->path));
    }
}
