<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            // Add status field: completed, pending, failed
            $table->enum('status', ['completed', 'pending', 'failed'])
                  ->default('pending')
                  ->after('notification_status_id');
            
            // Add human-readable status description
            $table->text('status_description')->nullable()->after('status');
            
            // Add index for efficient querying
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'status_description']);
        });
    }
};
