<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations - Add performance indexes for import operations.
     */
    public function up(): void
    {
        // Indexes for patron_delivery_preferences table
        if (Schema::hasTable('patron_delivery_preferences')) {
            Schema::table('patron_delivery_preferences', function (Blueprint $table) {
                // Only add indexes if columns exist
                if ($this->hasColumn('patron_delivery_preferences', 'patron_barcode')) {
                    $this->addIndexIfNotExists($table, 'patron_barcode', 'idx_pdp_patron_barcode');
                }

                // Check for different possible column names for delivery method
                if ($this->hasColumn('patron_delivery_preferences', 'current_delivery_method')) {
                    $this->addIndexIfNotExists($table, 'current_delivery_method', 'idx_pdp_current_delivery');
                } elseif ($this->hasColumn('patron_delivery_preferences', 'delivery_method')) {
                    $this->addIndexIfNotExists($table, 'delivery_method', 'idx_pdp_delivery_method');
                }

                if ($this->hasColumn('patron_delivery_preferences', 'last_seen_at')) {
                    $this->addIndexIfNotExists($table, 'last_seen_at', 'idx_pdp_last_seen');
                }

                // Composite index (only if both columns exist)
                if ($this->hasColumn('patron_delivery_preferences', 'patron_barcode') &&
                    $this->hasColumn('patron_delivery_preferences', 'delivery_method')) {
                    $this->addCompositeIndexIfNotExists(
                        $table,
                        ['patron_barcode', 'delivery_method'],
                        'idx_pdp_patron_delivery'
                    );
                }
            });
        }

        // Indexes for shoutbomb_submissions table
        if (Schema::hasTable('shoutbomb_submissions')) {
            Schema::table('shoutbomb_submissions', function (Blueprint $table) {
                if ($this->hasColumn('shoutbomb_submissions', 'patron_barcode')) {
                    $this->addIndexIfNotExists($table, 'patron_barcode', 'idx_sub_patron_barcode');
                }

                if ($this->hasColumn('shoutbomb_submissions', 'submitted_at')) {
                    $this->addIndexIfNotExists($table, 'submitted_at', 'idx_sub_submitted_at');
                }

                if ($this->hasColumn('shoutbomb_submissions', 'notification_type')) {
                    $this->addIndexIfNotExists($table, 'notification_type', 'idx_sub_notification_type');
                }

                if ($this->hasColumn('shoutbomb_submissions', 'delivery_type')) {
                    $this->addIndexIfNotExists($table, 'delivery_type', 'idx_sub_delivery_type');
                }

                // Composite indexes
                if ($this->hasColumn('shoutbomb_submissions', 'patron_barcode') &&
                    $this->hasColumn('shoutbomb_submissions', 'submitted_at')) {
                    $this->addCompositeIndexIfNotExists(
                        $table,
                        ['patron_barcode', 'submitted_at'],
                        'idx_sub_patron_submitted'
                    );
                }

                if ($this->hasColumn('shoutbomb_submissions', 'notification_type') &&
                    $this->hasColumn('shoutbomb_submissions', 'submitted_at')) {
                    $this->addCompositeIndexIfNotExists(
                        $table,
                        ['notification_type', 'submitted_at'],
                        'idx_sub_type_submitted'
                    );
                }
            });
        }

        // Indexes for polaris_phone_notices table
        if (Schema::hasTable('polaris_phone_notices')) {
            Schema::table('polaris_phone_notices', function (Blueprint $table) {
                if ($this->hasColumn('polaris_phone_notices', 'patron_barcode')) {
                    $this->addIndexIfNotExists($table, 'patron_barcode', 'idx_ppn_patron_barcode');
                }

                if ($this->hasColumn('polaris_phone_notices', 'notice_date')) {
                    $this->addIndexIfNotExists($table, 'notice_date', 'idx_ppn_notice_date');
                }

                if ($this->hasColumn('polaris_phone_notices', 'delivery_type')) {
                    $this->addIndexIfNotExists($table, 'delivery_type', 'idx_ppn_delivery_type');
                }

                if ($this->hasColumn('polaris_phone_notices', 'library_code')) {
                    $this->addIndexIfNotExists($table, 'library_code', 'idx_ppn_library_code');
                }

                // Composite indexes
                if ($this->hasColumn('polaris_phone_notices', 'patron_barcode') &&
                    $this->hasColumn('polaris_phone_notices', 'notice_date')) {
                    $this->addCompositeIndexIfNotExists(
                        $table,
                        ['patron_barcode', 'notice_date'],
                        'idx_ppn_patron_date'
                    );
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexes = [
            'patron_delivery_preferences' => [
                'idx_pdp_patron_barcode',
                'idx_pdp_current_delivery',
                'idx_pdp_delivery_method',
                'idx_pdp_last_seen',
                'idx_pdp_patron_delivery',
            ],
            'shoutbomb_submissions' => [
                'idx_sub_patron_barcode',
                'idx_sub_submitted_at',
                'idx_sub_notification_type',
                'idx_sub_delivery_type',
                'idx_sub_patron_submitted',
                'idx_sub_type_submitted',
            ],
            'polaris_phone_notices' => [
                'idx_ppn_patron_barcode',
                'idx_ppn_notice_date',
                'idx_ppn_delivery_type',
                'idx_ppn_library_code',
                'idx_ppn_patron_date',
            ],
        ];

        foreach ($indexes as $table => $tableIndexes) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) use ($tableIndexes) {
                    foreach ($tableIndexes as $index) {
                        if ($this->indexExists($table->getTable(), $index)) {
                            $table->dropIndex($index);
                        }
                    }
                });
            }
        }
    }

    /**
     * Check if a column exists in a table.
     */
    private function hasColumn(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    /**
     * Check if an index exists.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        // SQLite: use PRAGMA index_list and avoid information_schema
        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            foreach ($indexes as $index) {
                if (isset($index->name) && $index->name === $indexName) {
                    return true;
                }
            }

            return false;
        }

        // MySQL / MariaDB: use information_schema.statistics
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $database = $connection->getDatabaseName();

            $result = DB::select(
                "SELECT COUNT(*) as count
                 FROM information_schema.statistics
                 WHERE table_schema = ?
                   AND table_name = ?
                   AND index_name = ?",
                [$database, $table, $indexName]
            );

            return isset($result[0]) && (int) $result[0]->count > 0;
        }

        // Fallback for other drivers: best-effort detection using Doctrine schema manager when available
        try {
            $schemaManager = $connection->getDoctrineSchemaManager();
            $indexes = $schemaManager->listTableIndexes($table);

            return array_key_exists($indexName, $indexes);
        } catch (Throwable $e) {
            // If introspection isn't available, assume the index does not exist
            return false;
        }
    }

    /**
     * Add index if it doesn't exist.
     */
    private function addIndexIfNotExists(Blueprint $table, string $column, string $indexName): void
    {
        $tableName = $table->getTable();

        if (!$this->indexExists($tableName, $indexName)) {
            $table->index($column, $indexName);
        }
    }

    /**
     * Add composite index if it doesn't exist.
     */
    private function addCompositeIndexIfNotExists(Blueprint $table, array $columns, string $indexName): void
    {
        $tableName = $table->getTable();

        if (!$this->indexExists($tableName, $indexName)) {
            $table->index($columns, $indexName);
        }
    }
};
