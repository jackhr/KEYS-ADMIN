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
        if (Schema::hasTable('order_request_add_ons')) {
            return;
        }
        
        Schema::create('order_request_add_ons', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('order_request_id');
            $table->unsignedInteger('add_on_id');
            $table->integer('quantity')->default(1);

            $table->index('order_request_id');
            $table->index('add_on_id');
        });

        Schema::table('order_request_add_ons', function (Blueprint $table): void {
            $table->foreign('order_request_id')->references('id')->on('order_requests')->onDelete('cascade');
            $table->foreign('add_on_id')->references('id')->on('add_ons')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_request_add_ons', function (Blueprint $table): void {
            $table->dropForeign(['order_request_id']);
            $table->dropForeign(['add_on_id']);
        });

        Schema::dropIfExists('order_request_add_ons');
    }
};
