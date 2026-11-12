<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_statuses', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('description', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_statuses');
    }
};
