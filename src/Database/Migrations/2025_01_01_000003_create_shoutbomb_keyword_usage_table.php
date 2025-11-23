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
        Schema::create('shoutbomb_keyword_usage', function (Blueprint $table) {
            $table->id();

            // Keyword interaction details
            $table->string('keyword', 20)->index()->comment('RHL, RA, OI, HL, MYBOOK, STOP');
            $table->string('patron_barcode', 20)->nullable()->index();
            $table->string('phone_number', 20)->nullable()->index();
            $table->dateTime('usage_date')->index();

            // Keyword details
            $table->string('keyword_description')->nullable()->comment('What the keyword does');
            $table->integer('usage_count')->default(1)->comment('Number of times used in the period');

            // Report source
            $table->string('report_file')->nullable();
            $table->enum('report_period', ['Weekly', 'Monthly'])->nullable();

            // Import tracking
            $table->timestamp('imported_at')->useCurrent();
            $table->timestamps();

            // Composite indexes
            $table->index(['usage_date', 'keyword']);
            $table->index(['patron_barcode', 'keyword']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shoutbomb_keyword_usage');
    }
};
