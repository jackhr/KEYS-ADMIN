<?php

namespace Database\Seeders;

use App\Support\MockData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminApiTokenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = MockData::adminApiTokens();

        if ($rows === []) {
            return;
        }

        DB::table('admin_api_tokens')->insert($rows);
    }
}
