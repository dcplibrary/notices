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
        // Add enabled and display_order to notification_types
        Schema::table('notification_types', function (Blueprint $table) {
            $table->boolean('enabled')->default(true)->after('description');
            $table->integer('display_order')->default(0)->after('enabled');
            $table->softDeletes();
        });

        // Add enabled and display_order to notification_statuses
        Schema::table('notification_statuses', function (Blueprint $table) {
            $table->boolean('enabled')->default(true)->after('description');
            $table->integer('display_order')->default(0)->after('enabled');
            $table->string('category', 20)->nullable()->after('display_order')
                  ->comment('completed, pending, or failed');
            $table->softDeletes();
        });
        
        // Rename active to enabled in delivery_methods for consistency
        Schema::table('delivery_methods', function (Blueprint $table) {
            $table->renameColumn('active', 'enabled');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_types', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['enabled', 'display_order']);
        });

        Schema::table('notification_statuses', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['enabled', 'display_order', 'category']);
        });

        Schema::table('delivery_methods', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->renameColumn('enabled', 'active');
        });
    }
};
