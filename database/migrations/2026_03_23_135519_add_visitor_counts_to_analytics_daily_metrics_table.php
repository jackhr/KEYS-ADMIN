<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('analytics_daily_metrics')) {
            return;
        }

        if (! Schema::hasColumn('analytics_daily_metrics', 'unique_visitors_count')) {
            Schema::table('analytics_daily_metrics', function (Blueprint $table): void {
                $table->unsignedInteger('unique_visitors_count')->default(0)->after('growth_rate_pct');
            });
        }

        if (! Schema::hasColumn('analytics_daily_metrics', 'mobile_visitors_count')) {
            Schema::table('analytics_daily_metrics', function (Blueprint $table): void {
                $table->unsignedInteger('mobile_visitors_count')->default(0)->after('unique_visitors_count');
            });
        }

        if (! Schema::hasColumn('analytics_daily_metrics', 'desktop_visitors_count')) {
            Schema::table('analytics_daily_metrics', function (Blueprint $table): void {
                $table->unsignedInteger('desktop_visitors_count')->default(0)->after('mobile_visitors_count');
            });
        }

        if (! Schema::hasColumn('analytics_daily_metrics', 'page_views_count')) {
            Schema::table('analytics_daily_metrics', function (Blueprint $table): void {
                $table->unsignedInteger('page_views_count')->default(0)->after('desktop_visitors_count');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('analytics_daily_metrics')) {
            return;
        }

        Schema::table('analytics_daily_metrics', function (Blueprint $table): void {
            $columnsToDrop = [];

            if (Schema::hasColumn('analytics_daily_metrics', 'unique_visitors_count')) {
                $columnsToDrop[] = 'unique_visitors_count';
            }

            if (Schema::hasColumn('analytics_daily_metrics', 'mobile_visitors_count')) {
                $columnsToDrop[] = 'mobile_visitors_count';
            }

            if (Schema::hasColumn('analytics_daily_metrics', 'desktop_visitors_count')) {
                $columnsToDrop[] = 'desktop_visitors_count';
            }

            if (Schema::hasColumn('analytics_daily_metrics', 'page_views_count')) {
                $columnsToDrop[] = 'page_views_count';
            }

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
