<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('shoutbomb_phone_notices', 'polaris_phone_notices');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('polaris_phone_notices', 'shoutbomb_phone_notices');
    }
};
