<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_api_tokens')) {
            return;
        }

        Schema::create('admin_api_tokens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('admin_user_id');
            $table->string('token_hash', 64);
            $table->dateTime('expires_at');
            $table->dateTime('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_api_tokens');
    }
};
