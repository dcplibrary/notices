<?php

namespace Dcplibrary\Notices\Console;

use Dcplibrary\Notices\Contracts\NotificationFailureDetectorInterface;
use Dcplibrary\Notices\Models\ActivityLog;
use Illuminate\Console\Command;

/**
 * Detect notification failures and at-risk patrons by correlating
 * DeliveryConfirmation records with unified notifications.
 *
 * This command should run after both FTP imports and email ingestion
 * have completed for a given date.
 */
class ImportConfirmationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notices:import-confirmations {date? : Date (YYYY-MM-DD) to analyze, defaults to yesterday}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect notification failures and at-risk patrons by correlating delivery confirmations.';

    /**
     * Execute the console command.
     */
    public function handle(NotificationFailureDetectorInterface $detector): int
    {
        $dateInput = $this->argument('date');
        $date      = $dateInput ? new \DateTimeImmutable($dateInput) : new \DateTimeImmutable('yesterday');

        $this->info('[notices:import-confirmations] Detecting failures for ' . $date->format('Y-m-d'));

        $failures      = $detector->detectFailures($date);
        $invalidPhones = $detector->detectInvalidPhoneNumbers($date);

        ActivityLog::create([
            'action'       => 'import.confirmations.detect',
            'description'  => 'Detected notification failures',
            'subject_type' => null,
            'subject_id'   => null,
            'meta'         => [
                'date'                 => $date->format('Y-m-d'),
                'failures_count'       => count($failures),
                'invalid_phones_count' => count($invalidPhones),
            ],
        ]);

        $this->info('✔  Detected ' . count($failures) . ' notification failures for ' . $date->format('Y-m-d') . '.');
        $this->info('✔  Detected ' . count($invalidPhones) . ' invalid phone numbers for ' . $date->format('Y-m-d') . '.');

        return self::SUCCESS;
    }
}
