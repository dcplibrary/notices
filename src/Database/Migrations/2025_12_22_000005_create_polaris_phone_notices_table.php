<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('polaris_phone_notices')) {
            return;
        }

        Schema::create('polaris_phone_notices', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Core identifiers and correlation keys
            $table->string('media_type', 1)->nullable();           // V or T
            $table->string('language_code', 10)->nullable();       // eng
            $table->unsignedTinyInteger('notice_type')->nullable();
            $table->unsignedTinyInteger('notification_level')->nullable();

            $table->string('patron_barcode', 50)->nullable();
            $table->string('patron_title', 50)->nullable();
            $table->string('patron_first_name', 100)->nullable();
            $table->string('patron_last_name', 100)->nullable();
            $table->string('patron_phone', 20)->nullable();
            $table->string('patron_email', 255)->nullable();

            $table->string('library_code', 50)->nullable();
            $table->string('site_name', 255)->nullable();

            $table->string('item_barcode', 50)->nullable();
            $table->date('hold_till_date')->nullable();
            $table->string('item_title', 512)->nullable();

            $table->unsignedBigInteger('delivery_option_id_from_export')->nullable();
            $table->unsignedBigInteger('language_id')->nullable();
            $table->unsignedBigInteger('notification_type_id')->nullable();
            $table->unsignedBigInteger('delivery_option_id_duplicate')->nullable();

            $table->unsignedBigInteger('patron_id')->nullable();
            $table->unsignedBigInteger('item_record_id')->nullable();
            $table->unsignedBigInteger('sys_hold_request_id')->nullable();

            // Logical delivery channel derived from media_type (3=Voice, 8=SMS)
            $table->unsignedBigInteger('delivery_option_id')->nullable();

            // Metadata
            $table->dateTime('export_datetime')->nullable(); // from filename when available

            $table->timestamps();

            $table->index(['patron_id', 'item_record_id'], 'phone_notices_patron_item_idx');
            $table->index(['patron_barcode', 'item_barcode'], 'phone_notices_barcodes_idx');
            $table->index('sys_hold_request_id', 'phone_notices_sys_hold_idx');
            $table->index('export_datetime', 'phone_notices_export_dt_idx');

            // Foreign key constraints
            $table->foreign('delivery_option_id_from_export')
                ->references('id')
                ->on('delivery_options')
                ->onDelete('restrict')
            ;

            $table->foreign('delivery_option_id_duplicate')
                ->references('id')
                ->on('delivery_options')
                ->onDelete('restrict')
            ;

            $table->foreign('delivery_option_id')
                ->references('id')
                ->on('delivery_options')
                ->onDelete('restrict')
            ;

            $table->foreign('language_id')
                ->references('id')
                ->on('languages')
                ->onDelete('restrict')
            ;

            $table->foreign('notification_type_id')
                ->references('id')
                ->on('notification_types')
                ->onDelete('restrict')
            ;
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polaris_phone_notices');
    }
};
