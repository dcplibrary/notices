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
        Schema::create('delivery_methods', function (Blueprint $table) {
            // Mirrors Polaris.SA_DeliveryOptions structure
            $table->integer('delivery_option_id')->primary()->comment('Polaris DeliveryOptionID');
            $table->string('delivery_option', 100)->comment('Polaris delivery method name');
            
            // Additional useful fields
            $table->string('label')->nullable()->comment('Short label for UI');
            $table->text('description')->nullable()->comment('Extended description for UI');
            $table->boolean('enabled')->default(true)->comment('Whether this method is in use');
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
        Schema::dropIfExists('delivery_methods');
    }
};
