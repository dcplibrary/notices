<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This table tracks the OFFICIAL SQL-generated submission files
     * sent to Shoutbomb (holds, overdue, renew).
     */
    public function up(): void
    {
        Schema::create('shoutbomb_submissions', function (Blueprint $table) {
            $table->id();

            // Notification details
            $table->enum('notification_type', ['holds', 'overdue', 'renew'])->index();
            $table->string('patron_barcode', 20)->index();
            $table->string('phone_number', 20)->nullable()->index();

            // Item details (common)
            $table->string('title')->nullable();
            
            // Hold-specific fields
            $table->string('item_id', 50)->nullable()->comment('SysHoldRequestID for holds, ItemBarcode for overdue/renew');
            $table->integer('branch_id')->nullable()->comment('PickupOrganizationID for holds');
            $table->date('pickup_date')->nullable()->comment('CreationDate for holds');
            $table->date('expiration_date')->nullable()->comment('HoldTillDate for holds, DueDate for overdue/renew');
            
            // Overdue/Renew-specific fields
            $table->integer('item_record_id')->nullable()->comment('ItemRecordID for overdue/renew');
            $table->integer('renewals')->nullable()->comment('Renewal count for overdue/renew');
            $table->integer('bibliographic_record_id')->nullable()->comment('BibRecordID for overdue/renew');
            $table->integer('renewal_limit')->nullable()->comment('RenewalLimit for overdue/renew');

            // Submission tracking
            $table->dateTime('submitted_at')->index();
            $table->string('source_file')->comment('Original filename');
            $table->enum('delivery_type', ['voice', 'text'])->nullable()->index();

            // Import tracking
            $table->timestamp('imported_at')->useCurrent();
            $table->timestamps();

            // Indexes for common queries
            $table->index(['patron_barcode', 'submitted_at']);
            $table->index(['submitted_at', 'notification_type']);
            $table->index(['phone_number', 'submitted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shoutbomb_submissions');
    }
};
