<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds import_date column if it doesn't exist (for databases created
     * before this column was added to the main migration).
     */
    public function up(): void
    {
        if (!Schema::hasColumn('polaris_phone_notices', 'import_date')) {
            Schema::table('polaris_phone_notices', function (Blueprint $table) {
                $table->date('import_date')->nullable()->index()
                    ->comment('Date portion of import timestamp');
            });

            // Backfill import_date from imported_at if data exists
            \DB::statement('UPDATE polaris_phone_notices SET import_date = DATE(imported_at) WHERE import_date IS NULL AND imported_at IS NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('polaris_phone_notices', 'import_date')) {
            Schema::table('polaris_phone_notices', function (Blueprint $table) {
                $table->dropColumn('import_date');
            });
        }
    }
};
