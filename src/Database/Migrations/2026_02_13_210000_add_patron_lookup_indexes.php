<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Phase 1 Performance Optimization: Patron Lookup Indexes
 *
 * This migration adds indexes to support fast lookups by patron phone and email.
 * Based on slow query log analysis showing:
 * - 687 slow queries on patron_phone (avg 0.079s, max 0.262s)
 * - 39 slow queries on patron_email (avg 0.053s, max 0.118s)
 * - 26 slow queries on export_datetime (avg 0.052s, max 0.101s)
 *
 * Expected improvement: 100x faster lookups (from ~0.08s to ~0.001s)
 *
 */
return new class extends Migration {
    public function up(): void
    {
        // Add patron_phone indexes
        if (Schema::hasTable('polaris_hold_notifications')) {
            Schema::table('polaris_hold_notifications', function (Blueprint $table) {
                // Optimizes lookups like: WHERE patron_phone = '5555818536'
                // Query pattern: Used by failed delivery string resolution
                $table->index('patron_phone', 'idx_hold_patron_phone');

                // Optimizes date range queries: WHERE export_datetime >= X AND export_datetime < Y
                // Query pattern: Used by source file lookups
                $table->index('export_datetime', 'idx_hold_export_datetime');

                // Optimizes email lookups: WHERE patron_email = 'user@example.com'
                $table->index('patron_email', 'idx_hold_patron_email');
            });
        }

        if (Schema::hasTable('polaris_renewal_notifications')) {
            Schema::table('polaris_renewal_notifications', function (Blueprint $table) {
                $table->index('patron_phone', 'idx_renewal_patron_phone');
                $table->index('export_datetime', 'idx_renewal_export_datetime');
                $table->index('patron_email', 'idx_renewal_patron_email');
            });
        }

        if (Schema::hasTable('polaris_overdue_notifications')) {
            Schema::table('polaris_overdue_notifications', function (Blueprint $table) {
                $table->index('patron_phone', 'idx_overdue_patron_phone');
                $table->index('patron_email', 'idx_overdue_patron_email');
            });
        }

        if (Schema::hasTable('polaris_notification_history')) {
            Schema::table('polaris_notification_history', function (Blueprint $table) {
                $table->index('export_datetime', 'idx_history_export_datetime');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('polaris_hold_notifications')) {
            Schema::table('polaris_hold_notifications', function (Blueprint $table) {
                $table->dropIndex('idx_hold_patron_phone');
                $table->dropIndex('idx_hold_export_datetime');
                $table->dropIndex('idx_hold_patron_email');
            });
        }

        if (Schema::hasTable('polaris_renewal_notifications')) {
            Schema::table('polaris_renewal_notifications', function (Blueprint $table) {
                $table->dropIndex('idx_renewal_patron_phone');
                $table->dropIndex('idx_renewal_export_datetime');
                $table->dropIndex('idx_renewal_patron_email');
            });
        }

        if (Schema::hasTable('polaris_overdue_notifications')) {
            Schema::table('polaris_overdue_notifications', function (Blueprint $table) {
                $table->dropIndex('idx_overdue_patron_phone');
                $table->dropIndex('idx_overdue_patron_email');
            });
        }

        if (Schema::hasTable('polaris_notification_history')) {
            Schema::table('polaris_notification_history', function (Blueprint $table) {
                $table->dropIndex('idx_history_export_datetime');
            });
        }
    }
};
