<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add indexes for failed-contacts API filter/sort patterns.
     */
    public function up(): void
    {
        Schema::table('failed_delivery_strings', function (Blueprint $table) {
            // Optimizes unresolved + date-window API reads ordered by recency.
            $table->index(
                ['is_resolved', 'last_failed_at', 'id'],
                'fds_res_last_id_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('failed_delivery_strings', function (Blueprint $table) {
            $table->dropIndex('fds_res_last_id_idx');
        });
    }
};
