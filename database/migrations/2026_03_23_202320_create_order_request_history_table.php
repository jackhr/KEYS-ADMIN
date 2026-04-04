<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_request_history')) {
            return;
        }

        Schema::create('order_request_history', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('order_request_id');
            $table->string('admin_user', 100);
            $table->string('action', 50);
            $table->text('change_summary')->nullable();
            $table->json('previous_data')->nullable();
            $table->json('new_data')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('order_request_id');
            $table->foreign('order_request_id')->references('id')->on('order_requests')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_request_history')) {
            return;
        }

        Schema::table('order_request_history', function (Blueprint $table): void {
            $table->dropForeign(['order_request_id']);
        });

        Schema::dropIfExists('order_request_history');
    }
};
