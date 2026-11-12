<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('polaris_notification_history', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Correlation keys - match with exported notifications
            $table->unsignedBigInteger('patron_id')->nullable();
            $table->unsignedBigInteger('item_record_id')->nullable();
            $table->unsignedBigInteger('notification_type_id')->nullable();
            $table->unsignedBigInteger('delivery_option_id')->nullable();
            $table->unsignedBigInteger('reporting_org_id')->nullable();

            // Notification details
            $table->dateTime('notice_date')->nullable();
            $table->text('title')->nullable();
            $table->decimal('amount', 10, 2)->nullable();  // Fine/fee amount
            $table->unsignedBigInteger('notification_status_id')->nullable();
            $table->string('txn_id', 100)->nullable();

            // Tracking
            $table->dateTime('export_datetime')->nullable();  // When export ran

            $table->timestamps();

            // Indexes for correlation
            $table->index(['patron_id', 'item_record_id'], 'nh_patron_item_idx');
            $table->index('notice_date', 'nh_notice_date_idx');
            $table->index('notification_type_id', 'nh_notification_type_idx');
            $table->index('delivery_option_id', 'nh_delivery_option_idx');

            // Foreign key constraints
            $table->foreign('notification_type_id')
                ->references('id')
                ->on('notification_types')
                ->onDelete('restrict')
            ;

            $table->foreign('delivery_option_id')
                ->references('id')
                ->on('delivery_options')
                ->onDelete('restrict')
            ;

            $table->foreign('notification_status_id')
                ->references('id')
                ->on('notification_statuses')
                ->onDelete('restrict')
            ;
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polaris_notification_history');
    }
};
