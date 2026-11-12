<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Lookup table for notification status messages.
     *
     * Provides a single source of truth for all status types (delivered, pending, failed)
     * with customizable labels and messages for staff display.
     */
    public function up(): void
    {
        Schema::create('status_messages', function (Blueprint $table) {
            $table->id();

            // Unique code for the status message (e.g., 'delivered_email', 'pending_sms', 'opted_out')
            $table->string('code', 100)->unique();

            // Status type: delivered, pending, or failed
            $table->string('status', 20);

            // Display label (e.g., "Email Delivered", "Invalid Phone Number")
            $table->string('label', 255);

            // Explanation message shown in the viewer portal (HTML)
            $table->text('message');

            // Action/resolution steps for staff (HTML) - primarily for failed status
            $table->text('action')->nullable();

            // Which delivery option this applies to (null = any)
            $table->unsignedBigInteger('delivery_option_id')->nullable();

            // Source system for failed messages (shoutbomb, notification_log, system)
            $table->string('source', 50)->nullable();

            // Link to notification_statuses table if applicable
            $table->unsignedBigInteger('notification_status_id')->nullable();

            // Is this a patron contact issue (vs system/technical issue)?
            // Contact issues require staff to update patron info in Polaris
            $table->boolean('is_contact_issue')->default(false);

            // Display order in UI
            $table->unsignedInteger('sort_order')->default(0);

            // Whether this status message is active
            $table->boolean('is_enabled')->default(true);

            $table->timestamps();

            // Foreign keys
            $table->foreign('delivery_option_id')
                ->references('id')
                ->on('delivery_options')
                ->onDelete('set null')
            ;

            $table->foreign('notification_status_id')
                ->references('id')
                ->on('notification_statuses')
                ->onDelete('set null')
            ;

            // Indexes for common lookups
            $table->index('code');
            $table->index('status');
            $table->index('source');
            $table->index('is_enabled');
            $table->index(['status', 'is_enabled']);
            $table->index(['status', 'delivery_option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_messages');
    }
};
