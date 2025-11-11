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
            // Separate contact fields for better searchability
            $table->string('phone', 20)->nullable()->after('patron_barcode')->comment('Normalized phone number for SMS/Voice');
            $table->string('email', 255)->nullable()->after('phone')->comment('Email address for Email notifications');
            
            // Patron information for searching
            $table->string('patron_name', 255)->nullable()->after('email')->comment('Patron name for search');
            
            // Item information for tracking and verification
            $table->string('item_barcode', 50)->nullable()->after('patron_name')->comment('Primary item barcode in notification');
            $table->text('item_title')->nullable()->after('item_barcode')->comment('Item title for display');
            
            // Add indexes for common searches
            $table->index('phone', 'nl_phone_idx');
            $table->index('email', 'nl_email_idx');
            $table->index('item_barcode', 'nl_item_barcode_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropIndex('nl_phone_idx');
            $table->dropIndex('nl_email_idx');
            $table->dropIndex('nl_item_barcode_idx');
            
            $table->dropColumn([
                'phone',
                'email',
                'patron_name',
                'item_barcode',
                'item_title',
            ]);
        });
    }
};
