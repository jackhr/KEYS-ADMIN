<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_users') || Schema::hasColumn('admin_users', 'email')) {
            return;
        }

        Schema::table('admin_users', function (Blueprint $table): void {
            $table->string('email', 190)->nullable()->after('username');
            $table->unique('email', 'admin_users_email_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_users') || ! Schema::hasColumn('admin_users', 'email')) {
            return;
        }

        Schema::table('admin_users', function (Blueprint $table): void {
            $table->dropUnique('admin_users_email_unique');
            $table->dropColumn('email');
        });
    }
};

