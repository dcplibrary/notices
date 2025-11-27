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
        if (!Schema::hasTable('polaris_phone_notices')) {
            return;
        }

        Schema::table('polaris_phone_notices', function (Blueprint $table) {
            // Add core enrichment fields if they do not exist yet.
            if (!Schema::hasColumn('polaris_phone_notices', 'notification_type_id')) {
                $table->integer('notification_type_id')->nullable()->index()->after('language_code');
            }

            if (!Schema::hasColumn('polaris_phone_notices', 'delivery_option_id')) {
                $table->integer('delivery_option_id')->nullable()->after('notification_type_id');
            }

            if (!Schema::hasColumn('polaris_phone_notices', 'sys_hold_request_id')) {
                $table->integer('sys_hold_request_id')->nullable()->index()->after('item_record_id');
            }

            if (!Schema::hasColumn('polaris_phone_notices', 'account_balance')) {
                $table->decimal('account_balance', 10, 2)->nullable()->after('sys_hold_request_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('polaris_phone_notices')) {
            return;
        }

        Schema::table('polaris_phone_notices', function (Blueprint $table) {
            if (Schema::hasColumn('polaris_phone_notices', 'account_balance')) {
                $table->dropColumn('account_balance');
            }
            if (Schema::hasColumn('polaris_phone_notices', 'sys_hold_request_id')) {
                $table->dropColumn('sys_hold_request_id');
            }
            if (Schema::hasColumn('polaris_phone_notices', 'delivery_option_id')) {
                $table->dropColumn('delivery_option_id');
            }
            if (Schema::hasColumn('polaris_phone_notices', 'notification_type_id')) {
                $table->dropColumn('notification_type_id');
            }
        });
    }
};
