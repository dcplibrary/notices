<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * This table stores renewal reminder notifications sent to ShoutBomb from renew*.txt exports.
     *
     * Source File: renew_submitted_YYYY-MM-DD_HH-MM-SS.txt
     * Export Schedule: Daily at 8:03am
     *
     * File Format (pipe-delimited, 13 fields) - SAME as overdue:
     *   Field 1:  PatronID
     *   Field 2:  ItemBarcode
     *   Field 3:  Title
     *   Field 4:  DueDate (YYYY-MM-DD, typically 3-4 days in future)
     *   Field 5:  ItemRecordID
     *   Field 6-9: Dummy fields (always empty ||||)
     *   Field 10: Renewals (times renewed)
     *   Field 11: BibliographicRecordID
     *   Field 12: RenewalLimit (max renewals)
     *   Field 13: PatronBarcode
     *
     * CRITICAL: renew*.txt always contains notification_type_id = 7 (Renewal Reminder)
     */
    public function up(): void
    {
        Schema::create('notifications_renewal', function (Blueprint $table) {
            $table->id();

            // Field 1: Patron ID
            $table->integer('patron_id')->index()->comment('Field 1 - Internal Polaris patron ID');

            // Field 2: Item Barcode
            $table->string('item_barcode', 50)->index()->comment('Field 2');

            // Field 3: Title
            $table->text('title')->nullable()->comment('Field 3');

            // Field 4: Due Date (typically 3-4 days in future)
            $table->date('due_date')->nullable()->comment('Field 4 - YYYY-MM-DD, future date');

            // Field 5: Item Record ID
            $table->integer('item_record_id')->index()->comment('Field 5');

            // Fields 6-9: Dummy fields (not stored)

            // Field 10: Renewals
            $table->tinyInteger('renewals')->nullable()->comment('Field 10 - Times renewed');

            // Field 11: Bibliographic Record ID
            $table->integer('bibliographic_record_id')->nullable()->comment('Field 11');

            // Field 12: Renewal Limit
            $table->tinyInteger('renewal_limit')->nullable()->comment('Field 12 - Max renewals allowed');

            // Field 13: Patron Barcode
            $table->string('patron_barcode', 20)->index()->comment('Field 13');

            // Notification type - ALWAYS 7 for renewals (inferred from filename)
            $table->tinyInteger('notification_type_id')->default(7)
                ->comment('Always 7 (Renewal Reminder) - inferred from renew*.txt');

            // Delivery option - enriched from patron lists
            $table->tinyInteger('delivery_option_id')->nullable()->index()
                ->comment('3=Voice, 8=Text - enriched from patron list membership');

            // Export timestamp parsed from filename
            $table->dateTime('export_timestamp')->index()
                ->comment('Parsed from: renew_submitted_YYYY-MM-DD_HH-MM-SS.txt');

            // Import tracking
            $table->string('source_file', 255)->nullable()->comment('Original filename');
            $table->timestamp('imported_at')->useCurrent();
            $table->timestamps();

            // Composite indexes
            $table->index(['patron_id', 'item_record_id', 'export_timestamp'], 'nr_patron_item_timestamp_idx');
            $table->index(['patron_barcode', 'export_timestamp'], 'nr_barcode_timestamp_idx');
            $table->index(['export_timestamp', 'notification_type_id'], 'nr_timestamp_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications_renewal');
    }
};
