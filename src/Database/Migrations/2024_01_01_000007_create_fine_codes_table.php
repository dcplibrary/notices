<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fine_codes', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('description', 80);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fine_codes');
    }
};
