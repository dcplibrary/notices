<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shoutbomb_deliveries', function (Blueprint $table) {
            // Add patron_id to match email report format
            $table->integer('patron_id')->nullable()->after('patron_barcode');

            // Add patron_name for undelivered voice reports
            $table->string('patron_name', 100)->nullable()->after('patron_id');

            // Add library_name for undelivered voice reports
            $table->string('library_name', 100)->nullable()->after('patron_name');

            // Add status_code from opt-out/invalid reports
            $table->integer('status_code')->nullable()->after('status');
        });

        // Update ENUM columns - only for MySQL (SQLite stores enums as strings)
        if (DB::getDriverName() === 'mysql') {
            // Update the status enum to include 'OptedOut'
            DB::statement("ALTER TABLE shoutbomb_deliveries MODIFY COLUMN status ENUM('Delivered', 'Failed', 'Pending', 'Invalid', 'OptedOut') DEFAULT 'Pending'");

            // Update report_type enum to include email sources
            DB::statement("ALTER TABLE shoutbomb_deliveries MODIFY COLUMN report_type ENUM('Daily', 'Weekly', 'Monthly', 'text_delivery', 'voice_delivery', 'email_optout', 'email_invalid', 'email_undelivered_voice') NULL");
        }
        // SQLite doesn't enforce ENUM types, so no modification needed
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shoutbomb_deliveries', function (Blueprint $table) {
            $table->dropColumn(['patron_id', 'patron_name', 'library_name', 'status_code']);
        });

        // Revert ENUM columns - only for MySQL
        if (DB::getDriverName() === 'mysql') {
            // Revert status enum
            DB::statement("ALTER TABLE shoutbomb_deliveries MODIFY COLUMN status ENUM('Delivered', 'Failed', 'Pending', 'Invalid') DEFAULT 'Pending'");

            // Revert report_type enum
            DB::statement("ALTER TABLE shoutbomb_deliveries MODIFY COLUMN report_type ENUM('Daily', 'Weekly', 'Monthly', 'text_delivery', 'voice_delivery') NULL");
        }
    }
};
