<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vehicles') || Schema::hasColumn('vehicles', 'image_filename')) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->string('image_filename')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('vehicles') || ! Schema::hasColumn('vehicles', 'image_filename')) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropColumn('image_filename');
        });
    }
};
