<?php

namespace App\Services;

use App\Models\AnalyticsDailyMetric;
use App\Models\OrderRequest;
use App\Models\VisitorPageView;
use App\Models\VisitorSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

class AdminAnalyticsService
{
    private const RANGE_DAYS = [
        '7d' => 7,
        '30d' => 30,
        '90d' => 90,
    ];

    public function build(string $range): array
    {
        $days = $this->resolveRangeDays($range);
        $now = CarbonImmutable::now('UTC');
        $endDate = $now->startOfDay();
        $startDate = $endDate->subDays($days - 1);

        $dailyRows = $this->buildDailyRows($startDate, $endDate);

        $metrics = $this->buildMetricCards($dailyRows, $days, $now);
        $this->persistDailySnapshots($dailyRows, $now);

        return [
            'range' => $range,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'generated_at' => $now->toIso8601String(),
            'cards' => $metrics,
            'chart' => $dailyRows,
            'table' => array_values(array_reverse($dailyRows)),
        ];
    }

    private function resolveRangeDays(string $range): int
    {
        return self::RANGE_DAYS[$range] ?? self::RANGE_DAYS['90d'];
    }

    /** @return array<int, array<string, int|float|string>> */
    private function buildDailyRows(CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $rowsByDate = [];
        $cursor = $startDate;

        while ($cursor->lessThanOrEqualTo($endDate)) {
            $date = $cursor->toDateString();
            $rowsByDate[$date] = [
                'date' => $date,
                'label' => $cursor->format('M j'),
                'revenue_usd' => 0.0,
                'order_requests' => 0,
                'new_customers' => 0,
                'active_vehicles' => 0,
                'unique_visitors' => 0,
                'mobile_visitors' => 0,
                'desktop_visitors' => 0,
                'page_views' => 0,
                'growth_rate_pct' => 0.0,
            ];
            $cursor = $cursor->addDay();
        }

        $orderAggregates = OrderRequest::query()
            ->selectRaw('DATE(created_at) AS metric_date')
            ->selectRaw('COUNT(*) AS order_requests')
            ->selectRaw('COALESCE(SUM(sub_total), 0) AS revenue_usd')
            ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->groupBy('metric_date')
            ->orderBy('metric_date')
            ->get();

        foreach ($orderAggregates as $aggregate) {
            $metricDate = (string) $aggregate->metric_date;

            if (! isset($rowsByDate[$metricDate])) {
                continue;
            }

            $orders = (int) $aggregate->order_requests;
            $rowsByDate[$metricDate]['order_requests'] = $orders;
            $rowsByDate[$metricDate]['new_customers'] = $orders;
            $rowsByDate[$metricDate]['revenue_usd'] = round((float) $aggregate->revenue_usd, 2);
        }

        try {
            $visitorAggregates = VisitorPageView::query()
                ->selectRaw('DATE(visited_at) AS metric_date')
                ->selectRaw('COUNT(*) AS page_views')
                ->selectRaw('COUNT(DISTINCT visitor_id) AS unique_visitors')
                ->selectRaw("COUNT(DISTINCT CASE WHEN device_type = 'mobile' THEN visitor_id END) AS mobile_visitors")
                ->selectRaw("COUNT(DISTINCT CASE WHEN device_type = 'desktop' THEN visitor_id END) AS desktop_visitors")
                ->whereBetween('visited_at', [$startDate->startOfDay(), $endDate->endOfDay()])
                ->groupBy('metric_date')
                ->orderBy('metric_date')
                ->get();

            foreach ($visitorAggregates as $aggregate) {
                $metricDate = (string) $aggregate->metric_date;

                if (! isset($rowsByDate[$metricDate])) {
                    continue;
                }

                $rowsByDate[$metricDate]['unique_visitors'] = (int) ($aggregate->unique_visitors ?? 0);
                $rowsByDate[$metricDate]['mobile_visitors'] = (int) ($aggregate->mobile_visitors ?? 0);
                $rowsByDate[$metricDate]['desktop_visitors'] = (int) ($aggregate->desktop_visitors ?? 0);
                $rowsByDate[$metricDate]['page_views'] = (int) ($aggregate->page_views ?? 0);
            }
        } catch (QueryException) {
            // Visitor tables may not exist yet; keep analytics payload resilient.
        }

        $activeVehicleSets = [];
        $overlappingOrders = OrderRequest::query()
            ->select(['car_id', 'pick_up', 'drop_off'])
            ->where('pick_up', '<=', $endDate->endOfDay())
            ->where('drop_off', '>=', $startDate->startOfDay())
            ->whereNotNull('car_id')
            ->get();

        foreach ($overlappingOrders as $order) {
            $bookingStart = CarbonImmutable::parse((string) $order->pick_up, 'UTC')->startOfDay();
            $bookingEnd = CarbonImmutable::parse((string) $order->drop_off, 'UTC')->startOfDay();

            if ($bookingEnd->lessThan($startDate) || $bookingStart->greaterThan($endDate)) {
                continue;
            }

            $windowStart = $bookingStart->greaterThan($startDate) ? $bookingStart : $startDate;
            $windowEnd = $bookingEnd->lessThan($endDate) ? $bookingEnd : $endDate;
            $day = $windowStart;

            while ($day->lessThanOrEqualTo($windowEnd)) {
                $date = $day->toDateString();

                if (! isset($activeVehicleSets[$date])) {
                    $activeVehicleSets[$date] = [];
                }

                $activeVehicleSets[$date][(int) $order->car_id] = true;
                $day = $day->addDay();
            }
        }

        foreach ($activeVehicleSets as $date => $vehicles) {
            if (isset($rowsByDate[$date])) {
                $rowsByDate[$date]['active_vehicles'] = count($vehicles);
            }
        }

        $previousRevenue = 0.0;

        foreach ($rowsByDate as &$row) {
            $currentRevenue = (float) $row['revenue_usd'];
            $row['growth_rate_pct'] = round($this->percentageChange($currentRevenue, $previousRevenue), 2);
            $previousRevenue = $currentRevenue;
        }
        unset($row);

        return array_values($rowsByDate);
    }

