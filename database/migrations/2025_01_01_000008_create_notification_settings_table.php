<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This table stores runtime-configurable settings that can be changed
     * without code deployment. Supports scoping for multi-tenant scenarios.
     */
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();

            // Scoping (for multi-tenant or per-branch settings)
            $table->string('scope')->nullable()->index()->comment('global, branch, channel, etc.');
            $table->string('scope_id')->nullable()->index()->comment('Branch ID, Channel ID, etc.');

            // Setting
            $table->string('group')->index()->comment('Setting group for organization');
            $table->string('key')->index()->comment('Setting key (e.g., shoutbomb.ftp.host)');
            $table->text('value')->comment('JSON-encoded or plain value');
            $table->string('type')->default('string')->comment('string, int, bool, json, encrypted');

            // Metadata
            $table->text('description')->nullable()->comment('Human-readable description');
            $table->boolean('is_public')->default(false)->comment('Can be exposed via API');
            $table->boolean('is_editable')->default(true)->comment('Can be changed via UI');
            $table->boolean('is_sensitive')->default(false)->comment('Passwords, API keys, etc.');

            // Validation
            $table->json('validation_rules')->nullable()->comment('Laravel validation rules');

            // Audit
            $table->string('updated_by')->nullable()->comment('User who last updated');
            $table->timestamps();

            // Unique constraint
            $table->unique(['scope', 'scope_id', 'key'], 'unique_setting');

            // Composite indexes
            $table->index(['scope', 'scope_id']);
            $table->index(['group', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
