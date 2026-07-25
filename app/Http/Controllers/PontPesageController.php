<?php

namespace App\Http\Controllers;

use App\Models\PontPesage;
use Illuminate\Http\Request;

class PontPesageController extends Controller
{
    public function index(Request $request)
    {
        $query = PontPesage::query()->latest();

        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function ($q2) use ($q) {
                $q2->where('code', 'like', "%{$q}%")
                    ->orWhere('nom', 'like', "%{$q}%")
                    ->orWhere('localisation', 'like', "%{$q}%");
            });
        }

        $ponts = $query->paginate(20)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json(['data' => $ponts]);
        }

        return view('ponts_pesage.index', [
            'ponts' => $ponts,
        ]);
    }

    public function show(Request $request, PontPesage $pontPesage)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'data' => $pontPesage->load(['agents']),
            ]);
        }

        return redirect()->route('ponts_pesage.edit', $pontPesage);
    }

    public function edit(Request $request, PontPesage $pontPesage)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'data' => $pontPesage->load(['agents']),
            ]);
        }

        return view('ponts_pesage.edit', [
            'pontPesage' => $pontPesage,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:ponts_pesage,code'],
            'nom' => ['required', 'string', 'max:255'],
            'localisation' => ['nullable', 'string', 'max:255'],
            'actif' => ['nullable', 'boolean'],
        ]);

        $validated['actif'] = (bool) ($validated['actif'] ?? false);

        $pontPesage = PontPesage::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $pontPesage,
            ], 201);
        }

        return redirect()->back();
    }

    public function update(Request $request, PontPesage $pontPesage)
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:255', 'unique:ponts_pesage,code,' . $pontPesage->id],
            'nom' => ['sometimes', 'required', 'string', 'max:255'],
            'localisation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $validated['actif'] = (bool) ($request->boolean('actif'));

        $pontPesage->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $pontPesage->refresh(),
            ]);
        }

        return redirect()->back();
    }

    public function toggleGerable(PontPesage $pontPesage)
    {
        $pontPesage->update(['gerable' => !$pontPesage->gerable]);

        if (request()->wantsJson()) {
            return response()->json(['data' => $pontPesage->refresh()]);
        }

        return redirect()->back();
    }

    public function destroy(PontPesage $pontPesage)
    {
        $pontPesage->delete();

        if (request()->wantsJson()) {
            return response()->json(null, 204);
        }

        return redirect()->back();
    }
}
