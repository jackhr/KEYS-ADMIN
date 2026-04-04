<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Queue is configured to use sync driver in this admin project.
    }

    public function down(): void
    {
        // No-op.
    }
};
