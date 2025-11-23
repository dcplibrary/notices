<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This table stores aggregate statistics from ShoutBomb monthly reports.
     *
     * Source: "Shoutbomb Rpt [Month]" email reports
     * Schedule: Monthly
     */
    public function up(): void
    {
        Schema::create('shoutbomb_monthly_stats', function (Blueprint $table) {
            $table->id();

            // Email source identification
            $table->string('outlook_message_id', 255)->nullable()->unique()->comment('From Graph API');
            $table->string('subject', 500)->nullable()->comment('Email subject');

            // Report period
            $table->date('report_month')->index()->comment('First day of the report month');
            $table->string('branch_name', 100)->nullable()->comment('Branch name from report');

            // Hold notifications
            $table->integer('hold_text_notices')->default(0)->comment('SMS hold notices sent');
            $table->integer('hold_text_reminders')->default(0)->comment('SMS hold reminders');
            $table->integer('hold_voice_notices')->default(0)->comment('Voice hold notices');
            $table->integer('hold_voice_reminders')->default(0)->comment('Voice hold reminders');

            // Overdue notifications (text)
            $table->integer('overdue_text_notices')->default(0)->comment('SMS overdue notices');
            $table->integer('overdue_text_eligible_renewal')->default(0)->comment('SMS overdue, eligible for renewal');
            $table->integer('overdue_text_ineligible_renewal')->default(0)->comment('SMS overdue, not eligible for renewal');

            // Overdue notifications (voice)
            $table->integer('overdue_voice_notices')->default(0)->comment('Voice overdue notices');
            $table->integer('overdue_voice_eligible_renewal')->default(0)->comment('Voice overdue, eligible');
            $table->integer('overdue_voice_ineligible_renewal')->default(0)->comment('Voice overdue, not eligible');

            // Pre-due/Almost overdue notifications
            $table->integer('predue_text_notices')->default(0)->comment('SMS pre-due notices');
            $table->integer('predue_voice_notices')->default(0)->comment('Voice pre-due notices');

            // Fine notifications
            $table->integer('fine_text_notices')->default(0)->comment('SMS fine notices');
            $table->integer('fine_voice_notices')->default(0)->comment('Voice fine notices');

            // Bill notifications
            $table->integer('bill_text_notices')->default(0)->comment('SMS bill notices');
            $table->integer('bill_voice_notices')->default(0)->comment('Voice bill notices');

            // Delivery statistics
            $table->integer('total_text_sent')->default(0)->comment('Total SMS messages sent');
            $table->integer('total_voice_sent')->default(0)->comment('Total voice calls made');
            $table->integer('total_text_delivered')->default(0)->comment('SMS successfully delivered');
            $table->integer('total_voice_delivered')->default(0)->comment('Voice successfully delivered');
            $table->integer('total_text_failed')->default(0)->comment('SMS failures');
            $table->integer('total_voice_failed')->default(0)->comment('Voice failures');

            // Registration statistics
            $table->integer('total_registered_users')->default(0)->comment('Total registered for notifications');
            $table->integer('total_registered_text')->default(0)->comment('Registered for SMS');
            $table->integer('total_registered_voice')->default(0)->comment('Registered for voice');
            $table->integer('new_registrations')->default(0)->comment('New registrations this month');
            $table->integer('unsubscribes')->default(0)->comment('Opt-outs this month');
            $table->integer('invalid_numbers_count')->default(0)->comment('Invalid numbers detected');

            // Keyword usage (JSON array of keyword counts)
            $table->json('keyword_usage')->nullable()->comment('Array of {keyword: count} - RHL, RA, OI, HL, MYBOOK, STOP');

            // Processing timestamps
            $table->dateTime('received_at')->nullable()->comment('Email received timestamp');
            $table->dateTime('processed_at')->nullable()->comment('When we processed this report');

            $table->timestamps();

            // Unique constraint on report month
            $table->unique(['report_month', 'branch_name'], 'sms_month_branch_unique');
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
