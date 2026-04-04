<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // This admin project does not use Laravel's default users/session tables.
    }

    public function down(): void
    {
        // No-op.
    }
};
