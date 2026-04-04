<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('add_ons')) {
            return;
        }
        
        Schema::create('add_ons', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name', 100);
            $table->decimal('cost', 10, 2)->nullable();
            $table->mediumText('description');
            $table->string('abbr', 100);
            $table->boolean('fixed_price')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('add_ons');
    }
};
