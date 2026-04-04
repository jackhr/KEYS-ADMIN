<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! (bool) config('admin.use_mock_data', false)) {
            $this->command?->warn('Skipping mock seeders because ADMIN_USE_MOCK_DATA is disabled.');

            return;
        }

        $this->call([
            AdminUserSeeder::class,
            AdminApiTokenSeeder::class,
            AddOnSeeder::class,
            VehicleSeeder::class,
            ContactInfoSeeder::class,
            OrderRequestSeeder::class,
            OrderRequestAddOnSeeder::class,
            VehicleDiscountSeeder::class,
            VisitorSessionSeeder::class,
            VisitorPageViewSeeder::class,
            AnalyticsDailyMetricSeeder::class,
        ]);
    }
}
