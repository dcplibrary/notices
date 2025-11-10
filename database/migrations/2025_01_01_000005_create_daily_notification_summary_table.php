<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_notification_summary', function (Blueprint $table) {
            $table->id();

            // Summary period
            $table->date('summary_date')->index();

            // Notification breakdown
            $table->integer('notification_type_id')->index();
            $table->integer('delivery_option_id')->index();

            // Counts
            $table->integer('total_sent')->default(0);
            $table->integer('total_success')->default(0);
            $table->integer('total_failed')->default(0);
            $table->integer('total_pending')->default(0);

            // Item counts (aggregated)
            $table->integer('total_holds')->default(0);
            $table->integer('total_overdues')->default(0);
            $table->integer('total_overdues_2nd')->default(0);
            $table->integer('total_overdues_3rd')->default(0);
            $table->integer('total_cancels')->default(0);
            $table->integer('total_recalls')->default(0);
            $table->integer('total_bills')->default(0);

            // Unique patron count
            $table->integer('unique_patrons')->default(0);

            // Calculated fields
            $table->decimal('success_rate', 5, 2)->nullable()->comment('Percentage of successful deliveries');
            $table->decimal('failure_rate', 5, 2)->nullable()->comment('Percentage of failed deliveries');

            // Aggregation tracking
            $table->timestamp('aggregated_at')->useCurrent();
            $table->timestamps();

            // Unique constraint to prevent duplicate summaries
            $table->unique(['summary_date', 'notification_type_id', 'delivery_option_id'], 'unique_daily_summary');

            // Composite indexes for dashboard queries
            $table->index(['summary_date', 'notification_type_id'], 'dns_date_type_idx');
            $table->index(['summary_date', 'delivery_option_id'], 'dns_date_delivery_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_notification_summary');
    }
};
