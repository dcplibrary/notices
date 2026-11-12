<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            return;
        }

        Schema::create('notifications', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('import_batch_id')->nullable();

            // Identification / deduplication
            $table->string('notification_key', 191)->nullable();
            $table->string('export_filename')->nullable()->comment('Export file name (holds_/renew_/overdue_/notification_log_)');

            // Patron & organization context
            $table->string('patron_barcode', 50)->nullable();
            $table->unsignedBigInteger('patron_id')->nullable();
            $table->unsignedInteger('organization_id')->nullable();

            // Delivery option and type (align with Polaris DeliveryOptionID)
            // Local codes: 1 Mail, 2 Email, 3 Voice, 8 SMS
            // Nullable to support renewal notifications where delivery option comes from PatronRegistration and may be NULL
            $table->unsignedBigInteger('delivery_option_id')->nullable();
            $table->unsignedBigInteger('notification_type_id')->nullable()->comment('For reconciliation matching');

            $table->foreignId('ingested_email_id')
                ->nullable()
                ->constrained('ingested_emails')
                ->onDelete('set null')
                ->comment('Source email that created/updated this notification')
            ;

            $table->string('notification_type', 50)->nullable(); // e.g. hold, overdue, renewal
            $table->timestamp('notice_date')->nullable()->comment('For reconciliation matching');
            $table->unsignedTinyInteger('schedule_step')->nullable(); // e.g. 1st overdue, 2nd overdue

            // Timestamps across lifecycle
            $table->dateTime('generated_at')->nullable();
            $table->dateTime('first_exported_at')->nullable();
            $table->dateTime('last_exported_at')->nullable();
            $table->dateTime('expected_delivery_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('failed_at')->nullable();

            // Status & diagnostics
            $table->string('status', 50)->default('pending'); // pending, delivered, failed, skipped, etc.
            $table->boolean('is_combined')->default(false);
            $table->text('failure_reason')->nullable();
            $table->longText('raw_payload')->nullable();

            // Display columns: original case for UI rendering
            $table->string('display_patron_first_name', 255)->nullable();
            $table->string('display_patron_last_name', 255)->nullable();
            $table->string('display_title', 512)->nullable();
            $table->string('display_item_barcode', 50)->nullable();

            // Search columns: normalized (lowercase, punctuation stripped) for matching
            $table->string('search_patron_first_name', 255)->nullable();
            $table->string('search_patron_last_name', 255)->nullable();
            $table->string('search_full_name', 255)->nullable()->comment('Combined first+last for full name search');
            $table->string('search_title', 512)->nullable();
            $table->string('search_item_barcode', 50)->nullable();

            // Indexed patron contact info for fast lookups
            $table->string('indexed_patron_phone', 20)->nullable();
            $table->string('indexed_patron_email', 255)->nullable();

            $table->timestamps();

            // Indexes for common troubleshooting queries
            $table->index('import_batch_id');
            $table->index('notification_key');
            $table->index('ingested_email_id');
            // Short composite index names for MySQL
            $table->index(['patron_id', 'delivery_option_id'], 'notif_patron_deliv_idx');
            $table->index(['patron_barcode', 'delivery_option_id'], 'notif_barcode_deliv_idx');
            $table->index('status');
            $table->index('first_exported_at');
            $table->index('generated_at');

            // Supports issues/dashboard queries that filter by delivery option + status
            // and then constrain by failed_at time window (e.g. SMS opt-out).
            $table->index(['delivery_option_id', 'status', 'failed_at'], 'notif_do_status_failed_at_idx');

            // Search column indexes for fast patron/item lookups
            $table->index(['search_patron_last_name', 'search_patron_first_name'], 'notif_search_last_first_idx');
            $table->index('search_full_name', 'notif_search_full_name_idx');
            $table->index('search_title', 'notif_search_title_idx');
            $table->index('search_item_barcode', 'notif_search_item_barcode_idx');

            // Indexed patron contact info for fast exact-match lookups (replaces slow LIKE on raw_payload)
            $table->index('indexed_patron_phone', 'idx_notif_phone');
            $table->index('indexed_patron_email', 'idx_notif_email');

            // Foreign key constraints
            $table->foreign('import_batch_id')
                ->references('id')
                ->on('notification_import_batches')
                ->onDelete('set null')
            ;

            $table->foreign('delivery_option_id')
                ->references('id')
                ->on('delivery_options')
                ->onDelete('restrict')
            ;
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
