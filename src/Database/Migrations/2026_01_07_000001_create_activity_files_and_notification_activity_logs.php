<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Catalog of raw activity files (exports, notification logs, summary reports, Shoutbomb emails, etc.)
        Schema::create('activity_files', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('type', 50); // ftp_export, notification_log, summary_report_email, shoutbomb_email, etc.
            $table->string('original_name')->nullable(); // filename or email subject
            $table->string('disk', 50)->nullable(); // Laravel storage disk name
            $table->string('path')->nullable(); // Path within disk
            $table->string('hash', 64)->nullable(); // Optional content hash for deduplication

            // Optional link to the job run that processed this file
            $table->unsignedBigInteger('job_run_id')->nullable();

            $table->dateTime('parsed_at')->nullable(); // When this file was first ingested/parsed
            $table->dateTime('file_date')->nullable(); // Date encoded in filename or report

            $table->json('metadata')->nullable(); // Arbitrary JSON metadata (report type, counts, etc.)

            $table->timestamps();

            $table->index(['type', 'file_date'], 'activity_files_type_date_idx');
            $table->index('hash', 'activity_files_hash_idx');
            $table->index('job_run_id', 'activity_files_job_run_idx');
        });

        // Per-notification activity log
        Schema::create('notification_activity_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('notification_id');

            // When this notification was modified or enriched by this event
            $table->dateTime('occurred_at')->nullable();

            // High-level source of the event: export_file, notification_log, summary_report,
            // shoutbomb_failure, issue_detector, user, system, etc.
            $table->string('source', 50);

            // Optional link to the originating file in activity_files
            $table->unsignedBigInteger('activity_file_id')->nullable();

            // Optional link to a canonical data row (e.g., delivery_confirmations.id,
            // ingested_email_lines.id). We store both type and id for flexibility.
            $table->string('line_type', 50)->nullable();
            $table->unsignedBigInteger('line_id')->nullable();

            // Human-readable summary of what happened
            $table->string('description', 255);

            // Optional link back to the job run that produced this event
            $table->unsignedBigInteger('job_run_id')->nullable();

            // Additional structured metadata (status ids, failure types, issue ids, etc.)
            $table->json('meta')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('notification_id', 'notif_activity_notification_idx');
            $table->index('occurred_at', 'notif_activity_occurred_idx');
            $table->index(['source', 'occurred_at'], 'notif_activity_source_occurred_idx');
            $table->index('activity_file_id', 'notif_activity_file_idx');
            $table->index('job_run_id', 'notif_activity_job_run_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_activity_logs');
        Schema::dropIfExists('activity_files');
    }
};
