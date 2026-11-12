<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('notes')) {
            return;
        }

        Schema::create('notes', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Polymorphic subject (Notification, patron-like entity, EmailReport, etc.)
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');

            // Who wrote the note (optional, works with any user model)
            $table->string('author_type')->nullable();
            $table->unsignedBigInteger('author_id')->nullable();

            // Optional categorization (e.g. investigation, resolution, follow_up)
            $table->string('category', 50)->nullable();

            // The actual note content
            $table->text('body');

            // Simple flags
            $table->boolean('is_private')->default(false);

            // Extra structured data if needed later
            $table->json('meta')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['subject_type', 'subject_id'], 'notes_subject_idx');
            $table->index(['author_type', 'author_id'], 'notes_author_idx');
            $table->index('category', 'notes_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
