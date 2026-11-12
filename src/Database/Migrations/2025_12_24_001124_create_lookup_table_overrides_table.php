<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * This table allows customization of lookup table values without modifying
     * the original Polaris data. Staff can override display labels and control
     * visibility in UI selection inputs.
     */
    public function up(): void
    {
        Schema::create('lookup_table_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('table_name', 100); // e.g., 'notification_types', 'delivery_options'
            $table->unsignedBigInteger('record_id'); // The ID of the record in that table
            $table->string('custom_label', 255)->nullable(); // Override the display label
            $table->boolean('is_enabled')->default(true); // Show/hide in UI selection inputs
            $table->text('notes')->nullable(); // Why this override exists
            $table->timestamps();

            // Ensure one override per table/record combination (MySQL-safe name)
            $table->unique(['table_name', 'record_id'], 'lookup_overrides_tbl_rec_unq');

            // Index for quick lookups (MySQL-safe name)
            $table->index(['table_name', 'is_enabled'], 'lookup_overrides_tbl_enabled_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lookup_table_overrides');
    }
};
