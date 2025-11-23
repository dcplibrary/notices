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
     */
    public function up(): void
    {
        Schema::create('shoutbomb_phone_notices', function (Blueprint $table) {
            $table->id();

            // Polaris PhoneNotices.csv fields
            $table->enum('delivery_type', ['voice', 'text'])->index()->comment('V=Voice, T=Text from CSV field 1');
            $table->string('language', 10)->nullable()->comment('CSV field 2');
            $table->string('patron_barcode', 20)->index()->comment('CSV field 5');
            $table->string('first_name', 50)->nullable()->comment('CSV field 7');
            $table->string('last_name', 50)->nullable()->comment('CSV field 8');
            $table->string('phone_number', 20)->nullable()->index()->comment('CSV field 9');
            $table->string('email')->nullable()->comment('CSV field 10');
            $table->string('library_code', 20)->nullable()->comment('CSV field 11');
            $table->string('library_name', 100)->nullable()->comment('CSV field 12');
            $table->string('item_barcode', 50)->nullable()->comment('CSV field 13');
            $table->date('notice_date')->nullable()->comment('CSV field 14');
            $table->string('title')->nullable()->comment('CSV field 15');
            $table->string('organization_code', 20)->nullable()->comment('CSV field 16');
            $table->string('language_code', 10)->nullable()->comment('CSV field 17');
            $table->integer('patron_id')->nullable()->comment('CSV field 20');
            $table->integer('item_record_id')->nullable()->comment('CSV field 21');
            $table->integer('bib_record_id')->nullable()->comment('CSV field 22');

            // Tracking fields
            $table->string('source_file')->nullable()->comment('Original CSV filename');
            $table->timestamp('imported_at')->useCurrent();
            $table->timestamps();

            // Indexes for verification queries
            $table->index(['patron_barcode', 'notice_date']);
            $table->index(['phone_number', 'notice_date']);
            $table->index(['notice_date', 'delivery_type']);
            $table->index('imported_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shoutbomb_phone_notices');
    }
};
