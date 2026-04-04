<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AddOn;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddOnController extends Controller
{
    public function index(): JsonResponse
    {
        $items = AddOn::query()
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validateAddOn($request);
        $addOn = AddOn::query()->create($payload);

        return response()->json([
            'success' => true,
            'message' => 'Add-on created.',
            'data' => $addOn,
        ], 201);
    }

    public function update(Request $request, AddOn $addOn): JsonResponse
    {
        $payload = $this->validateAddOn($request, true);
        $addOn->fill($payload);
        $addOn->save();

        return response()->json([
            'success' => true,
            'message' => 'Add-on updated.',
            'data' => $addOn,
        ]);
    }

    public function destroy(AddOn $addOn): JsonResponse
    {
        try {
            $addOn->delete();
        } catch (QueryException) {
            return response()->json([
                'success' => false,
                'message' => 'Add-on cannot be deleted because related records exist.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Add-on deleted.',
        ]);
    }

    private function validateAddOn(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $optional = $partial ? 'sometimes' : 'nullable';

        return $request->validate([
            'name' => [$required, 'string', 'min:2', 'max:100'],
            'cost' => [$optional, 'nullable', 'numeric', 'min:0'],
            'description' => [$required, 'string', 'min:2', 'max:5000'],
            'abbr' => [$required, 'string', 'min:1', 'max:100'],
            'fixed_price' => [$optional, 'boolean'],
        ]);
    }
}
