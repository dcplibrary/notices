<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('job_runs', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('job_name', 150); // e.g. notices:import, notices:import-ftp, notices:process-orphaned
            $table->string('trigger_type', 50)->default('manual'); // manual, schedule, queue

            // Optional link to the user that triggered the job (for manual runs)
            $table->unsignedBigInteger('triggered_by_user_id')->nullable();

            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();

            $table->string('status', 50)->default('running'); // running, success, failed, partial
            $table->text('error_message')->nullable();

            $table->json('meta')->nullable(); // date ranges, flags, server info, etc.

            $table->timestamps();

            $table->index(['job_name', 'started_at'], 'job_runs_name_started_idx');
            $table->index(['status', 'started_at'], 'job_runs_status_started_idx');
        });

        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // App user who performed the action (polymorphic-friendly if needed)
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_type')->nullable(); // optional morph type

            // Optional direct link to a notification this action touched
            $table->unsignedBigInteger('notification_id')->nullable();

            // Optional link to a job run this action started or is associated with
            $table->unsignedBigInteger('job_run_id')->nullable();

            $table->dateTime('occurred_at');

            // Short action key: resolve_issue, change_status, update_settings, run_import, etc.
            $table->string('action', 100);

            // Human-readable description for UI
            $table->string('description', 255);

            // Extra context: route name, IP, old/new values, issue ids, etc.
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'occurred_at'], 'user_activity_user_occurred_idx');
            $table->index(['notification_id', 'occurred_at'], 'user_activity_notification_occurred_idx');
            $table->index(['job_run_id', 'occurred_at'], 'user_activity_job_occurred_idx');
            $table->index(['action', 'occurred_at'], 'user_activity_action_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_logs');
        Schema::dropIfExists('job_runs');
    }
};
