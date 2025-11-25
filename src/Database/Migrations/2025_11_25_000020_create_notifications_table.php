<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // Core identity
            $table->unsignedSmallInteger('notification_type_id')->index();
            $table->unsignedTinyInteger('notification_level')->nullable()->comment('1=1st overdue, 2=2nd, 3=3rd');
            $table->unsignedBigInteger('notification_log_id')->nullable()->index()->comment('Polaris NotificationLogID');

            // Patron identity
            $table->string('patron_barcode', 20)->index();
            $table->unsignedBigInteger('patron_id')->nullable()->index();

            // Item identity
            $table->string('item_barcode', 50)->nullable()->index();
            $table->unsignedBigInteger('item_record_id')->nullable()->index();
            $table->unsignedBigInteger('bib_record_id')->nullable()->index();
            $table->unsignedBigInteger('sys_hold_request_id')->nullable()->index();

            // Key dates
            $table->date('notice_date')->nullable()->index();
            $table->date('held_until')->nullable()->comment('HoldTillDate for holds');
            $table->date('due_date')->nullable()->comment('DueDate for overdue/renew');

            // Delivery + context
            $table->unsignedSmallInteger('delivery_option_id')->nullable()->index();
            $table->string('delivery_string', 255)->nullable()->comment('Raw contact target used (email or phone@carrier)');
            $table->unsignedSmallInteger('reporting_org_id')->nullable()->index();
            $table->string('site_code', 20)->nullable()->index();
            $table->string('site_name', 150)->nullable();
            $table->string('pickup_area_description', 255)->nullable();

            // Financial context
            $table->decimal('account_balance', 10, 2)->nullable();

            // Item + patron snapshot
            $table->string('browse_title')->nullable();
            $table->string('call_number', 100)->nullable();

            $table->string('patron_name_first', 100)->nullable();
            $table->string('patron_name_last', 100)->nullable();
            $table->string('patron_email', 255)->nullable();
            $table->string('patron_phone', 20)->nullable();

            $table->timestamps();

            // Useful composite indexes for lookups
            $table->index(['patron_barcode', 'notice_date']);
            $table->index(['item_barcode', 'notice_date']);
            $table->index(['notification_type_id', 'delivery_option_id', 'notice_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
