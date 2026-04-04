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
        if (Schema::hasTable('order_requests')) {
            return;
        }
        
        Schema::create('order_requests', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 100);
            $table->dateTime('pick_up');
            $table->dateTime('drop_off');
            $table->string('pick_up_location', 50);
            $table->string('drop_off_location', 50);
            $table->boolean('confirmed')->default(false);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('contact_info_id');
            $table->decimal('sub_total', 10, 2);
            $table->unsignedInteger('car_id');
            $table->integer('days');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('car_id');
            $table->index('contact_info_id');
        });

        Schema::table('order_requests', function (Blueprint $table): void {
            $table->foreign('contact_info_id')->references('id')->on('contact_info')->onDelete('cascade');
            $table->foreign('car_id')->references('id')->on('vehicles')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_requests', function (Blueprint $table): void {
            $table->dropForeign(['contact_info_id']);
            $table->dropForeign(['car_id']);
        });
        Schema::dropIfExists('order_requests');
    }
};
