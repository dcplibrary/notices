<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shoutbomb_monthly_stats', function (Blueprint $table) {
            $table->id();

            // Email identifiers
            $table->string('outlook_message_id')->unique()->index();
            $table->string('subject')->nullable();
            $table->date('report_month')->index(); // Extracted from subject or content
            $table->string('branch_name')->nullable()->index();

            // Hold notices
            $table->integer('hold_text_notices')->nullable();
            $table->integer('hold_text_reminders')->nullable();
            $table->integer('hold_voice_notices')->nullable();
            $table->integer('hold_voice_reminders')->nullable();

            // Overdue notices
            $table->integer('overdue_text_notices')->nullable();
            $table->integer('overdue_text_eligible_renewal')->nullable();
            $table->integer('overdue_text_ineligible_renewal')->nullable();
            $table->integer('overdue_text_renewed_successfully')->nullable();
            $table->integer('overdue_text_renewed_unsuccessfully')->nullable();
            $table->integer('overdue_voice_notices')->nullable();
            $table->integer('overdue_voice_eligible_renewal')->nullable();
            $table->integer('overdue_voice_ineligible_renewal')->nullable();

            // Renewal notices
            $table->integer('renewal_text_notices')->nullable();
            $table->integer('renewal_text_eligible')->nullable();
            $table->integer('renewal_text_ineligible')->nullable();
            $table->integer('renewal_text_unsuccessfully')->nullable();
            $table->integer('renewal_text_reminders')->nullable();
            $table->integer('renewal_text_reminder_eligible')->nullable();
            $table->integer('renewal_text_reminder_ineligible')->nullable();

            $table->integer('renewal_voice_notices')->nullable();
            $table->integer('renewal_voice_eligible')->nullable();
            $table->integer('renewal_voice_ineligible')->nullable();
            $table->integer('renewal_voice_reminders')->nullable();
            $table->integer('renewal_voice_reminder_eligible')->nullable();
            $table->integer('renewal_voice_reminder_ineligible')->nullable();

            // Registration statistics
            $table->integer('total_registered_users')->nullable();
            $table->integer('total_registered_barcodes')->nullable();
            $table->integer('total_registered_text')->nullable();
            $table->integer('total_registered_voice')->nullable();
            $table->integer('new_registrations_month')->nullable();
            $table->integer('new_voice_signups')->nullable();
            $table->integer('new_text_signups')->nullable();

            // Voice call statistics
            $table->integer('average_daily_calls')->nullable();

            // Keyword usage (stored as JSON)
            $table->json('keyword_usage')->nullable();

            // Timestamps
            $table->timestamp('received_at')->nullable()->index();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            // Index for querying by month/branch
            $table->index(['report_month', 'branch_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shoutbomb_monthly_stats');
    }
};
