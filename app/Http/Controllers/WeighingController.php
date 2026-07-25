<?php

namespace App\Http\Controllers;

use App\Models\Weighing;
use Illuminate\Http\Request;

class WeighingController extends Controller
{
    public function index(Request $request)
    {
        $query = Weighing::query()->with(['weighbridge', 'truck', 'agent', 'driver'])->latest('weighed_at');

        if ($request->filled('from')) {
            $query->where('weighed_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('weighed_at', '<=', $request->date('to'));
        }

        if ($request->filled('weighbridge_id')) {
            $query->where('weighbridge_id', $request->integer('weighbridge_id'));
        }

        if ($request->filled('truck_id')) {
            $query->where('truck_id', $request->integer('truck_id'));
        }

        return response()->json([
            'data' => $query->paginate(20),
        ]);
    }

    public function show(Weighing $weighing)
    {
        return response()->json([
            'data' => $weighing->load(['weighbridge', 'truck', 'agent', 'driver']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'weighbridge_id' => ['required', 'integer', 'exists:weighbridges,id'],
            'truck_id' => ['required', 'integer', 'exists:trucks,id'],
            'agent_id' => ['required', 'integer', 'exists:users,id'],
            'driver_id' => ['nullable', 'integer', 'exists:users,id'],
            'gross_weight' => ['required', 'numeric', 'min:0'],
            'tare_weight' => ['required', 'numeric', 'min:0'],
            'weighed_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $gross = (float) $validated['gross_weight'];
        $tare = (float) $validated['tare_weight'];
        $validated['net_weight'] = $gross - $tare;

        $weighing = Weighing::create($validated);

        return response()->json([
            'data' => $weighing->load(['weighbridge', 'truck', 'agent', 'driver']),
        ], 201);
    }

    public function update(Request $request, Weighing $weighing)
    {
        $validated = $request->validate([
            'weighbridge_id' => ['sometimes', 'required', 'integer', 'exists:weighbridges,id'],
            'truck_id' => ['sometimes', 'required', 'integer', 'exists:trucks,id'],
            'agent_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'driver_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'gross_weight' => ['sometimes', 'required', 'numeric', 'min:0'],
            'tare_weight' => ['sometimes', 'required', 'numeric', 'min:0'],
            'weighed_at' => ['sometimes', 'nullable', 'date'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $gross = array_key_exists('gross_weight', $validated) ? (float) $validated['gross_weight'] : (float) $weighing->gross_weight;
        $tare = array_key_exists('tare_weight', $validated) ? (float) $validated['tare_weight'] : (float) $weighing->tare_weight;
        $validated['net_weight'] = $gross - $tare;

        $weighing->update($validated);

        return response()->json([
            'data' => $weighing->refresh()->load(['weighbridge', 'truck', 'agent', 'driver']),
        ]);
    }

    public function destroy(Weighing $weighing)
    {
        $weighing->delete();

        return response()->json(null, 204);
    }
}
