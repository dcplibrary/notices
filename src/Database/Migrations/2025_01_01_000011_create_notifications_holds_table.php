<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This table stores hold notifications sent to ShoutBomb from holds*.txt exports.
     *
     * Source File: holds_submitted_YYYY-MM-DD_HH-MM-SS_.txt
     * Export Schedule: 4x daily (8am, 9am, 1pm, 5pm)
     *
     * File Format (pipe-delimited, 7 fields):
     *   Field 1: BrowseTitle
     *   Field 2: CreationDate (YYYY-MM-DD)
     *   Field 3: SysHoldRequestID (primary linking key)
     *   Field 4: PatronID
     *   Field 5: PickupOrganizationID (always 3 for DCPL)
     *   Field 6: HoldTillDate (YYYY-MM-DD)
     *   Field 7: PatronBarcode
     *
     * CRITICAL: holds*.txt always contains notification_type_id = 2 (Hold)
     */
    public function up(): void
    {
        Schema::create('notifications_holds', function (Blueprint $table) {
            $table->id();

            // Field 1: Browse Title
            $table->text('browse_title')->nullable()->comment('Field 1 - Item title');

            // Field 2: Creation Date
            $table->date('creation_date')->nullable()->comment('Field 2 - When hold was placed');

            // Field 3: Sys Hold Request ID (PRIMARY LINKING KEY)
            $table->integer('sys_hold_request_id')->index()->comment('Field 3 - Hold request ID for linking');

            // Field 4: Patron ID
            $table->integer('patron_id')->index()->comment('Field 4 - Internal Polaris patron ID');

            // Field 5: Pickup Organization ID
            $table->integer('pickup_organization_id')->nullable()->comment('Field 5 - Branch ID, always 3 for DCPL');

            // Field 6: Hold Till Date
            $table->date('hold_till_date')->nullable()->comment('Field 6 - When hold expires');

            // Field 7: Patron Barcode
            $table->string('patron_barcode', 20)->index()->comment('Field 7');

            // Notification type - ALWAYS 2 for holds (inferred from filename)
            $table->tinyInteger('notification_type_id')->default(2)->comment('Always 2 (Hold) - inferred from holds*.txt');

            // Delivery option - enriched from patron lists
            $table->tinyInteger('delivery_option_id')->nullable()->index()
                ->comment('3=Voice, 8=Text - enriched from patron list membership');

            // Export timestamp parsed from filename
            $table->dateTime('export_timestamp')->index()
                ->comment('Parsed from: holds_submitted_YYYY-MM-DD_HH-MM-SS_.txt');

            // Import tracking
            $table->string('source_file', 255)->nullable()->comment('Original filename');
            $table->timestamp('imported_at')->useCurrent();
            $table->timestamps();

            // Composite indexes
            $table->index(['patron_barcode', 'export_timestamp']);
            $table->index(['sys_hold_request_id', 'export_timestamp']);
            $table->index(['export_timestamp', 'notification_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications_holds');
    }
};
