<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * This table stores records from PhoneNotices.csv (Polaris export) used to
     * VERIFY that notices were handed off from Polaris to Shoutbomb.
     */
    public function up(): void
    {
        Schema::create('polaris_phone_notices', function (Blueprint $table) {
            $table->id();

            // Core delivery + patron fields
            $table->enum('delivery_type', ['voice', 'text'])->nullable()->index();
            $table->string('language', 10)->nullable();
            $table->string('patron_barcode', 20)->index();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('phone_number', 20)->nullable()->index();
            $table->string('email', 255)->nullable();

            // Library + item context
            $table->string('library_code', 20)->nullable()->index();
            $table->string('library_name', 150)->nullable();
            $table->string('item_barcode', 50)->nullable()->index();
            $table->date('notice_date')->nullable()->index();
            $table->string('title')->nullable();

            // Additional metadata from CSV
            $table->string('organization_code', 20)->nullable();
            $table->string('language_code', 20)->nullable();
            $table->unsignedBigInteger('patron_id')->nullable()->index();
            $table->unsignedBigInteger('item_record_id')->nullable()->index();
            $table->unsignedBigInteger('bib_record_id')->nullable()->index();

            // Import tracking
            $table->string('source_file', 255)->nullable();
            $table->timestamp('imported_at')->useCurrent();
            $table->timestamps();

            // Composite indexes for common verification queries
            $table->index(['patron_barcode', 'notice_date']);
            $table->index(['phone_number', 'notice_date']);
            $table->index(['library_code', 'notice_date']);
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
