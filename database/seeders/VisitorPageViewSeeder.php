<?php

namespace Database\Seeders;

use App\Support\MockData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VisitorPageViewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = MockData::visitorPageViews();

        if ($rows === []) {
            return;
        }

        $rows = array_map(static function (array $row): array {
            if (array_key_exists('metadata', $row) && is_array($row['metadata'])) {
                $row['metadata'] = json_encode($row['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            return $row;
        }, $rows);

        DB::table('visitor_page_views')->insert($rows);
    }
}
