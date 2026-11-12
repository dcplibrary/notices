<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Links individual patrons to delivery issues.
     * Each patron affected by a failed delivery string or missing preference gets an entry.
     */
    public function up(): void
    {
        Schema::create('patron_delivery_issues', function (Blueprint $table) {
            $table->id();

            // Issue type
            $table->enum('issue_type', [
                'null_preference',           // Patron has no delivery preference configured
                'failed_delivery_string',    // Patron uses a delivery string that has failed
                'lx_starter_failure',        // Notification generated but never uploaded to LX Starter
                'polaris_export_failure',    // Notification exported from Polaris but never sent by LX Starter
                'mismatched_preference',     // Conflicting delivery preferences across barcodes
                'manual_review',             // Requires staff review
                'system_error',              // System processing error
            ]);

            // Patron identification
            $table->unsignedBigInteger('patron_id')->nullable();
            $table->string('patron_barcode', 50)->nullable();

            // Link to failed delivery string (null for other issue types)
            $table->unsignedBigInteger('failed_delivery_string_id')->nullable();

            // Notification that triggered this issue detection
            $table->unsignedBigInteger('notification_id')->nullable();

            // Additional context for this specific issue
            $table->text('issue_details')->nullable();

            // When detected and resolved
            $table->dateTime('detected_at');
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();

            // Resolution tracking
            $table->unsignedBigInteger('resolved_by_user_id')->nullable();
            $table->string('resolved_by_name', 255)->nullable();

            $table->timestamps();

            // Indexes - use short explicit names to avoid MySQL 64-char limit
            $table->index('issue_type', 'pdi_issue_type_idx');
            // Short composite index name for MySQL
            $table->index(['patron_id', 'issue_type'], 'patron_issue_type_idx');
            $table->index('patron_barcode', 'pdi_barcode_idx');
            $table->index('failed_delivery_string_id', 'pdi_failed_str_idx');
            $table->index('notification_id', 'pdi_notif_idx');
            $table->index('detected_at', 'pdi_detected_idx');
            $table->index('resolved_at', 'pdi_resolved_idx');
            $table->index('resolved_by_user_id', 'pdi_resolved_by_user_idx');

            // Foreign keys
            $table->foreign('failed_delivery_string_id')
                ->references('id')
                ->on('failed_delivery_strings')
                ->onDelete('cascade')
            ;

            $table->foreign('notification_id')
                ->references('id')
                ->on('notifications')
                ->onDelete('set null')
            ;
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patron_delivery_issues');
    }
};
