<?php

namespace Database\Seeders;

use App\Support\MockData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VehicleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = array_map(static function (array $row): array {
            unset($row['year'], $row['taxi']);

            return $row;
        }, MockData::vehicles());

        if ($rows === []) {
            return;
        }

        DB::table('vehicles')->insert($rows);
    }
}
