<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This table stores patron notification preferences from the daily patron list exports.
     *
     * CRITICAL CONCEPT: The file name indicates the delivery method:
     *   - voice_patrons*.txt → delivery_option_id = 3 (Phone1 - Voice calls)
     *   - text_patrons*.txt  → delivery_option_id = 8 (TXT Messaging - SMS)
     *
     * Both files use the same phone field (PhoneVoice1) but different delivery methods.
     *
     * File Format (pipe-delimited):
     *   Field 1: PhoneVoice1 (10 digits, no dashes)
     *   Field 2: Patron Barcode
     *
     * Import Schedule:
     *   - voice_patrons: Daily at 4:00am
     *   - text_patrons:  Daily at 5:00am
     */
    public function up(): void
    {
        Schema::create('patrons_notification_preferences', function (Blueprint $table) {
            $table->id();

            // Patron identification
            $table->string('patron_barcode', 20)->index()->comment('Patron barcode from field 2');

            // Phone number (from PhoneVoice1 field)
            $table->char('phone_voice1', 10)->index()->comment('10-digit phone number from field 1');

            // Delivery method - derived from source file name
            $table->enum('delivery_method', ['voice', 'text'])->index()
                ->comment('Derived from filename: voice_patrons=voice, text_patrons=text');

            // Polaris delivery_option_id equivalent
            $table->tinyInteger('delivery_option_id')->index()
                ->comment('3=Phone1 (Voice), 8=TXT Messaging');

            // Import tracking
            $table->date('import_date')->index()->comment('Date of the patron list export');
            $table->string('source_file', 255)->nullable()->comment('Original filename');
            $table->timestamp('imported_at')->useCurrent();
            $table->timestamps();

            // Unique constraint: A patron can only have one delivery preference per day
            $table->unique(['patron_barcode', 'import_date'], 'pnp_barcode_date_unique');

            // Composite indexes for enrichment lookups
            $table->index(['patron_barcode', 'import_date', 'delivery_method'], 'pnp_barcode_date_method_idx');
            $table->index(['phone_voice1', 'import_date'], 'pnp_phone_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patrons_notification_preferences');
    }
};
