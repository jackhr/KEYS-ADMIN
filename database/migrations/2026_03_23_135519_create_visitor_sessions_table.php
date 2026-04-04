<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visitor_sessions')) {
            return;
        }

        Schema::create('visitor_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('visitor_id', 36);
            $table->string('session_id', 36)->unique();
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
            $table->string('entry_path', 255)->nullable();
            $table->string('entry_referrer', 1024)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 16)->default('other');
            $table->boolean('is_bot')->default(false);
            $table->string('os_name', 64)->nullable();
            $table->string('browser_name', 64)->nullable();
            $table->string('language', 16)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->timestamps();

            $table->index(['visitor_id', 'last_seen_at']);
            $table->index(['device_type', 'last_seen_at']);
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_sessions');
    }
};
