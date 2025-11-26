<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations - Create processed_files table for tracking imports.
     */
    public function up(): void
    {
        Schema::create('processed_files', function (Blueprint $table) {
            $table->id();
            $table->string('filename')->index();
            $table->string('file_type')->index(); // 'patron_list', 'phone_notice', 'submission'
            $table->string('category')->nullable()->index(); // 'voice', 'text', 'holds', 'overdue', etc.
            $table->string('status')->default('completed')->index(); // 'completed', 'failed', 'partial'
            $table->integer('records_processed')->default(0);
            $table->integer('records_new')->default(0);
            $table->integer('records_updated')->default(0);
            $table->integer('records_unchanged')->default(0);
            $table->integer('records_errors')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->index();
            $table->timestamps();

            // Composite index for common queries
            $table->index(['file_type', 'status', 'processed_at'], 'idx_type_status_date');
            $table->index(['filename', 'file_type'], 'idx_filename_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processed_files');
    }
};
