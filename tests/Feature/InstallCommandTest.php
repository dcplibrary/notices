<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Dcplibrary\Notices\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class InstallCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/notices-install-' . uniqid();
        mkdir($this->dir);
        $this->app->useEnvironmentPath($this->dir);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/.env');
        @rmdir($this->dir);

        parent::tearDown();
    }

    #[Test]
    public function it_writes_the_settings_the_package_reads(): void
    {
        file_put_contents($this->dir . '/.env', "APP_NAME=\"Host App\"\nMICROSOFT_MAILBOX=old@example.org\nMICROSOFT_CLIENT_SECRET=keep-me\n");

        $this->artisan('notices:install', ['--no-migrate' => true, '--no-seed' => true])
            ->expectsQuestion('Entra tenant ID (MICROSOFT_TENANT_ID)', 'tenant-1')
            ->expectsQuestion('App registration client ID (MICROSOFT_CLIENT_ID)', 'client-1')
            ->expectsQuestion('App registration client secret (MICROSOFT_CLIENT_SECRET, blank to keep the saved value)', '')
            ->expectsQuestion('Mailbox that receives the reports (MICROSOFT_MAILBOX)', 'reports@example.org')
            ->expectsQuestion('Mail folder holding Shoutbomb reports (MICROSOFT_FOLDER_SHOUTBOMB)', 'Inbox/Shoutbomb Reports')
            ->expectsQuestion('Only accept reports from these senders (comma-separated, blank for any) (MICROSOFT_EMAIL_FROM)', '')
            ->expectsConfirmation('Fetch export files from an FTP server?', 'yes')
            ->expectsQuestion('FTP host (POLARIS_FTP_HOST)', 'ftp.example.org')
            ->expectsQuestion('FTP port (POLARIS_FTP_PORT)', '21')
            ->expectsQuestion('FTP username (POLARIS_FTP_USERNAME)', 'notices')
            ->expectsQuestion('FTP password (POLARIS_FTP_PASSWORD)', 's3cret#1')
            ->expectsQuestion('Folder on the FTP server (POLARIS_FTP_PATH)', '/exports')
            ->expectsQuestion('Timezone of the export file names (NOTICES_TIMEZONE)', 'America/Chicago')
            ->assertSuccessful();

        $written = \Dotenv\Dotenv::parse((string) file_get_contents($this->dir . '/.env'));

        $this->assertSame([
            'APP_NAME'                   => 'Host App',
            'MICROSOFT_MAILBOX'          => 'reports@example.org',
            'MICROSOFT_CLIENT_SECRET'    => 'keep-me',
            'MICROSOFT_TENANT_ID'        => 'tenant-1',
            'MICROSOFT_CLIENT_ID'        => 'client-1',
            'MICROSOFT_FOLDER_SHOUTBOMB' => 'Inbox/Shoutbomb Reports',
            'POLARIS_USE_FTP'            => 'true',
            'POLARIS_FTP_HOST'           => 'ftp.example.org',
            'POLARIS_FTP_PORT'           => '21',
            'POLARIS_FTP_USERNAME'       => 'notices',
            'POLARIS_FTP_PASSWORD'       => 's3cret#1',
            'POLARIS_FTP_PATH'           => '/exports',
            'NOTICES_TIMEZONE'           => 'America/Chicago',
        ], $written);

        // An optional setting left blank is not added to the file.
        $this->assertArrayNotHasKey('MICROSOFT_EMAIL_FROM', $written);
    }

    #[Test]
    public function it_skips_the_ftp_questions_when_ftp_is_not_used(): void
    {
        $this->artisan('notices:install', ['--no-migrate' => true, '--no-seed' => true])
            ->expectsQuestion('Entra tenant ID (MICROSOFT_TENANT_ID)', '')
            ->expectsQuestion('App registration client ID (MICROSOFT_CLIENT_ID)', '')
            ->expectsQuestion('App registration client secret (MICROSOFT_CLIENT_SECRET)', '')
            ->expectsQuestion('Mailbox that receives the reports (MICROSOFT_MAILBOX)', '')
            ->expectsQuestion('Mail folder holding Shoutbomb reports (MICROSOFT_FOLDER_SHOUTBOMB)', 'Shoutbomb_Reports')
            ->expectsQuestion('Only accept reports from these senders (comma-separated, blank for any) (MICROSOFT_EMAIL_FROM)', '')
            ->expectsConfirmation('Fetch export files from an FTP server?', 'no')
            ->expectsQuestion('Timezone of the export file names (NOTICES_TIMEZONE)', 'America/Chicago')
            ->assertSuccessful();

        $this->assertSame([
            'MICROSOFT_FOLDER_SHOUTBOMB' => 'Shoutbomb_Reports',
            'POLARIS_USE_FTP'            => 'false',
            'NOTICES_TIMEZONE'           => 'America/Chicago',
        ], \Dotenv\Dotenv::parse((string) file_get_contents($this->dir . '/.env')));
    }

    #[Test]
    public function every_setting_it_writes_is_one_the_config_reads(): void
    {
        $command = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Console/InstallCommand.php');
        preg_match_all("/'((?:MICROSOFT|POLARIS|NOTICES)_[A-Z0-9_]+)'/", $command, $written);

        $config = (string) file_get_contents(dirname(__DIR__, 2) . '/config/notices.php');
        preg_match_all("/env\\('([A-Z0-9_]+)'/", $config, $read);

        $this->assertNotEmpty($written[1]);
        $this->assertSame([], array_values(array_diff(array_unique($written[1]), $read[1])));
    }
}
