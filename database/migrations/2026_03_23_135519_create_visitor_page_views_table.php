<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visitor_page_views')) {
            return;
        }

        Schema::create('visitor_page_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('visitor_session_id')
                ->nullable()
                ->constrained('visitor_sessions')
                ->nullOnDelete();
            $table->string('visitor_id', 36);
            $table->dateTime('visited_at');
            // Keep indexed path under legacy InnoDB key-size limits on shared hosts.
            $table->string('route_path', 191);
            $table->text('full_url')->nullable();
            $table->text('query_string')->nullable();
            $table->text('referrer')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 16)->default('other');
            $table->boolean('is_bot')->default(false);
            $table->string('os_name', 64)->nullable();
            $table->string('browser_name', 64)->nullable();
            $table->string('language', 16)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedSmallInteger('viewport_width')->nullable();
            $table->unsignedSmallInteger('viewport_height')->nullable();
            $table->unsignedSmallInteger('screen_width')->nullable();
            $table->unsignedSmallInteger('screen_height')->nullable();
            $table->string('event_type', 32)->default('page_view');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['route_path', 'visited_at']);
            $table->index(['visitor_id', 'visited_at']);
            $table->index(['device_type', 'visited_at']);
            $table->index('visited_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_page_views');
    }
};
