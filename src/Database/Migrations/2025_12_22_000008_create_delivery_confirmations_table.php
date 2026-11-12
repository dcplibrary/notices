<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('delivery_confirmations')) {
            return;
        }

        Schema::create('delivery_confirmations', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Source of confirmation: notification_log, shoutbomb_voice_failure, shoutbomb_invalid_phone, etc.
            $table->string('confirmation_source', 50);
            $table->string('source_filename')->nullable()->comment('NotificationLog file name for grace period calculation');

            // ID of the record in the source system (NotificationLogID, EmailReport ID, etc.)
            $table->string('source_record_id')->nullable();

            // Link to unified notification when matched (optional early on)
            $table->unsignedBigInteger('notification_id')->nullable();
            $table->string('notification_key', 191)->nullable();

            // Core correlation keys (from NotificationLog or other sources)
            $table->unsignedBigInteger('patron_id')->nullable();
            $table->unsignedBigInteger('item_record_id')->nullable();
            $table->unsignedBigInteger('notification_type_id')->nullable();
            $table->unsignedBigInteger('delivery_option_id')->nullable();
            $table->unsignedInteger('reporting_org_id')->nullable();

            // When the notice was logged as sent / processed
            $table->dateTime('notice_date')->nullable();

            // Status code from source (e.g. NotificationStatusID)
            $table->unsignedBigInteger('notification_status_id')->nullable();

            // Target address and details (email/phone, error text, etc.)
            $table->string('delivery_string')->nullable();
            $table->text('details')->nullable();

            // Batch-level counts from notification_log (holds, overdues, etc.)
            $table->unsignedInteger('holds_count')->nullable();
            $table->unsignedInteger('cancels_count')->nullable();

            // Patron context
            $table->string('patron_barcode', 50)->nullable();

            // Optional link to ingested_emails when confirmation comes from email
            $table->unsignedBigInteger('ingested_email_id')->nullable();

            $table->timestamps();

            // Indexes for common lookups
            $table->index('confirmation_source', 'delivery_conf_source_idx');
            $table->index('notification_key', 'delivery_conf_notif_key_idx');
            $table->index(['patron_id', 'notification_type_id', 'delivery_option_id'], 'delivery_conf_patron_type_do_idx');
            $table->index('notice_date', 'delivery_conf_notice_date_idx');

            // Foreign key constraints
            $table->foreign('notification_id')
                ->references('id')
                ->on('notifications')
                ->onDelete('set null')
            ;

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

            $table->foreign('notification_status_id')
                ->references('id')
                ->on('notification_statuses')
                ->onDelete('restrict')
            ;

            $table->foreign('ingested_email_id')
                ->references('id')
                ->on('ingested_emails')
                ->onDelete('set null')
            ;
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_confirmations');
    }
};
