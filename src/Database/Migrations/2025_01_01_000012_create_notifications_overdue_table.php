<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This table stores overdue/fine/bill notifications sent to ShoutBomb from overdue*.txt exports.
     *
     * Source File: overdue_submitted_YYYY-MM-DD_HH-MM-SS.txt
     * Export Schedule: Daily at 8:04am
     *
     * File Format (pipe-delimited, 13 fields):
     *   Field 1:  PatronID
     *   Field 2:  ItemBarcode
     *   Field 3:  Title
     *   Field 4:  DueDate (YYYY-MM-DD)
     *   Field 5:  ItemRecordID
     *   Field 6-9: Dummy fields (always empty ||||)
     *   Field 10: Renewals (times renewed)
     *   Field 11: BibliographicRecordID
     *   Field 12: RenewalLimit (max renewals)
     *   Field 13: PatronBarcode
     *
     * CRITICAL: overdue*.txt contains MULTIPLE notification types:
     *   - Type 1:  1st Overdue (Day 1 after due date)
     *   - Type 7:  Pre-due reminders (3 days and 1 day before due)
     *   - Type 8:  Fine notices
     *   - Type 11: Bill notices (lost item)
     *   - Type 12: 2nd Overdue (Day 7 after due)
     *   - Type 13: 3rd Overdue (Day 14 after due)
     *
     * Exact type MUST be enriched from PhoneNotices.csv
     */
    public function up(): void
    {
        Schema::create('notifications_overdue', function (Blueprint $table) {
            $table->id();

            // Field 1: Patron ID
            $table->integer('patron_id')->index()->comment('Field 1 - Internal Polaris patron ID');

            // Field 2: Item Barcode
            $table->string('item_barcode', 50)->index()->comment('Field 2');

            // Field 3: Title
            $table->text('title')->nullable()->comment('Field 3');

            // Field 4: Due Date
            $table->date('due_date')->nullable()->comment('Field 4 - YYYY-MM-DD');

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

            // Notification type - MUST be enriched from PhoneNotices
            // Could be: 1, 7, 8, 11, 12, or 13
            $table->tinyInteger('notification_type_id')->nullable()->index()
                ->comment('1=1st Overdue, 7=Pre-due, 8=Fine, 11=Bill, 12=2nd, 13=3rd - enriched from PhoneNotices');

            // Delivery option - enriched from patron lists
            $table->tinyInteger('delivery_option_id')->nullable()->index()
                ->comment('3=Voice, 8=Text - enriched from patron list membership');

            // Export timestamp parsed from filename
            $table->dateTime('export_timestamp')->index()
                ->comment('Parsed from: overdue_submitted_YYYY-MM-DD_HH-MM-SS.txt');

            // Import tracking
            $table->string('source_file', 255)->nullable()->comment('Original filename');
            $table->timestamp('imported_at')->useCurrent();
            $table->timestamps();

            // Composite indexes for enrichment queries
            $table->index(['patron_id', 'item_record_id', 'export_timestamp'], 'no_patron_item_timestamp_idx');
            $table->index(['patron_barcode', 'export_timestamp']);
            $table->index(['export_timestamp', 'notification_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications_overdue');
    }
};
