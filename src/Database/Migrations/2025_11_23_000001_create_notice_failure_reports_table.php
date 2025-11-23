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
        $tableName = config('notices.integrations.shoutbomb_reports.table', 'notice_failure_reports');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();

            // Outlook message identifiers
            $table->string('outlook_message_id')->index();
            $table->string('subject')->nullable();
            $table->string('from_address')->nullable();

            // Shoutbomb patron details
            $table->string('patron_phone', 20)->nullable()->index();
            $table->string('patron_id', 50)->nullable()->index();
            $table->string('patron_barcode', 50)->nullable()->index();
            $table->boolean('barcode_partial')->default(false)
                ->comment('True if patron_barcode contains only last 4 digits (XXXXXXXXXX####)');
            $table->string('patron_name')->nullable();

            // Failure details
            $table->string('notice_type', 20)->nullable()->index(); // SMS, Voice, etc.
            $table->string('failure_type', 50)->nullable()->index(); // opted-out, invalid, voice-not-delivered
            $table->text('failure_reason')->nullable();
            $table->string('account_status', 20)->nullable()->index(); // active, deleted, unavailable
            $table->string('notice_description')->nullable(); // For voice failures
            $table->integer('attempt_count')->nullable();

            // Timestamps
            $table->timestamp('received_at')->nullable()->index();
            $table->timestamp('processed_at')->nullable()->index();

            // Raw content (optional, for debugging)
            $table->longText('raw_content')->nullable();

            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['failure_type', 'received_at']);
            $table->index(['notice_type', 'received_at']);
            $table->index(['patron_phone', 'received_at']);
            $table->index(['barcode_partial', 'patron_barcode']);

            // Unique constraint: same failure shouldn't be recorded twice from same email
            $table->unique(['outlook_message_id', 'patron_phone', 'patron_id'], 'unique_failure_per_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('notices.integrations.shoutbomb_reports.table', 'notice_failure_reports');
        Schema::dropIfExists($tableName);
    }
};
