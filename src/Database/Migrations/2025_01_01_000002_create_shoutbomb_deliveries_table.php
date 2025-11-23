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
        Schema::create('shoutbomb_deliveries', function (Blueprint $table) {
            $table->id();

            // Delivery details
            $table->string('patron_barcode', 20)->nullable()->index();
            $table->string('phone_number', 20)->index();
            $table->enum('delivery_type', ['SMS', 'Voice'])->index();
            $table->string('message_type', 50)->nullable()->comment('Hold, Overdue, AlmostOverdue, etc.');

            // Delivery status
            $table->dateTime('sent_date')->index();
            $table->enum('status', ['Delivered', 'Failed', 'Pending', 'Invalid'])->default('Pending');
            $table->string('carrier', 100)->nullable();
            $table->text('failure_reason')->nullable();

            // Source information
            $table->string('report_file')->nullable()->comment('Source report filename');
            $table->enum('report_type', ['Daily', 'Weekly', 'Monthly', 'text_delivery', 'voice_delivery'])->nullable();

            // Import tracking
            $table->timestamp('imported_at')->useCurrent();
            $table->timestamps();

            // Composite indexes
            $table->index(['sent_date', 'delivery_type']);
            $table->index(['sent_date', 'status']);
            $table->index(['patron_barcode', 'sent_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shoutbomb_deliveries');
    }
};
