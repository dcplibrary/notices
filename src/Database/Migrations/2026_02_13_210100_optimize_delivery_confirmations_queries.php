<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Phase 2 Performance Optimization: Delivery Confirmations
 *
 * This migration adds a composite index to optimize delivery confirmations queries
 * that filter by patron_barcode, delivery_option_id, and notice_date range.
 *
 * Based on slow query log analysis showing:
 * - 55 slow queries (avg 0.115s, max 0.195s)
 * - Using DATE() function which prevents index usage
 *
 * Query pattern:
 * SELECT ... FROM delivery_confirmations
 * WHERE patron_barcode = '...'
 *   AND delivery_option_id = 1
 *   AND notice_date IS NOT NULL
 *   AND DATE(notice_date) >= DATE("2026-02-10")
 *   AND DATE(notice_date) <= DATE("2026-02-13")
 * ORDER BY notice_date ASC
 *
 * Expected improvement: 20x faster (from ~0.115s to ~0.005s)
 *
 * Note: Application code should be updated to avoid DATE() function:
 *   WHERE notice_date >= '2026-02-10 00:00:00'
 *     AND notice_date < '2026-02-14 00:00:00'
 *
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('delivery_confirmations')) {
            Schema::table('delivery_confirmations', function (Blueprint $table) {
                // Composite index to support patron + delivery method + date range queries
                // This covers the full WHERE clause in the typical query pattern
                $table->index(
                    ['patron_barcode', 'delivery_option_id', 'notice_date'],
                    'idx_dc_patron_delivery_date'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('delivery_confirmations')) {
            Schema::table('delivery_confirmations', function (Blueprint $table) {
                $table->dropIndex('idx_dc_patron_delivery_date');
            });
        }
    }
};