    /**
     * @param  array<int, array<string, int|float|string>>  $dailyRows
     * @return array<string, array<string, float>>
     */
    private function buildMetricCards(array $dailyRows, int $days, CarbonImmutable $now): array
    {
        $currentRevenue = array_reduce($dailyRows, static function (float $carry, array $row): float {
            return $carry + (float) $row['revenue_usd'];
        }, 0.0);

        $currentActiveVehicles = $this->countActiveVehiclesAt($now);
        $newCustomerWindow = $this->buildTrailingWindowFromNow($now, 30);
        $currentOrders = $this->countOrderRequestsBetween($newCustomerWindow['start'], $newCustomerWindow['end']);
        $previousNewCustomerWindow = [
            'start' => $newCustomerWindow['start']->subDays(30),
            'end' => $newCustomerWindow['start']->subDay(),
        ];
        $previousOrders = $this->countOrderRequestsBetween(
            $previousNewCustomerWindow['start'],
            $previousNewCustomerWindow['end']
        );

        $previousEnd = $now->subDays($days)->startOfDay();
        $previousStart = $previousEnd->subDays($days - 1);
        $previousRows = $this->buildDailyRows($previousStart, $previousEnd);

        $previousRevenue = array_reduce($previousRows, static function (float $carry, array $row): float {
            return $carry + (float) $row['revenue_usd'];
        }, 0.0);
        $previousActiveVehicles = $this->countActiveVehiclesAt($now->subDays($days));
        $currentGrowthRate = $this->percentageChange($currentRevenue, $previousRevenue);

        $prePreviousEnd = $previousStart->subDay();
        $prePreviousStart = $prePreviousEnd->subDays($days - 1);
        $prePreviousRows = $this->buildDailyRows($prePreviousStart, $prePreviousEnd);
        $prePreviousRevenue = array_reduce($prePreviousRows, static function (float $carry, array $row): float {
            return $carry + (float) $row['revenue_usd'];
        }, 0.0);

        $previousGrowthRate = $this->percentageChange($previousRevenue, $prePreviousRevenue);

        return [
            'total_revenue' => [
                'value' => round($currentRevenue, 2),
                'change_pct' => round($this->percentageChange($currentRevenue, $previousRevenue), 2),
            ],
            'new_customers' => [
                'value' => (float) $currentOrders,
                'change_pct' => round($this->percentageChange((float) $currentOrders, (float) $previousOrders), 2),
            ],
            'current_vehicles' => [
                'value' => (float) $currentActiveVehicles,
                'change_pct' => round($this->percentageChange((float) $currentActiveVehicles, (float) $previousActiveVehicles), 2),
            ],
            'growth_rate' => [
                'value' => round($currentGrowthRate, 2),
                'change_pct' => round($currentGrowthRate - $previousGrowthRate, 2),
            ],
        ];
    }

