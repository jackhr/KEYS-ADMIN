<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_users')) {
            return;
        }

        Schema::create('admin_users', function (Blueprint $table): void {
            $table->id();
            $table->string('username', 120);
            $table->string('password_hash');
            $table->string('role', 50)->default('admin');
            $table->boolean('active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_users');
    }
};
