<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('polaris_phone_notices') && !Schema::hasColumn('polaris_phone_notices', 'import_date')) {
            Schema::table('polaris_phone_notices', function (Blueprint $table) {
                $table->date('import_date')->nullable()->index();
            });

            // Backfill from imported_at
            DB::statement('UPDATE polaris_phone_notices SET import_date = DATE(imported_at) WHERE import_date IS NULL AND imported_at IS NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('polaris_phone_notices', 'import_date')) {
            Schema::table('polaris_phone_notices', function (Blueprint $table) {
                $table->dropColumn('import_date');
            });
        }
    }
};
