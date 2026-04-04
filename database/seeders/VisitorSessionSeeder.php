<?php

namespace Database\Seeders;

use App\Support\MockData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VisitorSessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = MockData::visitorSessions();

        if ($rows === []) {
            return;
        }

        DB::table('visitor_sessions')->insert($rows);
    }
}
