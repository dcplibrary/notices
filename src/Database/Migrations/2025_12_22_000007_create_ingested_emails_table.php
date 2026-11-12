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
        if (Schema::hasTable('ingested_emails')) {
            return;
        }

        Schema::create('ingested_emails', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique()->comment('Microsoft Graph message ID');
            $table->foreignId('email_report_type_id')
                ->nullable()
                ->constrained('email_report_types')
                ->onDelete('set null')
                ->comment('Type of email report (failure, summary, etc.)')
            ;
            $table->string('subject')->nullable();
            $table->text('body_preview')->nullable()->comment('First 500 characters of body');
            $table->longText('body_full')->nullable()->comment('Complete email body (plain text)');
            $table->timestamp('received_at')->nullable()->comment('When email was received');
            $table->timestamp('imported_at')->nullable()->comment('When we ingested this email');
            $table->string('sender_email')->nullable();
            $table->integer('attachments_count')->default(0);
            $table->json('raw_metadata')->nullable()->comment('Full Microsoft Graph API response');

            // Parsed data fields (from email_reports)
            $table->json('parsed_data')->nullable()->comment('Parsed failure/invalid records');
            $table->unsignedInteger('parsed_record_count')->default(0)->comment('Number of records in parsed_data');
            $table->string('processing_status', 20)->default('processed');
            $table->dateTime('processed_at')->nullable();
            $table->text('processing_notes')->nullable();
            $table->text('error_message')->nullable();
            $table->date('report_date')->nullable()->comment('Logical report date');
            $table->unsignedInteger('failures_count')->nullable()->comment('Count of failures in report');
            $table->unsignedInteger('invalid_phones_count')->nullable()->comment('Count of invalid phones');

            $table->timestamps();

            $table->index('received_at');
            $table->index('imported_at');
            $table->index('sender_email');
            $table->index('processing_status');
            $table->index('report_date');
            $table->index('email_report_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingested_emails');
    }
};
