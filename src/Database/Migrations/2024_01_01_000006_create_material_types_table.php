<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('material_types', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('description', 80);
            $table->unsignedInteger('minimum_age')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_types');
    }
};
