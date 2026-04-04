<?php

namespace Database\Seeders;

use App\Support\MockData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AddOnSeeder extends Seeder
{
    public function run(): void
    {
        $rows = MockData::addOns();

        if ($rows === []) {
            return;
        }

        DB::table('add_ons')->insert($rows);
    }
}
