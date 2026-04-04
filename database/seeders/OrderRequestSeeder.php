<?php

namespace Database\Seeders;

use App\Support\MockData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = array_map(static function (array $row): array {
            $row['status'] = (string) ($row['status'] ?? ((bool) ($row['confirmed'] ?? false) ? 'confirmed' : 'pending'));

            return $row;
        }, MockData::orderRequests());

        if ($rows === []) {
            return;
        }

        DB::table('order_requests')->insert($rows);
    }
}
