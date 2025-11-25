<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patron_profiles', function (Blueprint $table) {
            $table->id();

            $table->string('patron_barcode', 20)->unique();
            $table->unsignedBigInteger('patron_id')->nullable()->index();

            $table->string('name_first', 100)->nullable();
            $table->string('name_last', 100)->nullable();
            $table->string('primary_phone', 20)->nullable();
            $table->string('email_address', 255)->nullable();

            // Delivery preference tracking
            $table->unsignedSmallInteger('delivery_option_id')->nullable()->index();
            $table->unsignedSmallInteger('former_delivery_option_id')->nullable();
            $table->dateTime('delivery_option_changed_at')->nullable();

            // Context
            $table->string('language_code', 20)->nullable();
            $table->unsignedSmallInteger('language_id')->nullable();
            $table->unsignedSmallInteger('reporting_org_id')->nullable()->index();

            // Last seen markers
            $table->dateTime('last_seen_in_phonenotices_at')->nullable();
            $table->dateTime('last_seen_in_notification_logs_at')->nullable();
            $table->dateTime('last_seen_in_lists_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patron_profiles');
    }
};
