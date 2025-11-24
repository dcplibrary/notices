<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Add performance indexes for import operations
     */
    public function up(): void
    {
        // Indexes for patron_delivery_preferences table
        if (Schema::hasTable('patron_delivery_preferences')) {
            Schema::table('patron_delivery_preferences', function (Blueprint $table) {
                // Only add indexes if columns exist
                if ($this->hasColumn('patron_delivery_preferences', 'patron_barcode')) {
                    $this->addIndexIfNotExists('patron_delivery_preferences', 'patron_barcode', 'idx_pdp_patron_barcode');
                }
                
                // Check for different possible column names for delivery method
                if ($this->hasColumn('patron_delivery_preferences', 'current_delivery_method')) {
                    $this->addIndexIfNotExists('patron_delivery_preferences', 'current_delivery_method', 'idx_pdp_current_delivery');
                } elseif ($this->hasColumn('patron_delivery_preferences', 'delivery_method')) {
                    $this->addIndexIfNotExists('patron_delivery_preferences', 'delivery_method', 'idx_pdp_delivery_method');
                }
                
                if ($this->hasColumn('patron_delivery_preferences', 'last_seen_at')) {
                    $this->addIndexIfNotExists('patron_delivery_preferences', 'last_seen_at', 'idx_pdp_last_seen');
                }
                
                // Composite index (only if both columns exist)
                if ($this->hasColumn('patron_delivery_preferences', 'patron_barcode') && 
                    $this->hasColumn('patron_delivery_preferences', 'delivery_method')) {
                    $this->addCompositeIndexIfNotExists(
                        'patron_delivery_preferences', 
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
                    $this->addIndexIfNotExists('shoutbomb_submissions', 'patron_barcode', 'idx_sub_patron_barcode');
                }
                
                if ($this->hasColumn('shoutbomb_submissions', 'submitted_at')) {
                    $this->addIndexIfNotExists('shoutbomb_submissions', 'submitted_at', 'idx_sub_submitted_at');
                }
                
                if ($this->hasColumn('shoutbomb_submissions', 'notification_type')) {
                    $this->addIndexIfNotExists('shoutbomb_submissions', 'notification_type', 'idx_sub_notification_type');
                }
                
                if ($this->hasColumn('shoutbomb_submissions', 'delivery_type')) {
                    $this->addIndexIfNotExists('shoutbomb_submissions', 'delivery_type', 'idx_sub_delivery_type');
                }
                
                // Composite indexes
                if ($this->hasColumn('shoutbomb_submissions', 'patron_barcode') && 
                    $this->hasColumn('shoutbomb_submissions', 'submitted_at')) {
                    $this->addCompositeIndexIfNotExists(
                        'shoutbomb_submissions', 
                        ['patron_barcode', 'submitted_at'], 
                        'idx_sub_patron_submitted'
                    );
                }
                
                if ($this->hasColumn('shoutbomb_submissions', 'notification_type') && 
                    $this->hasColumn('shoutbomb_submissions', 'submitted_at')) {
                    $this->addCompositeIndexIfNotExists(
                        'shoutbomb_submissions', 
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
                    $this->addIndexIfNotExists('polaris_phone_notices', 'patron_barcode', 'idx_ppn_patron_barcode');
                }
                
                if ($this->hasColumn('polaris_phone_notices', 'notice_date')) {
                    $this->addIndexIfNotExists('polaris_phone_notices', 'notice_date', 'idx_ppn_notice_date');
                }
                
                if ($this->hasColumn('polaris_phone_notices', 'delivery_type')) {
                    $this->addIndexIfNotExists('polaris_phone_notices', 'delivery_type', 'idx_ppn_delivery_type');
                }
                
                if ($this->hasColumn('polaris_phone_notices', 'library_code')) {
                    $this->addIndexIfNotExists('polaris_phone_notices', 'library_code', 'idx_ppn_library_code');
                }
                
                // Composite indexes
                if ($this->hasColumn('polaris_phone_notices', 'patron_barcode') && 
                    $this->hasColumn('polaris_phone_notices', 'notice_date')) {
                    $this->addCompositeIndexIfNotExists(
                        'polaris_phone_notices', 
                        ['patron_barcode', 'notice_date'], 
                        'idx_ppn_patron_date'
                    );
                }
            });
        }
    }

    /**
     * Reverse the migrations
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
     * Check if a column exists in a table
     */
    private function hasColumn(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    /**
     * Check if an index exists
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        
        $result = DB::select("
            SELECT COUNT(*) as count
            FROM information_schema.statistics 
            WHERE table_schema = ? 
            AND table_name = ? 
            AND index_name = ?
        ", [$database, $table, $indexName]);
        
        return $result[0]->count > 0;
    }

    /**
     * Add index if it doesn't exist
     */
    private function addIndexIfNotExists(string $table, string $column, string $indexName): void
    {
        if (!$this->indexExists($table, $indexName)) {
            DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$column}`)");
        }
    }

    /**
     * Add composite index if it doesn't exist
     */
    private function addCompositeIndexIfNotExists(string $table, array $columns, string $indexName): void
    {
        if (!$this->indexExists($table, $indexName)) {
            $columnList = implode('`, `', $columns);
            DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$columnList}`)");
        }
    }
};
