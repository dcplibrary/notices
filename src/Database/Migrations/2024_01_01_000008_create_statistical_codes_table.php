<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('statistical_codes', function (Blueprint $table) {
            $table->unsignedInteger('statistical_code_id');
            $table->unsignedInteger('organization_id');
            $table->string('description', 80);
            $table->timestamps();

            $table->primary(['statistical_code_id', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statistical_codes');
    }
};