    /** @return array{start: CarbonImmutable, end: CarbonImmutable} */
    private function buildTrailingWindowFromNow(CarbonImmutable $now, int $days): array
    {
        $end = $now->startOfDay();
        $start = $end->subDays(max(1, $days) - 1);

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    private function countOrderRequestsBetween(CarbonImmutable $startDate, CarbonImmutable $endDate): int
    {
        return OrderRequest::query()
            ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->count();
    }

    private function countActiveVehiclesAt(CarbonImmutable $moment): int
    {
        return OrderRequest::query()
            ->where('pick_up', '<=', $moment)
            ->where('drop_off', '>=', $moment)
            ->distinct('car_id')
            ->count('car_id');
    }

    private function percentageChange(float $current, float $baseline): float
    {
        if ($baseline <= 0) {
            if ($current <= 0) {
                return 0.0;
            }

            return 100.0;
        }

        return (($current - $baseline) / $baseline) * 100;
    }

    /** @param array<string, mixed> $filters */
    public function buildDailySessions(string $date, array $filters): array
    {
        $targetDate = $this->parseDateString($date);
        $start = $targetDate->startOfDay();
        $end = $targetDate->endOfDay();

        $perPage = max(1, min(500, (int) ($filters['per_page'] ?? 50)));
        $requestedPage = max(1, (int) ($filters['page'] ?? 1));
        $deviceType = isset($filters['device_type']) ? trim((string) $filters['device_type']) : '';
        $botMode = (string) ($filters['bot_mode'] ?? 'exclude');
        $referrerContains = isset($filters['referrer_contains']) ? trim((string) $filters['referrer_contains']) : '';
        $minPageViews = max(0, (int) ($filters['min_page_views'] ?? 0));
        $minDurationSeconds = max(0, (int) ($filters['min_duration_seconds'] ?? 0));

        $query = VisitorSession::query()
            ->where('first_seen_at', '<=', $end)
            ->where('last_seen_at', '>=', $start)
            ->withCount(['pageViews as page_views_count' => function ($pageViews) use ($start, $end): void {
                $pageViews->whereBetween('visited_at', [$start, $end]);
            }])
            ->orderByDesc('last_seen_at');

        if ($deviceType !== '' && $deviceType !== 'bot') {
            $query->where('device_type', $deviceType);
        }

        if ($deviceType === 'bot') {
            $query->where('is_bot', true);
        } elseif ($botMode === 'only') {
            $query->where('is_bot', true);
        } elseif ($botMode === 'exclude') {
            $query->where('is_bot', false);
        }

        if ($referrerContains !== '') {
            $safeLike = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $referrerContains).'%';

            $query->where(function ($nested) use ($safeLike, $start, $end): void {
                $nested
                    ->where('entry_referrer', 'like', $safeLike)
                    ->orWhereHas('pageViews', function ($pageViews) use ($safeLike, $start, $end): void {
                        $pageViews
                            ->whereBetween('visited_at', [$start, $end])
                            ->where(function ($pageViewsNested) use ($safeLike): void {
                                $pageViewsNested
                                    ->where('referrer', 'like', $safeLike)
                                    ->orWhere('full_url', 'like', $safeLike)
                                    ->orWhere('query_string', 'like', $safeLike)
                                    ->orWhere('route_path', 'like', $safeLike);
                            });
                    });
            });
        }

        try {
            $sessionRows = $query->get()
                ->map(static function (VisitorSession $session): array {
                    $startedAt = $session->first_seen_at;
                    $endedAt = $session->last_seen_at;
                    $durationSeconds = 0;

                    if ($startedAt !== null && $endedAt !== null) {
                        $durationSeconds = max(0, $startedAt->diffInSeconds($endedAt));
                    }

                    return [
                        'id' => (int) $session->id,
                        'session_id' => (string) $session->session_id,
                        'visitor_id' => (string) $session->visitor_id,
                        'first_seen_at' => $startedAt?->toIso8601String(),
                        'last_seen_at' => $endedAt?->toIso8601String(),
                        'session_duration_seconds' => $durationSeconds,
                        'page_views' => max(0, (int) ($session->page_views_count ?? 0)),
                        'entry_path' => $session->entry_path,
                        'entry_referrer' => $session->entry_referrer,
                        'device_type' => $session->device_type ?? 'other',
                        'is_bot' => (bool) $session->is_bot,
                        'os_name' => $session->os_name,
                        'browser_name' => $session->browser_name,
                        'language' => $session->language,
                        'timezone' => $session->timezone,
                        'ip_address' => $session->ip_address,
                    ];
                })
                ->values();
        } catch (QueryException) {
            $sessionRows = collect();
        }

        if ($minPageViews > 0) {
            $sessionRows = $sessionRows
                ->filter(static fn (array $row): bool => (int) $row['page_views'] >= $minPageViews)
                ->values();
        }

        if ($minDurationSeconds > 0) {
            $sessionRows = $sessionRows
                ->filter(static fn (array $row): bool => (int) $row['session_duration_seconds'] >= $minDurationSeconds)
                ->values();
        }

        $totalSessions = $sessionRows->count();
        $lastPage = max(1, (int) ceil($totalSessions / $perPage));
        $currentPage = min($requestedPage, $lastPage);
        $paginatedRows = $sessionRows->forPage($currentPage, $perPage)->values()->all();

        $botSessions = $sessionRows->filter(static fn (array $row): bool => (bool) $row['is_bot'])->count();
        $avgDurationSeconds = $totalSessions > 0
            ? round((float) ($sessionRows->avg('session_duration_seconds') ?? 0), 2)
            : 0.0;
        $avgPagesPerSession = $totalSessions > 0
            ? round((float) ($sessionRows->avg('page_views') ?? 0), 2)
            : 0.0;

        return [
            'date' => $targetDate->toDateString(),
            'filters' => [
                'device_type' => $deviceType === '' ? null : $deviceType,
                'bot_mode' => $botMode,
                'referrer_contains' => $referrerContains,
                'min_page_views' => $minPageViews,
                'min_duration_seconds' => $minDurationSeconds,
            ],
            'summary' => [
                'unique_visitors' => $sessionRows
                    ->pluck('visitor_id')
                    ->filter(static fn ($visitorId): bool => is_string($visitorId) && $visitorId !== '')
                    ->unique()
                    ->count(),
                'total_sessions' => $totalSessions,
                'avg_session_duration_seconds' => $avgDurationSeconds,
                'avg_pages_per_session' => $avgPagesPerSession,
                'bot_session_pct' => $totalSessions > 0
                    ? round(($botSessions / $totalSessions) * 100, 2)
                    : 0.0,
            ],
            'sessions' => [
                'items' => $paginatedRows,
                'meta' => [
                    'current_page' => $currentPage,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'total' => $totalSessions,
                ],
            ],
        ];
    }

