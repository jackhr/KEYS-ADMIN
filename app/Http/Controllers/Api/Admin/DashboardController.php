<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AddOn;
use App\Models\OrderRequest;
use App\Models\Vehicle;
use App\Models\VehicleDiscount;
use App\Models\VisitorSession;
use App\Services\AdminAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private AdminAnalyticsService $analyticsService) {}

    public function summary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'vehicles_total' => Vehicle::query()->count(),
                'vehicles_showing' => Vehicle::query()->where('showing', 1)->count(),
                'add_ons_total' => AddOn::query()->count(),
                'vehicle_discounts_total' => VehicleDiscount::query()->count(),
                'order_requests_total' => OrderRequest::query()->count(),
                'order_requests_pending' => OrderRequest::query()->where('confirmed', 0)->count(),
                'order_requests_confirmed' => OrderRequest::query()->where('confirmed', 1)->count(),
                'order_requests_revenue' => (float) (OrderRequest::query()->sum('sub_total') ?? 0),
            ],
        ]);
    }

    public function analytics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'range' => ['nullable', 'in:7d,30d,90d'],
        ]);

        $range = (string) ($validated['range'] ?? '90d');

        return response()->json([
            'success' => true,
            'data' => $this->analyticsService->build($range),
        ]);
    }

    public function analyticsSessions(Request $request, string $date): JsonResponse
    {
        $request->merge(['date' => $date]);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'device_type' => ['nullable', 'in:desktop,mobile,tablet,bot,other'],
            'bot_mode' => ['nullable', 'in:exclude,include,only'],
            'referrer_contains' => ['nullable', 'string', 'max:255'],
            'min_page_views' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'min_duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->analyticsService->buildDailySessions((string) $validated['date'], $validated),
        ]);
    }

    public function analyticsSessionPageViews(Request $request, VisitorSession $visitorSession): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->analyticsService->buildSessionPageViews($visitorSession, $validated),
        ]);
    }
}
