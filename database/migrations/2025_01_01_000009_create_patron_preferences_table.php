<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This table stores patron notification preferences including opt-in/out
     * settings, preferred channels, and contact preferences.
     */
    public function up(): void
    {
        Schema::create('patron_preferences', function (Blueprint $table) {
            $table->id();

            // Patron identification
            $table->string('patron_barcode', 20)->unique()->index()->comment('From ILS');
            $table->integer('patron_id')->nullable()->index()->comment('Internal patron ID');

            // Opt-out preferences
            $table->boolean('opt_out_voice')->default(false)->comment('Opted out of voice calls');
            $table->boolean('opt_out_text')->default(false)->comment('Opted out of text messages');
            $table->boolean('opt_out_email')->default(false)->comment('Opted out of emails');
            $table->boolean('opt_out_all')->default(false)->comment('Opted out of all notifications');

            // Channel preferences
            $table->json('channel_preferences')->nullable()->comment('Preferred notification channels in order');
            $table->string('preferred_channel')->nullable()->comment('Single preferred channel');

            // Contact information (may override ILS)
            $table->string('phone_number', 20)->nullable()->comment('Preferred phone number');
            $table->string('email')->nullable()->comment('Preferred email address');
            $table->string('language', 10)->nullable()->default('en')->comment('Preferred language');

            // Notification type preferences
            $table->boolean('notify_holds')->default(true)->comment('Receive hold notifications');
            $table->boolean('notify_overdues')->default(true)->comment('Receive overdue notifications');
            $table->boolean('notify_renewals')->default(true)->comment('Receive renewal reminders');
            $table->boolean('notify_bills')->default(true)->comment('Receive billing notifications');

            // Timing preferences
            $table->time('quiet_hours_start')->nullable()->comment('Do not call before this time');
            $table->time('quiet_hours_end')->nullable()->comment('Do not call after this time');
            $table->string('timezone')->nullable()->default('America/Chicago')->comment('Patron timezone');

            // Frequency preferences
            $table->integer('max_daily_notifications')->nullable()->comment('Maximum notifications per day');
            $table->integer('overdue_reminder_frequency_days')->nullable()->default(3)->comment('Days between overdue reminders');

            // Audit
            $table->timestamp('preferences_updated_at')->nullable()->comment('Last preference update');
            $table->string('updated_by')->nullable()->comment('Who updated (patron, staff, system)');
            $table->string('update_source')->nullable()->comment('Web, API, ILS, etc.');

            $table->timestamps();

            // Indexes for common lookups
            $table->index('phone_number');
            $table->index('email');
            $table->index(['opt_out_all', 'opt_out_voice']);
            $table->index(['opt_out_all', 'opt_out_text']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patron_preferences');
    }
};