    /** @param array<string, mixed> $filters */
    public function buildSessionPageViews(VisitorSession $session, array $filters): array
    {
        $perPage = max(1, min(500, (int) ($filters['per_page'] ?? 200)));
        $requestedPage = max(1, (int) ($filters['page'] ?? 1));
        $date = isset($filters['date']) ? (string) $filters['date'] : null;
        $windowStart = $date !== null ? $this->parseDateString($date)->startOfDay() : null;
        $windowEnd = $windowStart?->endOfDay();

        $query = VisitorPageView::query()
            ->where('visitor_session_id', $session->id)
            ->orderByDesc('visited_at');

        if ($windowStart !== null && $windowEnd !== null) {
            $query->whereBetween('visited_at', [$windowStart, $windowEnd]);
        }

        try {
            $total = (clone $query)->count();
        } catch (QueryException) {
            $total = 0;
        }
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min($requestedPage, $lastPage);

        try {
            $items = $query
                ->forPage($currentPage, $perPage)
                ->get()
                ->map(static function (VisitorPageView $view): array {
                    return [
                        'id' => (int) $view->id,
                        'visited_at' => $view->visited_at?->toIso8601String(),
                        'route_path' => $view->route_path,
                        'full_url' => $view->full_url,
                        'query_string' => $view->query_string,
                        'referrer' => $view->referrer,
                        'event_type' => $view->event_type,
                        'device_type' => $view->device_type,
                        'is_bot' => (bool) $view->is_bot,
                        'os_name' => $view->os_name,
                        'browser_name' => $view->browser_name,
                        'language' => $view->language,
                        'timezone' => $view->timezone,
                        'ip_address' => $view->ip_address,
                        'viewport_width' => $view->viewport_width,
                        'viewport_height' => $view->viewport_height,
                        'screen_width' => $view->screen_width,
                        'screen_height' => $view->screen_height,
                        'metadata' => $view->metadata,
                    ];
                })
                ->values()
                ->all();
        } catch (QueryException) {
            $items = [];
        }

        return [
            'session' => [
                'id' => (int) $session->id,
                'session_id' => (string) $session->session_id,
                'visitor_id' => (string) $session->visitor_id,
                'first_seen_at' => $session->first_seen_at?->toIso8601String(),
                'last_seen_at' => $session->last_seen_at?->toIso8601String(),
                'entry_path' => $session->entry_path,
                'entry_referrer' => $session->entry_referrer,
                'device_type' => $session->device_type ?? 'other',
                'is_bot' => (bool) $session->is_bot,
                'os_name' => $session->os_name,
                'browser_name' => $session->browser_name,
                'language' => $session->language,
                'timezone' => $session->timezone,
                'ip_address' => $session->ip_address,
            ],
            'date' => $date,
            'page_views' => [
                'items' => $items,
                'meta' => [
                    'current_page' => $currentPage,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'total' => $total,
                ],
            ],
        ];
    }

