<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This table tracks the CURRENT delivery preference for each patron,
     * along with when the preference was last changed and what the
     * previous preference was.
     *
     * This is a "current state" table - one row per patron - unlike
     * patrons_notification_preferences which stores daily snapshots.
     *
     * Delivery methods:
     *   - voice (delivery_option_id = 3): Phone1 - Voice calls
     *   - text (delivery_option_id = 8): TXT Messaging - SMS
     *   - unknown: Patron hasn't appeared in either list yet
     */
    public function up(): void
    {
        Schema::create('patron_delivery_preferences', function (Blueprint $table) {
            $table->id();

            // Patron identification (unique per patron)
            $table->string('patron_barcode', 20)->unique()->comment('Patron barcode - unique per patron');

            // Phone number (from PhoneVoice1 field)
            $table->char('phone_voice1', 10)->nullable()->index()->comment('10-digit phone number');

            // Current delivery preference
            $table->enum('current_delivery_method', ['voice', 'text', 'unknown'])->default('unknown')
                ->comment('Current delivery method: voice, text, or unknown');
            $table->tinyInteger('current_delivery_option_id')->nullable()
                ->comment('Current Polaris delivery_option_id: 3=Voice, 8=Text, null=unknown');

            // Previous delivery preference (for change tracking)
            $table->enum('previous_delivery_method', ['voice', 'text', 'unknown'])->nullable()
                ->comment('Previous delivery method before the last change');
            $table->tinyInteger('previous_delivery_option_id')->nullable()
                ->comment('Previous Polaris delivery_option_id before the last change');

            // Change tracking timestamps
            $table->timestamp('preference_changed_at')->nullable()
                ->comment('When the delivery preference was last changed');
            $table->timestamp('first_seen_at')->nullable()
                ->comment('When this patron first appeared in a patron list');
            $table->timestamp('last_seen_at')->nullable()
                ->comment('When this patron last appeared in a patron list');

            // Track which file was source of last update
            $table->string('last_source_file', 255)->nullable()
                ->comment('Filename of the last patron list this patron appeared in');

            $table->timestamps();

            // Indexes for common queries
            $table->index(['current_delivery_method', 'last_seen_at'], 'pdp_method_lastseen_idx');
            $table->index('preference_changed_at', 'pdp_changed_at_idx');
            $table->index('phone_voice1', 'pdp_phone_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patron_delivery_preferences');
    }
};
