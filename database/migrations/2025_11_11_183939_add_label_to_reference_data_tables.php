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
        // Add label column to notification_types
        Schema::table('notification_types', function (Blueprint $table) {
            $table->string('label')->nullable()->after('description');
        });

        // Add label column to delivery_methods
        Schema::table('delivery_methods', function (Blueprint $table) {
            $table->string('label')->nullable()->after('delivery_option');
        });

        // Add label column to notification_statuses
        Schema::table('notification_statuses', function (Blueprint $table) {
            $table->string('label')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_types', function (Blueprint $table) {
            $table->dropColumn('label');
        });

        Schema::table('delivery_methods', function (Blueprint $table) {
            $table->dropColumn('label');
        });

        Schema::table('notification_statuses', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};