    private function parseDateString(string $date): CarbonImmutable
    {
        $parsed = CarbonImmutable::createFromFormat('Y-m-d', $date, 'UTC');

        if (! $parsed instanceof CarbonImmutable || $parsed->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException("Invalid date format: {$date}");
        }

        return $parsed;
    }

    /** @param array<int, array<string, int|float|string>> $dailyRows */
    private function persistDailySnapshots(array $dailyRows, CarbonImmutable $capturedAt): void
    {
        $payload = [];
        $capturedAtString = $capturedAt->toDateTimeString();

        foreach ($dailyRows as $row) {
            $payload[] = [
                'snapshot_date' => (string) $row['date'],
                'order_requests_count' => (int) $row['order_requests'],
                'new_customers_count' => (int) $row['new_customers'],
                'active_vehicles_count' => (int) $row['active_vehicles'],
                'revenue_usd' => (float) $row['revenue_usd'],
                'growth_rate_pct' => (float) $row['growth_rate_pct'],
                'unique_visitors_count' => (int) ($row['unique_visitors'] ?? 0),
                'mobile_visitors_count' => (int) ($row['mobile_visitors'] ?? 0),
                'desktop_visitors_count' => (int) ($row['desktop_visitors'] ?? 0),
                'page_views_count' => (int) ($row['page_views'] ?? 0),
                'metadata' => json_encode([
                    'label' => (string) $row['label'],
                    'source' => 'order_requests_live',
                ]) ?: '{}',
                'captured_at' => $capturedAtString,
                'updated_at' => $capturedAtString,
                'created_at' => $capturedAtString,
            ];
        }

        if ($payload === []) {
            return;
        }

        try {
            AnalyticsDailyMetric::query()->upsert(
                $payload,
                ['snapshot_date'],
                [
                    'order_requests_count',
                    'new_customers_count',
                    'active_vehicles_count',
                    'revenue_usd',
                    'growth_rate_pct',
                    'unique_visitors_count',
                    'mobile_visitors_count',
                    'desktop_visitors_count',
                    'page_views_count',
                    'metadata',
                    'captured_at',
                    'updated_at',
                ]
            );
        } catch (QueryException) {
            // Snapshot persistence is best-effort; analytics payload should still resolve.
        }
    }
}
