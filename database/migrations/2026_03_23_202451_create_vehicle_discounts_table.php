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
        if (Schema::hasTable('vehicle_discounts')) {
            return;
        }
        
        Schema::create('vehicle_discounts', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('vehicle_id');
            $table->float('price_XCD', 8, 2);
            $table->float('price_USD', 8, 2);
            $table->unsignedTinyInteger('days');

            $table->index('vehicle_id');
        });

        Schema::table('vehicle_discounts', function (Blueprint $table): void {
            $table->foreign('vehicle_id')->references('id')->on('vehicles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_discounts', function (Blueprint $table): void {
            $table->dropForeign(['vehicle_id']);
        });

        Schema::dropIfExists('vehicle_discounts');
    }
};
