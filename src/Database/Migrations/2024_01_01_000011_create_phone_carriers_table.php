<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL, we need to use raw SQL to ensure unsigned BIGINT
        // For SQLite, we can use Schema builder
        if ('mysql' === DB::connection()->getDriverName()) {
            DB::statement('
                CREATE TABLE phone_carriers (
                    id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                    name VARCHAR(100) NOT NULL UNIQUE,
                    sms_gateway VARCHAR(100) NULL COMMENT "Email gateway for SMS, e.g., @txt.att.net",
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL,
                    INDEX idx_active (active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');
        } else {
            Schema::create('phone_carriers', function (Blueprint $table) {
                $table->unsignedBigInteger('id')->primary();
                $table->string('name', 100)->unique();
                $table->string('sms_gateway', 100)->nullable()->comment('Email gateway for SMS, e.g., @txt.att.net');
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->index('active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phone_carriers');
    }
};
