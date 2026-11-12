<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('activity_log')) {
            return;
        }

        Schema::create('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Polymorphic user reference (works with any auth user model)
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            // What happened
            $table->string('action', 100); // e.g. notification.status.updated, import.run
            $table->string('description')->nullable();

            // Polymorphic subject this log entry refers to (e.g. Notification, EmailReport)
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // Structured details (before/after, command options, file names, etc.)
            $table->json('meta')->nullable();

            // Optional request context
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            // Indexes (short names for MySQL)
            $table->index(['subject_type', 'subject_id'], 'activity_subject_idx');
            $table->index(['user_type', 'user_id'], 'activity_user_idx');
            $table->index('action', 'activity_action_idx');
            $table->index('created_at', 'activity_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
