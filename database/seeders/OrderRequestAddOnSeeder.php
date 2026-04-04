<?php

namespace Database\Seeders;

use App\Support\MockData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderRequestAddOnSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = MockData::orderRequestAddOns();

        if ($rows === []) {
            return;
        }

        DB::table('order_request_add_ons')->insert($rows);
    }
}
