<?php

namespace Dcplibrary\Notices\Console;

use Dcplibrary\Notices\Support\EnvFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Sets up the package in a host application.
 *
 * Asks for the settings the package reads, writes them to the application's
 * .env file, and optionally runs the migrations and seeds the lookup tables.
 * It only touches the keys listed here; everything else in .env is left alone.
 *
 * Safe to run again: existing values are offered as defaults, and a secret
 * left blank keeps the value already in the file.
 */
class InstallCommand extends Command
{
    protected $signature = 'notices:install
        {--no-migrate : Do not run database migrations}
        {--no-seed : Do not seed the Polaris lookup tables}
        {--publish-config : Also publish config/notices.php into the application}';

    protected $description = 'Set up the notices package: write its .env settings, migrate and seed';

    public function handle(): int
    {
        $path = $this->laravel->environmentFilePath();
        $env  = new EnvFile($path);

        $this->info('Notices setup');
        $this->line('Settings are written to ' . $path);
        $this->line('Press Enter to keep the value shown. Leave a secret blank to keep the one already saved.');
        $this->newLine();

        $changes = $this->collectMicrosoft($env) + $this->collectPolaris($env) + $this->collectGeneral($env);

        foreach ($changes as $key => $value) {
            $env->set($key, $value);
        }
        $env->save();

        $this->newLine();
        $this->info('Saved ' . count($changes) . ' setting(s) to .env.');

        if ($this->option('publish-config')) {
            $this->call('vendor:publish', ['--tag' => 'notices-config', '--force' => true]);
        }

        $this->ensureStorageDirectory();

        if (!$this->option('no-migrate') && $this->confirm('Run database migrations now?', true)) {
            $this->call('migrate', ['--force' => true]);
        }

        if (!$this->option('no-seed') && $this->confirm('Seed the Polaris lookup tables now?', true)) {
            $this->call('notices:seed');
        }

        $this->nextSteps();

        return self::SUCCESS;
    }

    /** @return array<string, string> */
    private function collectMicrosoft(EnvFile $env): array
    {
        $this->comment('Microsoft Graph (reads the report emails)');

        $changes = [];
        $this->askFor($changes, $env, 'MICROSOFT_TENANT_ID', 'Entra tenant ID');
        $this->askFor($changes, $env, 'MICROSOFT_CLIENT_ID', 'App registration client ID');
        $this->askForSecret($changes, $env, 'MICROSOFT_CLIENT_SECRET', 'App registration client secret');
        $this->askFor($changes, $env, 'MICROSOFT_MAILBOX', 'Mailbox that receives the reports');
        $this->askFor($changes, $env, 'MICROSOFT_FOLDER_SHOUTBOMB', 'Mail folder holding Shoutbomb reports', 'Shoutbomb_Reports');
        $this->askFor($changes, $env, 'MICROSOFT_EMAIL_FROM', 'Only accept reports from these senders (comma-separated, blank for any)');
        $this->newLine();

        return $changes;
    }

    /** @return array<string, string> */
    private function collectPolaris(EnvFile $env): array
    {
        $this->comment('Polaris exports');

        $changes = [];
        $useFtp  = $this->confirm(
            'Fetch export files from an FTP server?',
            'true' === strtolower((string) $env->get('POLARIS_USE_FTP', 'false'))
        );
        $changes['POLARIS_USE_FTP'] = $useFtp ? 'true' : 'false';

        if ($useFtp) {
            $this->askFor($changes, $env, 'POLARIS_FTP_HOST', 'FTP host');
            $this->askFor($changes, $env, 'POLARIS_FTP_PORT', 'FTP port', '21');
            $this->askFor($changes, $env, 'POLARIS_FTP_USERNAME', 'FTP username');
            $this->askForSecret($changes, $env, 'POLARIS_FTP_PASSWORD', 'FTP password');
            $this->askFor($changes, $env, 'POLARIS_FTP_PATH', 'Folder on the FTP server', '/');
        }
        $this->newLine();

        return $changes;
    }

    /** @return array<string, string> */
    private function collectGeneral(EnvFile $env): array
    {
        $this->comment('General');

        $changes = [];
        $this->askFor($changes, $env, 'NOTICES_TIMEZONE', 'Timezone of the export file names', 'America/Chicago');

        return $changes;
    }

    /**
     * Ask for a plain setting. A blank answer for a key that is not in the
     * file yet leaves it out, so optional settings stay optional.
     *
     * @param array<string, string> $changes
     */
    private function askFor(array &$changes, EnvFile $env, string $key, string $question, ?string $default = null): void
    {
        $answer = $this->ask($question . ' (' . $key . ')', $env->get($key, $default));
        $answer = null === $answer ? '' : trim((string) $answer);

        if ('' === $answer && !$env->has($key)) {
            return;
        }

        $changes[$key] = $answer;
    }

    /**
     * Ask for a secret without echoing it. Blank keeps what is already saved.
     *
     * @param array<string, string> $changes
     */
    private function askForSecret(array &$changes, EnvFile $env, string $key, string $question): void
    {
        $suffix = $env->has($key) && null !== $env->get($key) ? ', blank to keep the saved value' : '';
        $answer = $this->secret($question . ' (' . $key . $suffix . ')');

        if (null !== $answer && '' !== $answer) {
            $changes[$key] = (string) $answer;
        }
    }

    private function ensureStorageDirectory(): void
    {
        $directory = (string) config('notices.import.storage');

        if ('' !== $directory && !File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
            $this->line('Created ' . $directory);
        }
    }

    private function nextSteps(): void
    {
        $this->newLine();
        $this->info('Next steps');
        $this->line('  1. If the application caches its config, refresh it:  php artisan config:clear');
        $this->line('  2. Run a first import:                                php artisan notices:import --days=1');
        $this->line('  3. Make sure the scheduler runs every minute:         php artisan schedule:run');
        $this->line('  4. Open the dashboard at /notices');
    }
}
