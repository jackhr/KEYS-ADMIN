<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\VehicleDiscount;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleDiscountController extends Controller
{
    public function index(): JsonResponse
    {
        $discounts = VehicleDiscount::query()
            ->with('vehicle:id,name,slug')
            ->orderBy('vehicle_id')
            ->orderBy('days')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $discounts,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validateDiscount($request);
        $discount = VehicleDiscount::query()->create($payload);

        return response()->json([
            'success' => true,
            'message' => 'Vehicle discount created.',
            'data' => $discount->load('vehicle:id,name,slug'),
        ], 201);
    }

    public function update(Request $request, VehicleDiscount $vehicleDiscount): JsonResponse
    {
        $payload = $this->validateDiscount($request, true);
        $vehicleDiscount->fill($payload);
        $vehicleDiscount->save();

        return response()->json([
            'success' => true,
            'message' => 'Vehicle discount updated.',
            'data' => $vehicleDiscount->load('vehicle:id,name,slug'),
        ]);
    }

    public function destroy(VehicleDiscount $vehicleDiscount): JsonResponse
    {
        try {
            $vehicleDiscount->delete();
        } catch (QueryException) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle discount could not be deleted.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vehicle discount deleted.',
        ]);
    }

    private function validateDiscount(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'vehicle_id' => [$required, 'integer', 'exists:vehicles,id'],
            'price_XCD' => [$required, 'numeric', 'min:0'],
            'price_USD' => [$required, 'numeric', 'min:0'],
            'days' => [$required, 'integer', 'min:1', 'max:365'],
        ]);
    }
}
