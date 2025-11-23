<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This table stores PhoneNotices.csv (Polaris native export) for
     * VERIFICATION/CORROBORATION of the official SQL submissions.
     *
     * PhoneNotices.csv is the validation baseline - what Polaris queued for notification.
     *
     * CSV Field Mapping (1-based index):
     *  1: delivery_method (V=Voice, T=Text)
     *  2: language
     *  3: notice_type (1-4, i-tiva types)
     *  4: notification_level (1=default, 2=2nd, 3=3rd)
     *  5: patron_barcode
     *  6: patron_title (Mr., Mrs., Dr., etc.)
     *  7: name_first
     *  8: name_last
     *  9: phone_number
     * 10: email_address
     * 11: site_code
     * 12: site_name
     * 13: item_barcode
     * 14: due_date (MM/DD/YYYY format)
     * 15: browse_title
     * 16: reporting_org_id
     * 17: language_id (1033=English)
     * 18: notification_type_id (1=Overdue, 2=Hold, 7=AlmostOverdue, etc.)
     * 19: delivery_option_id (3=Phone1/Voice, 8=TXT Messaging)
     * 20: patron_id
     * 21: item_record_id
     * 22: sys_hold_request_id (for holds only)
     * 23: pickup_area_description (conditional, for holds)
     * 24: txn_id (manual bills only)
     * 25: account_balance (fines/bills only)
     */
    public function up(): void
    {
        Schema::create('polaris_phone_notices', function (Blueprint $table) {
            $table->id();

            // CSV Field 1: Delivery Method
            $table->char('delivery_method', 1)->index()->comment('V=Voice, T=Text from CSV field 1');

            // CSV Field 2: Language (ISO 639-2/T code)
            $table->string('language', 10)->nullable()->comment('CSV field 2 - ISO language code');

            // CSV Field 3: Notice Type (i-tiva)
            $table->tinyInteger('notice_type')->nullable()->comment('CSV field 3 - 1-4 i-tiva types');

            // CSV Field 4: Notification Level
            $table->tinyInteger('notification_level')->nullable()->comment('CSV field 4 - 1=default, 2=2nd, 3=3rd');

            // CSV Field 5: Patron Barcode
            $table->string('patron_barcode', 20)->index()->comment('CSV field 5');

            // CSV Field 6: Patron Title
            $table->string('patron_title', 20)->nullable()->comment('CSV field 6 - Mr., Mrs., Dr., etc.');

            // CSV Fields 7-8: Name
            $table->string('name_first', 50)->nullable()->comment('CSV field 7');
            $table->string('name_last', 50)->nullable()->comment('CSV field 8');

            // CSV Field 9: Phone Number
            $table->string('phone_number', 20)->nullable()->index()->comment('CSV field 9 - may have formatting');

            // CSV Field 10: Email Address
            $table->string('email_address', 255)->nullable()->comment('CSV field 10');

            // CSV Fields 11-12: Site/Branch
            $table->string('site_code', 20)->nullable()->comment('CSV field 11 - e.g., DCPL');
            $table->string('site_name', 100)->nullable()->comment('CSV field 12 - Branch name');

            // CSV Field 13: Item Barcode
            $table->string('item_barcode', 50)->nullable()->index()->comment('CSV field 13');

            // CSV Field 14: Due Date
            $table->date('due_date')->nullable()->comment('CSV field 14 - converted from MM/DD/YYYY');

            // CSV Field 15: Browse Title
            $table->text('browse_title')->nullable()->comment('CSV field 15');

            // CSV Field 16: Reporting Org ID
            $table->integer('reporting_org_id')->nullable()->index()->comment('CSV field 16 - Branch ID');

            // CSV Field 17: Language ID
            $table->integer('language_id')->nullable()->comment('CSV field 17 - 1033=English');

            // CSV Field 18: Notification Type ID (CRITICAL for tracking)
            $table->integer('notification_type_id')->nullable()->index()
                ->comment('CSV field 18 - 1=Overdue, 2=Hold, 7=AlmostOverdue, 8=Fine, 11=Bill, 12=2nd Overdue, 13=3rd Overdue');

            // CSV Field 19: Delivery Option ID (CRITICAL for delivery method)
            $table->integer('delivery_option_id')->nullable()->index()
                ->comment('CSV field 19 - 3=Phone1/Voice, 8=TXT Messaging');

            // CSV Field 20: Patron ID
            $table->integer('patron_id')->nullable()->index()->comment('CSV field 20 - Internal Polaris ID');

            // CSV Field 21: Item Record ID
            $table->integer('item_record_id')->nullable()->index()->comment('CSV field 21');

            // CSV Field 22: Sys Hold Request ID (holds only)
            $table->integer('sys_hold_request_id')->nullable()->index()->comment('CSV field 22 - Hold-specific');

            // CSV Field 23: Pickup Area Description (holds only)
            $table->string('pickup_area_description', 255)->nullable()->comment('CSV field 23 - Conditional for holds');

            // CSV Field 24: Transaction ID (manual bills only)
            $table->integer('txn_id')->nullable()->comment('CSV field 24 - Manual bills only');

            // CSV Field 25: Account Balance (fines/bills only)
            $table->decimal('account_balance', 10, 2)->nullable()->comment('CSV field 25 - Fines/bills only');

            // Import tracking
            $table->date('import_date')->nullable()->index()->comment('Date portion of import timestamp');
            $table->string('source_file', 255)->nullable()->comment('Original CSV filename');
            $table->timestamp('imported_at')->useCurrent();
            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['patron_barcode', 'import_date']);
            $table->index(['phone_number', 'import_date']);
            $table->index(['import_date', 'delivery_method']);
            $table->index(['import_date', 'notification_type_id']);
            $table->index(['patron_id', 'item_record_id', 'import_date'], 'ppn_patron_item_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('polaris_phone_notices');
    }
};
