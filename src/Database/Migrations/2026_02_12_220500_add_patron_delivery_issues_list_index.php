<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('patron_delivery_issues')) {
            return;
        }

        Schema::table('patron_delivery_issues', function (Blueprint $table) {
            // Supports unresolved issues list queries filtered by date and sorted
            // by detected_at/id (issues API and dashboard list views).
            $table->index(
                ['resolved_at', 'detected_at', 'id'],
                'pdi_resolved_detected_id_idx'
            );
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('patron_delivery_issues')) {
            return;
        }

        Schema::table('patron_delivery_issues', function (Blueprint $table) {
            $table->dropIndex('pdi_resolved_detected_id_idx');
        });
    }
};
