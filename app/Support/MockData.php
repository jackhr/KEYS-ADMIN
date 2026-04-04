<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class MockData
{
    private const TABLES = [
        'add_ons',
        'admin_api_tokens',
        'admin_users',
        'contact_info',
        'analytics_daily_metrics',
        'migrations',
        'order_requests',
        'order_request_add_ons',
        'visitor_sessions',
        'visitor_page_views',
        'vehicles',
        'vehicle_discounts',
    ];

    /** @return array<string, mixed> */
    public static function summary(): array
    {
        $summary = self::readJson('_summary');

        return is_array($summary) ? $summary : [];
    }

    /** @return array<int, array<string, mixed>> */
    public static function table(string $table): array
    {
        if (! in_array($table, self::TABLES, true)) {
            throw new RuntimeException("Unsupported mock table: {$table}");
        }

        $data = self::readJson($table);

        if (! is_array($data)) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $data */
        return $data;
    }

    /** @return array<int, array<string, mixed>> */
    public static function addOns(): array
    {
        return self::table('add_ons');
    }

    /** @return array<int, array<string, mixed>> */
    public static function adminUsers(): array
    {
        return self::table('admin_users');
    }

    /** @return array<int, array<string, mixed>> */
    public static function adminApiTokens(): array
    {
        return self::table('admin_api_tokens');
    }

    /** @return array<int, array<string, mixed>> */
    public static function contactInfo(): array
    {
        return self::table('contact_info');
    }

    /** @return array<int, array<string, mixed>> */
    public static function analyticsDailyMetrics(): array
    {
        return self::table('analytics_daily_metrics');
    }

    /** @return array<int, array<string, mixed>> */
    public static function migrations(): array
    {
        return self::table('migrations');
    }

    /** @return array<int, array<string, mixed>> */
    public static function orderRequests(): array
    {
        return self::table('order_requests');
    }

    /** @return array<int, array<string, mixed>> */
    public static function orderRequestAddOns(): array
    {
        return self::table('order_request_add_ons');
    }

    /** @return array<int, array<string, mixed>> */
    public static function visitorSessions(): array
    {
        return self::table('visitor_sessions');
    }

    /** @return array<int, array<string, mixed>> */
    public static function visitorPageViews(): array
    {
        return self::table('visitor_page_views');
    }

    /** @return array<int, array<string, mixed>> */
    public static function vehicles(): array
    {
        return self::table('vehicles');
    }

    /** @return array<int, array<string, mixed>> */
    public static function vehicleDiscounts(): array
    {
        return self::table('vehicle_discounts');
    }

    /** @return array<int, array<string, mixed>>|array<string, mixed> */
    private static function readJson(string $filename): array
    {
        $path = base_path('mock-data/raw/'.$filename.'.json');

        if (! is_file($path)) {
            throw new RuntimeException("Mock data file not found: {$path}");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read mock data file: {$path}");
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            throw new RuntimeException("Invalid JSON in mock data file: {$path}");
        }

        return $decoded;
    }
}
