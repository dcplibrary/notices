<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shoutbomb_registrations', function (Blueprint $table) {
            $table->id();

            // Registration statistics snapshot
            $table->date('snapshot_date')->unique()->index();

            // Subscription counts
            $table->integer('total_text_subscribers')->default(0);
            $table->integer('total_voice_subscribers')->default(0);
            $table->integer('total_subscribers')->default(0);

            // Percentages
            $table->decimal('text_percentage', 5, 2)->nullable();
            $table->decimal('voice_percentage', 5, 2)->nullable();

            // Changes from previous period
            $table->integer('text_change')->default(0)->comment('Change from previous snapshot');
            $table->integer('voice_change')->default(0)->comment('Change from previous snapshot');

            // Additional statistics
            $table->integer('new_registrations')->default(0);
            $table->integer('unsubscribes')->default(0);
            $table->integer('invalid_numbers')->default(0);

            // Report source
            $table->string('report_file')->nullable();
            $table->enum('report_type', ['Daily', 'Weekly', 'Monthly']);

            // Import tracking
            $table->timestamp('imported_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shoutbomb_registrations');
    }
};
