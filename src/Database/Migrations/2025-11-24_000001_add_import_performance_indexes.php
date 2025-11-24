<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add performance indexes for import operations
     */
    public function up(): void
    {
        // Indexes for patron_delivery_preferences table
        Schema::table('patron_delivery_preferences', function (Blueprint $table) {
            // Single column indexes
            $table->index('patron_barcode', 'idx_pdp_patron_barcode');
            $table->index('current_delivery_method', 'idx_pdp_current_delivery');
            $table->index('last_seen_at', 'idx_pdp_last_seen');
            $table->index('source_file', 'idx_pdp_source_file');
            
            // Composite indexes for common queries
            $table->index(['patron_barcode', 'current_delivery_method'], 'idx_pdp_patron_delivery');
            $table->index(['last_seen_at', 'current_delivery_method'], 'idx_pdp_seen_delivery');
        });

        // Indexes for shoutbomb_submissions table
        Schema::table('shoutbomb_submissions', function (Blueprint $table) {
            // Single column indexes
            $table->index('patron_barcode', 'idx_sub_patron_barcode');
            $table->index('submitted_at', 'idx_sub_submitted_at');
            $table->index('notification_type', 'idx_sub_notification_type');
            $table->index('delivery_type', 'idx_sub_delivery_type');
            $table->index('source_file', 'idx_sub_source_file');
            
            // Composite indexes for common queries
            $table->index(['patron_barcode', 'submitted_at'], 'idx_sub_patron_submitted');
            $table->index(['notification_type', 'submitted_at'], 'idx_sub_type_submitted');
            $table->index(['delivery_type', 'submitted_at'], 'idx_sub_delivery_submitted');
            $table->index(['submitted_at', 'notification_type', 'delivery_type'], 'idx_sub_full_query');
        });

        // Indexes for polaris_phone_notices table (for verification queries)
        Schema::table('polaris_phone_notices', function (Blueprint $table) {
            // Single column indexes
            $table->index('patron_barcode', 'idx_ppn_patron_barcode');
            $table->index('notice_date', 'idx_ppn_notice_date');
            $table->index('delivery_type', 'idx_ppn_delivery_type');
            $table->index('library_code', 'idx_ppn_library_code');
            $table->index('source_file', 'idx_ppn_source_file');
            
            // Composite indexes for verification queries
            $table->index(['patron_barcode', 'notice_date'], 'idx_ppn_patron_date');
            $table->index(['notice_date', 'delivery_type'], 'idx_ppn_date_delivery');
            $table->index(['notice_date', 'library_code'], 'idx_ppn_date_library');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::table('patron_delivery_preferences', function (Blueprint $table) {
            $table->dropIndex('idx_pdp_patron_barcode');
            $table->dropIndex('idx_pdp_current_delivery');
            $table->dropIndex('idx_pdp_last_seen');
            $table->dropIndex('idx_pdp_source_file');
            $table->dropIndex('idx_pdp_patron_delivery');
            $table->dropIndex('idx_pdp_seen_delivery');
        });

        Schema::table('shoutbomb_submissions', function (Blueprint $table) {
            $table->dropIndex('idx_sub_patron_barcode');
            $table->dropIndex('idx_sub_submitted_at');
            $table->dropIndex('idx_sub_notification_type');
            $table->dropIndex('idx_sub_delivery_type');
            $table->dropIndex('idx_sub_source_file');
            $table->dropIndex('idx_sub_patron_submitted');
            $table->dropIndex('idx_sub_type_submitted');
            $table->dropIndex('idx_sub_delivery_submitted');
            $table->dropIndex('idx_sub_full_query');
        });

        Schema::table('polaris_phone_notices', function (Blueprint $table) {
            $table->dropIndex('idx_ppn_patron_barcode');
            $table->dropIndex('idx_ppn_notice_date');
            $table->dropIndex('idx_ppn_delivery_type');
            $table->dropIndex('idx_ppn_library_code');
            $table->dropIndex('idx_ppn_source_file');
            $table->dropIndex('idx_ppn_patron_date');
            $table->dropIndex('idx_ppn_date_delivery');
            $table->dropIndex('idx_ppn_date_library');
        });
    }
};
