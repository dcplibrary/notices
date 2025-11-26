<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shoutbomb_submission_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('submission_id')->index();
            $table->unsignedBigInteger('import_file_id')->index();
            $table->timestamps();

            $table->foreign('submission_id')
                ->references('id')->on('shoutbomb_submissions')
                ->onDelete('cascade');

            $table->foreign('import_file_id')
                ->references('id')->on('import_files')
                ->onDelete('cascade');

            $table->unique(['submission_id', 'import_file_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shoutbomb_submission_files');
    }
};
