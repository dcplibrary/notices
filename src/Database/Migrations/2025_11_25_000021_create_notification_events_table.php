<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('notification_id')->index();

            // What happened
            $table->string('event_type', 64)->index();
            $table->dateTime('event_at')->nullable()->index();

            // Channel / status context for this event
            $table->unsignedSmallInteger('delivery_option_id')->nullable()->index();
            $table->string('status_code', 50)->nullable();
            $table->string('status_text')->nullable();

            // Source linkage (where this came from)
            $table->string('source_table', 100)->nullable()->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->string('source_file', 255)->nullable();
            $table->unsignedBigInteger('import_job_id')->nullable()->index();

            $table->timestamps();

            $table->foreign('notification_id')
                ->references('id')->on('notifications')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_events');
    }
};
