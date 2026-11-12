<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('notification_import_batches')) {
            return;
        }

        Schema::create('notification_import_batches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('source', 100);
            $table->date('date_for')->nullable();
            $table->dateTime('imported_at');
            // Identifier can be a file path, filename, or email message ID.
            $table->string('identifier', 191)->nullable();
            $table->unsignedInteger('records_count')->default(0);
            $table->text('meta')->nullable();
            $table->timestamps();

            // Short index name for MySQL
            $table->index(['source', 'date_for'], 'notif_import_source_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_import_batches');
    }
};
