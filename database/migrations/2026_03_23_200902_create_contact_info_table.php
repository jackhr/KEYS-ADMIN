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
        if (Schema::hasTable('contact_info')) {
            return;
        }
        
        Schema::create('contact_info', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('driver_license', 50)->nullable();
            $table->string('hotel', 50)->nullable();
            $table->string('country_or_region', 50)->nullable();
            $table->string('street', 100)->nullable();
            $table->string('town_or_city', 50)->nullable();
            $table->string('state_or_county', 50)->nullable();
            $table->string('phone', 20);
            $table->string('email', 100);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_info');
    }
};
