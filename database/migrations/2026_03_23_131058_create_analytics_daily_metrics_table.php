<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('analytics_daily_metrics')) {
            return;
        }

        Schema::create('analytics_daily_metrics', function (Blueprint $table): void {
            $table->id();
            $table->date('snapshot_date')->unique();
            $table->unsignedInteger('order_requests_count')->default(0);
            $table->unsignedInteger('new_customers_count')->default(0);
            $table->unsignedInteger('active_vehicles_count')->default(0);
            $table->decimal('revenue_usd', 12, 2)->default(0);
            $table->decimal('growth_rate_pct', 7, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->dateTime('captured_at')->nullable();
            $table->timestamps();

            $table->index('captured_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily_metrics');
    }
};
