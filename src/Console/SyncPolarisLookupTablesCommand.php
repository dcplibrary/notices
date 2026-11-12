<?php

namespace Dcplibrary\Notices\Console;

use Dcplibrary\Notices\Database\Seeders\LookupTablesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Artisan command to recreate static Polaris lookup tables in the local database.
 */
class SyncPolarisLookupTablesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notices:sync-lookup-tables';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recreate static Polaris lookup tables in the local database.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Run the dedicated seeder that ships with the package under docs/polaris/lookup_tables.
        // This seeder recreates the Polaris static lookup tables locally.
        Artisan::call('db:seed', [
            '--class' => LookupTablesSeeder::class,
            '--force' => true,
        ]);

        $this->info('Polaris lookup tables synchronized.');

        return self::SUCCESS;
    }
}
