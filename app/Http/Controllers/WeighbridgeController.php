<?php

namespace App\Http\Controllers;

use App\Models\Weighbridge;
use Illuminate\Http\Request;

class WeighbridgeController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Weighbridge::query()->latest()->paginate(20),
        ]);
    }

    public function show(Weighbridge $weighbridge)
    {
        return response()->json([
            'data' => $weighbridge->load(['agents']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:weighbridges,code'],
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $weighbridge = Weighbridge::create($validated);

        return response()->json([
            'data' => $weighbridge,
        ], 201);
    }

    public function update(Request $request, Weighbridge $weighbridge)
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:255', 'unique:weighbridges,code,' . $weighbridge->id],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $weighbridge->update($validated);

        return response()->json([
            'data' => $weighbridge->refresh(),
        ]);
    }

    public function destroy(Weighbridge $weighbridge)
    {
        $weighbridge->delete();

        return response()->json(null, 204);
    }
}
