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
        Schema::create('notification_types', function (Blueprint $table) {
            // Mirrors Polaris.NotificationTypes structure
            $table->integer('notification_type_id')->primary()->comment('Polaris NotificationTypeID');
            $table->string('description', 80)->comment('Type description from Polaris');
            $table->string('label')->nullable()->comment('Short label for UI');
            $table->boolean('enabled')->default(true)->comment('Whether type is active');
            $table->integer('display_order')->default(0)->comment('Sort order for UI display');
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_types');
    }
};
