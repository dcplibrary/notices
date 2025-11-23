<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This table stores delivery failures from ShoutBomb with enrichment capabilities.
     *
     * Source Files (Email Reports):
     *   1. "Invalid patron phone number [Date]" - Daily invalid/opted-out phones (6am)
     *   2. "Voice notices that were not delivered on [Date]" - Daily voice failures (4:10pm)
     *
     * This enhanced table supports linking failures back to:
     *   - PhoneNotices.csv (validation baseline)
     *   - Notification exports (holds, overdue, renew)
     */
    public function up(): void
    {
        Schema::create('notice_failure_reports', function (Blueprint $table) {
            $table->id();

            // Email source identification
            $table->string('outlook_message_id', 255)->nullable()->unique()->comment('From Graph API');
            $table->string('subject', 500)->nullable()->comment('Email subject line');
            $table->string('from_address', 255)->nullable()->comment('From: field');

            // Patron/Contact Information (parsed from email)
            $table->char('patron_phone', 10)->nullable()->index()->comment('10-digit phone number parsed from email');
            $table->integer('patron_id')->nullable()->index()->comment('Polaris patron ID if available');
            $table->string('patron_barcode', 20)->nullable()->index()->comment('May be partial (last 4 digits)');
            $table->boolean('barcode_partial')->default(false)->comment('True if barcode is XXXX#### redacted format');
            $table->string('patron_name', 255)->nullable()->comment('LAST, FIRST format from voice failure emails');

            // Contact type/value (NEW fields for flexibility)
            $table->enum('contact_type', ['phone', 'email'])->default('phone')->comment('Type of contact that failed');
            $table->string('contact_value', 255)->nullable()->comment('Phone number or email that failed');

            // Enrichment fields - linked to PhoneNotices
            $table->integer('notification_type_id')->nullable()->index()
                ->comment('Enriched from PhoneNotices - 1=Overdue, 2=Hold, 7=Renewal, etc.');
            $table->tinyInteger('delivery_option_id')->nullable()->index()
                ->comment('From email parse: SMS→8, Voice failure→3');
            $table->integer('item_record_id')->nullable()->comment('Enriched from PhoneNotices');
            $table->integer('sys_hold_request_id')->nullable()->comment('Enriched from PhoneNotices (holds only)');
            $table->integer('bibliographic_record_id')->nullable()->comment('Enriched from PhoneNotices');

            // Timestamp enrichment
            $table->dateTime('notification_queued_at')->nullable()->comment('When Polaris queued the notification');
            $table->dateTime('notification_sent_at')->nullable()->comment('Approximate from email date');
            $table->dateTime('export_timestamp')->nullable()->comment('From notification export file');

            // Delivery method and failure details
            $table->enum('delivery_method', ['voice', 'sms'])->nullable()->index()
                ->comment('voice or sms');
            $table->string('failure_type', 100)->nullable()->index()
                ->comment('opted-out, invalid, voice-not-delivered, etc.');
            $table->text('failure_reason')->nullable()->comment('Detailed failure reason from email');
            $table->string('failure_category', 50)->nullable()->index()
                ->comment('Categorized: invalid_number, opted_out, no_answer, busy, etc.');

            // Additional context
            $table->string('account_status', 50)->nullable()->comment('If available from email');
            $table->string('notice_description', 255)->nullable()->comment('message_type from voice failure');
            $table->tinyInteger('attempt_count')->nullable()->comment('Number of delivery attempts if available');

            // Foreign key references (for enrichment linking)
            $table->unsignedBigInteger('phone_notices_import_id')->nullable()
                ->comment('FK to polaris_phone_notices.id');
            $table->unsignedBigInteger('notification_export_id')->nullable()
                ->comment('FK to notifications_holds/overdue/renewal.id');

            // Processing timestamps
            $table->dateTime('received_at')->nullable()->index()->comment('Email received timestamp');
            $table->dateTime('processed_at')->nullable()->comment('When we processed this failure');

            // Debug storage
            $table->longText('raw_content')->nullable()->comment('Full email body for debugging');

            // Standard timestamps
            $table->timestamps();

            // Indexes for common queries
            $table->index(['received_at', 'failure_type']);
            $table->index(['patron_barcode', 'received_at']);
            $table->index(['patron_phone', 'received_at']);
            $table->index(['delivery_method', 'failure_type']);
            $table->index(['notification_type_id', 'received_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notice_failure_reports');
    }
};
