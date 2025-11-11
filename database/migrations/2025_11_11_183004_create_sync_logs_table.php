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
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('operation_type'); // 'sync_all', 'import_polaris', 'import_shoutbomb', 'aggregate'
            $table->string('status'); // 'running', 'completed', 'completed_with_errors', 'failed'
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->json('results')->nullable(); // Detailed results for each step
            $table->text('error_message')->nullable();
            $table->integer('records_processed')->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // Who triggered it
            $table->timestamps();
            
            $table->index('operation_type');
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
