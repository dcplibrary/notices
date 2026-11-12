<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                // Supports failed-by-method queries filtered by status + export range.
                $table->index(
                    ['status', 'first_exported_at', 'delivery_option_id', 'id'],
                    'notif_status_export_delivery_id_idx'
                );

                // Supports failed-by-type queries filtered by status + export range.
                $table->index(
                    ['status', 'first_exported_at', 'notification_type_id', 'id'],
                    'notif_status_export_type_id_idx'
                );
            });
        }

        if (Schema::hasTable('delivery_confirmations')) {
            Schema::table('delivery_confirmations', function (Blueprint $table) {
                // Optimizes notification_log dashboard diagnostics by source + channel + day range.
                $table->index(
                    ['confirmation_source', 'delivery_option_id', 'notice_date'],
                    'delivery_conf_src_do_notice_idx'
                );
            });
        }

        if (Schema::hasTable('notification_activity_logs')) {
            Schema::table('notification_activity_logs', function (Blueprint $table) {
                $table->index(['occurred_at', 'id'], 'notif_activity_occurred_id_idx');
            });
        }

        if (Schema::hasTable('user_activity_logs')) {
            Schema::table('user_activity_logs', function (Blueprint $table) {
                $table->index(['occurred_at', 'id'], 'user_activity_occurred_id_idx');
            });
        }

        if (Schema::hasTable('activity_log')) {
            Schema::table('activity_log', function (Blueprint $table) {
                $table->index(['created_at', 'id'], 'activity_created_id_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropIndex('notif_status_export_delivery_id_idx');
                $table->dropIndex('notif_status_export_type_id_idx');
            });
        }

        if (Schema::hasTable('delivery_confirmations')) {
            Schema::table('delivery_confirmations', function (Blueprint $table) {
                $table->dropIndex('delivery_conf_src_do_notice_idx');
            });
        }

        if (Schema::hasTable('notification_activity_logs')) {
            Schema::table('notification_activity_logs', function (Blueprint $table) {
                $table->dropIndex('notif_activity_occurred_id_idx');
            });
        }

        if (Schema::hasTable('user_activity_logs')) {
            Schema::table('user_activity_logs', function (Blueprint $table) {
                $table->dropIndex('user_activity_occurred_id_idx');
            });
        }

        if (Schema::hasTable('activity_log')) {
            Schema::table('activity_log', function (Blueprint $table) {
                $table->dropIndex('activity_created_id_idx');
            });
        }
    }
};
