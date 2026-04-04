<?php

namespace Database\Seeders;

use App\Support\MockData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VehicleDiscountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = MockData::vehicleDiscounts();

        if ($rows === []) {
            return;
        }

        DB::table('vehicle_discounts')->insert($rows);
    }
}
