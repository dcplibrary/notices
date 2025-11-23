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
        Schema::create('notification_statuses', function (Blueprint $table) {
            // Mirrors Polaris.NotificationStatuses structure
            $table->integer('notification_status_id')->primary()->comment('Polaris NotificationStatusID');
            $table->string('description', 255)->comment('Status description from Polaris');
            $table->string('label')->nullable()->comment('Short label for UI');
            $table->boolean('enabled')->default(true)->comment('Whether status is active');
            $table->integer('display_order')->default(0)->comment('Sort order for UI display');
            $table->string('category', 20)->nullable()->comment('completed, pending, or failed');
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_statuses');
    }
};
