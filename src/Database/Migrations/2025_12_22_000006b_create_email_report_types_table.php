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
        // If the table already exists (for example, on an existing install or
        // a pre-populated sqlite database), skip creation so the migration can
        // still be marked as ran without error.
        if (Schema::hasTable('email_report_types')) {
            return;
        }

        Schema::create('email_report_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('Internal code identifier');
            $table->string('label')->comment('Display name for report type');
            $table->string('category')->nullable()->comment('Grouping category (shoutbomb, polaris, other)');
            $table->text('description')->nullable();
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index('category');
            $table->index('display_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_report_types');
    }
};
