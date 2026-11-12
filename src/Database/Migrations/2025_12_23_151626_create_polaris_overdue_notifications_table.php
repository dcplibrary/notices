<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Stores raw overdue/fine/bill notification exports from Polaris.
     * These are queue-based notifications for items that are overdue.
     *
     * Note: For overdue notifications, delivery_option_id comes from
     * NotificationQueue and may be NULL, so we don't enforce a foreign
     * key constraint. Similarly, notification_type_id and material_type_id
     * are nullable, so we don't enforce foreign keys on them.
     *
     * Overdue Notification Types (NotificationTypeID):
     * - 1: 1st Overdue (1 day after due date)
     * - 8: 2nd Overdue (7 days after due date)
     * - 11: 3rd Overdue (14 days after due date)
     * - 12: Bill (21 days after due date)
     * - 13: Collection Agency (42 days after due date - final notice)
     */
    public function up(): void
    {
        Schema::create('polaris_overdue_notifications', function (Blueprint $table) {
            $table->id();

            // Correlation keys
            $table->unsignedBigInteger('patron_id')->nullable();
            $table->unsignedBigInteger('item_record_id')->nullable();
            $table->unsignedBigInteger('notification_type_id')->nullable();
            $table->unsignedBigInteger('delivery_option_id')->nullable();

            // Queue identity
            $table->unsignedBigInteger('notification_queue_id')->nullable();
            $table->boolean('processed')->nullable();
            $table->dateTime('notification_creation_date')->nullable();

            // Patron / item
            $table->string('patron_barcode', 50)->nullable();
            $table->string('item_barcode', 50)->nullable();
            $table->string('title')->nullable();
            $table->unsignedBigInteger('bibliographic_record_id')->nullable();

            // Additional patron data
            $table->string('patron_first_name', 100)->nullable();
            $table->string('patron_last_name', 100)->nullable();
            $table->string('patron_email', 255)->nullable();
            $table->string('patron_phone', 50)->nullable();
            $table->unsignedInteger('patron_record_status_id')->nullable();
            $table->date('patron_expiration_date')->nullable();

            // Circulation
            $table->date('due_date')->nullable();
            $table->integer('days_until_due')->nullable();
            $table->unsignedInteger('renewals')->nullable();
            $table->unsignedInteger('renewal_limit')->nullable();
            $table->unsignedBigInteger('material_type_id')->nullable();
            $table->unsignedInteger('checkout_branch_id')->nullable();
            $table->unsignedInteger('loan_units')->nullable();

            // Hold lifecycle (mostly NULL for overdue notifications)
            $table->unsignedBigInteger('sys_hold_request_id')->nullable();
            $table->unsignedInteger('sys_hold_status_id')->nullable();
            $table->date('activation_date')->nullable();
            $table->date('hold_notification_date')->nullable();
            $table->date('hold_till_date')->nullable();
            $table->unsignedInteger('pickup_organization_id')->nullable();
            $table->string('site_code', 20)->nullable();
            $table->string('site_name', 255)->nullable();
            $table->unsignedInteger('hold_pickup_area_id')->nullable();
            $table->boolean('suspended')->nullable();

            // Overdue status flags
            $table->unsignedInteger('system_blocks')->nullable();
            $table->unsignedInteger('ovd_notice_count')->nullable();
            $table->dateTime('ovd_notice_date')->nullable();
            $table->boolean('billing_notice_sent')->nullable();

            // Export timestamp
            $table->dateTime('export_datetime')->nullable();

            // Traceability back to original export files
            $table->string('source_filename', 191)->nullable();
            $table->unsignedInteger('source_line_number')->nullable();
            $table->text('source_raw_line')->nullable();
            $table->dateTime('imported_at')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index('patron_id');
            $table->index('patron_barcode');
            $table->index('item_record_id');
            $table->index('notification_type_id');
            $table->index('delivery_option_id');
            $table->index('due_date');
            $table->index('notification_creation_date');
            $table->index('export_datetime');

            // Foreign key constraints
            // Nullable fields can have foreign keys - the constraint only validates when value is present
            $table->foreign('notification_type_id')
                ->references('id')
                ->on('notification_types')
                ->onDelete('restrict')
            ;

            $table->foreign('delivery_option_id')
                ->references('id')
                ->on('delivery_options')
                ->onDelete('restrict')
            ;

            $table->foreign('material_type_id')
                ->references('id')
                ->on('material_types')
                ->onDelete('restrict')
            ;
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('polaris_overdue_notifications');
    }
};
