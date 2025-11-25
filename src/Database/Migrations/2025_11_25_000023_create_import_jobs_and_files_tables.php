<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 50)->index();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->string('status', 20)->default('pending'); // pending/success/failed/partial
            $table->json('options_json')->nullable();
            $table->timestamps();
        });

        Schema::create('import_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_job_id')->nullable()->index();
            $table->string('filename', 255);
            $table->date('logical_date')->nullable()->index();
            $table->unsignedInteger('records_imported')->default(0);
            $table->unsignedInteger('records_skipped')->default(0);
            $table->string('checksum', 128)->nullable();
            $table->timestamps();

            $table->foreign('import_job_id')
                ->references('id')->on('import_jobs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_files');
        Schema::dropIfExists('import_jobs');
    }
};
