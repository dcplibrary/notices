<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Tracks delivery strings (email addresses, phone numbers) that have failed.
     * When a delivery string fails, ALL patrons using it are potentially affected.
     */
    public function up(): void
    {
        Schema::create('failed_delivery_strings', function (Blueprint $table) {
            $table->id();

            // Delivery channel and address
            $table->unsignedBigInteger('delivery_option_id'); // 1=Mail, 2=Email, 3=Voice, 8=SMS
            $table->string('delivery_string', 255); // The actual email/phone that failed

            // Failure tracking
            $table->string('failure_source', 100); // e.g., 'shoutbomb_voice_failure', 'shoutbomb_invalid_phone'
            $table->text('failure_details')->nullable(); // The actual error message
            $table->dateTime('first_failed_at'); // When we first detected this failure
            $table->dateTime('last_failed_at'); // Most recent failure occurrence
            $table->unsignedInteger('failure_count')->default(1); // Number of times this has failed

            // Status and resolution
            $table->boolean('is_resolved')->default(false);
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();

            // Resolution tracking
            $table->unsignedBigInteger('resolved_by_user_id')->nullable();
            $table->string('resolved_by_name', 255)->nullable();

            // Optional manual mapping added at resolution time
            $table->string('resolved_patron_barcode', 50)->nullable();
            $table->string('resolved_patron_first_name', 100)->nullable();
            $table->string('resolved_patron_last_name', 100)->nullable();

            // Cached metrics (updated when patron_delivery_issues are created)
            $table->unsignedInteger('affected_patron_count')->default(0);

            $table->timestamps();

            // Indexes for quick lookup
            // Short unique index name to satisfy MySQL's 64 character limit
            $table->unique(['delivery_option_id', 'delivery_string'], 'failed_deliv_opt_string_unq');
            $table->index('is_resolved');
            $table->index('failure_source');
            $table->index('first_failed_at');
            $table->index('resolved_by_user_id', 'fds_resolved_by_user_idx');
            // Supports dashboard CTA sampling and counts for unresolved failed contacts, especially
            // when filtering SMS/Voice to true contact-problem sources and ordering by recency.
            $table->index(
                ['is_resolved', 'delivery_option_id', 'failure_source', 'last_failed_at'],
                'fds_res_do_src_last_idx'
            );

            // Foreign key
            $table->foreign('delivery_option_id')
                ->references('id')
                ->on('delivery_options')
                ->onDelete('restrict')
            ;
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_delivery_strings');
    }
};
