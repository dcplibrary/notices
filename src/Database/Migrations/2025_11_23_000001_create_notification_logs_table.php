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
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();

            // Polaris source data
            $table->integer('polaris_log_id')->unique()->nullable()->comment('NotificationLogID from Polaris');
            $table->integer('patron_id')->comment('PatronID from Polaris');
            $table->string('patron_barcode', 20)->nullable();
            
            // Contact fields (parsed from delivery_string for better searchability)
            $table->string('phone', 20)->nullable()->index('nl_phone_idx')->comment('Normalized phone number for SMS/Voice');
            $table->string('email', 255)->nullable()->index('nl_email_idx')->comment('Email address for Email notifications');
            $table->string('patron_name', 255)->nullable()->comment('Patron name for search');
            
            // Item information
            $table->string('item_barcode', 50)->nullable()->index('nl_item_barcode_idx')->comment('Primary item barcode in notification');
            $table->text('item_title')->nullable()->comment('Item title for display');

            // Notification details
            $table->dateTime('notification_date')->index()->comment('NotificationDateTime from Polaris');

            // These IDs are required for real imported data but are nullable here to
            // keep tests and exploratory inserts simple. Domain logic/scopes still
            // treat them as required when present.
            $table->integer('notification_type_id')->nullable()->index()->comment('1=Overdue, 2=Hold, 7=AlmostOverdue, etc.');
            $table->integer('delivery_option_id')->nullable()->index()->comment('1=Mail, 2=Email, 3=Voice, 8=SMS');
            $table->integer('notification_status_id')->nullable()->index()->comment('12=Success, 14=Failed, etc.');
            $table->enum('status', ['completed', 'pending', 'failed'])->default('pending')->index()->comment('Simplified status for queries');
            $table->text('status_description')->nullable()->comment('Human-readable status description');

            // Delivery information
            $table->string('delivery_string')->nullable()->comment('Email address, phone number, or mailing address');

            // Counts
            $table->integer('holds_count')->default(0);
            $table->integer('overdues_count')->default(0);
            $table->integer('overdues_2nd_count')->default(0);
            $table->integer('overdues_3rd_count')->default(0);
            $table->integer('cancels_count')->default(0);
            $table->integer('recalls_count')->default(0);
            $table->integer('routings_count')->default(0);
            $table->integer('bills_count')->default(0);
            $table->integer('manual_bill_count')->default(0);

            // Additional fields
            $table->integer('reporting_org_id')->nullable()->index();
            $table->integer('language_id')->nullable();
            $table->string('carrier_name')->nullable()->comment('For SMS/Voice - mobile carrier');
            $table->text('details')->nullable()->comment('Additional details from Polaris');
            $table->boolean('reported')->default(false)->comment('Flagged as reported in Polaris');

            // Import tracking
            $table->timestamp('imported_at')->useCurrent();
            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['notification_date', 'notification_type_id']);
            $table->index(['notification_date', 'delivery_option_id']);
            $table->index(['patron_id', 'notification_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
