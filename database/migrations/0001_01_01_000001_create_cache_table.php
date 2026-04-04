<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Cache is configured to use file store in this admin project.
    }

    public function down(): void
    {
        // No-op.
    }
};
