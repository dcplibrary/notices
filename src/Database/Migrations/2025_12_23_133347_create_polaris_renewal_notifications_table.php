<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('polaris_renewal_notifications', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Columns mirror the Polaris renew export query aliases as closely as possible.
            $table->unsignedBigInteger('patron_id')->nullable();
            $table->unsignedBigInteger('item_record_id')->nullable();
            $table->unsignedBigInteger('notification_type_id')->nullable();
            $table->unsignedBigInteger('delivery_option_id')->nullable();

            $table->unsignedBigInteger('notification_queue_id')->nullable();
            $table->boolean('processed')->nullable();
            $table->dateTime('notification_creation_date')->nullable();

            $table->string('patron_barcode', 20)->nullable();
            $table->string('item_barcode', 20)->nullable();
            $table->text('title')->nullable();
            $table->unsignedBigInteger('bibliographic_record_id')->nullable();

            $table->string('patron_first_name', 32)->nullable();
            $table->string('patron_last_name', 100)->nullable();
            $table->string('patron_email', 64)->nullable();
            $table->string('patron_phone', 20)->nullable();
            $table->unsignedInteger('patron_record_status_id')->nullable();
            $table->date('patron_expiration_date')->nullable();

            $table->date('due_date')->nullable();
            $table->integer('days_until_due')->nullable();
            $table->integer('renewals')->nullable();
            $table->integer('renewal_limit')->nullable();
            $table->unsignedBigInteger('material_type_id')->nullable();
            $table->unsignedInteger('checkout_branch_id')->nullable();
            $table->integer('loan_units')->nullable();

            $table->unsignedBigInteger('sys_hold_request_id')->nullable();
            $table->unsignedBigInteger('sys_hold_status_id')->nullable();
            $table->date('activation_date')->nullable();
            $table->date('hold_notification_date')->nullable();
            $table->date('hold_till_date')->nullable();
            $table->unsignedInteger('pickup_organization_id')->nullable();
            $table->string('site_code', 20)->nullable();
            $table->string('site_name', 191)->nullable();
            $table->unsignedInteger('hold_pickup_area_id')->nullable();
            $table->boolean('suspended')->nullable();

            $table->integer('system_blocks')->nullable();
            $table->integer('ovd_notice_count')->nullable();
            $table->date('ovd_notice_date')->nullable();
            $table->boolean('billing_notice_sent')->nullable();

            // Stored as string in export; keep as string here as well.
            $table->string('export_datetime', 23)->nullable();

            // Traceability back to original export files
            $table->string('source_filename', 191)->nullable();
            $table->unsignedInteger('source_line_number')->nullable();
            $table->text('source_raw_line')->nullable();
            $table->dateTime('imported_at')->nullable();

            $table->timestamps();

            $table->index(['patron_id', 'item_record_id']);
            $table->index(['patron_barcode']);
            // Short index name to satisfy MySQL's 64 character limit
            $table->index(['notification_type_id', 'delivery_option_id'], 'polaris_renew_notif_type_deliv_idx');
            $table->index(['due_date']);

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

    public function down(): void
    {
        Schema::dropIfExists('polaris_renewal_notifications');
    }
};
