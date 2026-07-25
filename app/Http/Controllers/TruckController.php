<?php

namespace App\Http\Controllers;

use App\Models\Truck;
use Illuminate\Http\Request;

class TruckController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Truck::query()->with('driver')->latest()->paginate(20),
        ]);
    }

    public function show(Truck $truck)
    {
        return response()->json([
            'data' => $truck->load(['driver']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'registration_number' => ['required', 'string', 'max:255', 'unique:trucks,registration_number'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'driver_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $truck = Truck::create($validated);

        return response()->json([
            'data' => $truck->load(['driver']),
        ], 201);
    }

    public function update(Request $request, Truck $truck)
    {
        $validated = $request->validate([
            'registration_number' => ['sometimes', 'required', 'string', 'max:255', 'unique:trucks,registration_number,' . $truck->id],
            'brand' => ['sometimes', 'nullable', 'string', 'max:255'],
            'model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'year' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:2100'],
            'driver_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $truck->update($validated);

        return response()->json([
            'data' => $truck->refresh()->load(['driver']),
        ]);
    }

    public function destroy(Truck $truck)
    {
        $truck->delete();

        return response()->json(null, 204);
    }
}
