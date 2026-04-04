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
        if (Schema::hasTable('vehicles')) {
            return;
        }
        
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name', 30);
            $table->string('type', 20);
            $table->string('slug', 99);
            $table->boolean('showing')->default(true);
            $table->integer('landing_order')->nullable();
            $table->float('base_price_XCD');
            $table->float('base_price_USD');
            $table->unsignedTinyInteger('insurance')->default(0);
            $table->integer('times_requested')->default(0);
            $table->unsignedTinyInteger('people')->default(4);
            $table->integer('bags')->nullable();
            $table->unsignedTinyInteger('doors')->default(4);
            $table->boolean('4wd')->default(false);
            $table->boolean('ac')->default(true);
            $table->boolean('manual')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
