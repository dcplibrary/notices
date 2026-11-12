<?php

namespace Dcplibrary\Notices\Console;

use Dcplibrary\Notices\Database\Seeders\LookupTablesSeeder;
use Illuminate\Console\Command;

/**
 * Artisan command to seed the notices package lookup tables.
 *
 * Delegates to LookupTablesSeeder to populate the static Polaris reference
 * tables (delivery_options, notification_types, notification_statuses, etc.)
 * required for the notices package to function correctly.
 *
 * Usage:
 *   php artisan notices:seed
 */
class SeedCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notices:seed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the notices package lookup tables with Polaris data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->call('db:seed', [
            '--class' => LookupTablesSeeder::class,
        ]);

        return 0;
    }
}
