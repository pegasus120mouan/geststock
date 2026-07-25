<?php

namespace App\Http\Controllers;

use App\Models\Commis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CommisController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless(auth()->check(), 401);
            abort_unless(auth()->user()->role === 'admin', 403);

            return $next($request);
        });
    }

    private function fetchPonts(): array
    {
        try {
            $mesPontsUrl = (string) config('services.external_auth.mes_ponts_url');
            $timeout = (int) config('services.external_auth.timeout', 10);
            $response = Http::acceptJson()->withoutVerifying()->timeout($timeout)->get($mesPontsUrl);

            if ($response->successful()) {
                return $response->json('ponts') ?? [];
            }
        } catch (\Throwable $e) {
            // Ignorer
        }

        return [];
    }

    private function findPontById(array $ponts, int $idPont): ?array
    {
        foreach ($ponts as $pont) {
            if ((int) ($pont['id_pont'] ?? 0) === $idPont) {
                return $pont;
            }
        }

        return null;
    }

    public function index(Request $request)
    {
        $query = Commis::query()->orderBy('nom')->orderBy('prenom');

        if ($request->filled('id_pont')) {
            $query->where('id_pont', (int) $request->input('id_pont'));
        }

        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function ($sub) use ($q) {
                $sub->where('nom', 'like', "%{$q}%")
                    ->orWhere('prenom', 'like', "%{$q}%")
                    ->orWhere('contact', 'like', "%{$q}%")
                    ->orWhere('nom_pont', 'like', "%{$q}%")
                    ->orWhere('code_pont', 'like', "%{$q}%")
                    ->orWhere('gerant', 'like', "%{$q}%");
            });
        }

        $ponts = $this->fetchPonts();
        $gerantsParPont = [];
        foreach ($ponts as $pont) {
            $gerantsParPont[(int) ($pont['id_pont'] ?? 0)] = $pont['gerant'] ?? '';
        }

        return view('commis.index', [
            'commis' => $query->paginate(20)->withQueryString(),
            'ponts' => $ponts,
            'gerantsParPont' => $gerantsParPont,
            'external_error' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_pont' => ['required', 'integer'],
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:150'],
            'contact' => ['nullable', 'string', 'max:50'],
            'code_pin' => ['required', 'digits:4'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $pont = $this->findPontById($this->fetchPonts(), (int) $validated['id_pont']);
        if (!$pont) {
            return back()->withInput()->withErrors(['id_pont' => 'Pont invalide ou indisponible.']);
        }

        $data = [
            'id_pont' => (int) $validated['id_pont'],
            'nom_pont' => $pont['nom_pont'] ?? '',
            'code_pont' => $pont['code_pont'] ?? '',
            'gerant' => $pont['gerant'] ?? null,
            'nom' => $validated['nom'],
            'prenom' => $validated['prenom'],
            'contact' => $validated['contact'] ?? null,
            'code_pin' => $validated['code_pin'],
            'password' => $validated['password'],
        ];

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars/commis', 'public');
        } else {
            $data['avatar'] = 'default.png';
        }

        Commis::create($data);

        return redirect()->route('commis.index')->with('success', 'Commis créé avec succès.');
    }

    public function update(Request $request, Commis $commi)
    {
        $validated = $request->validate([
            'id_pont' => ['required', 'integer'],
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:150'],
            'contact' => ['nullable', 'string', 'max:50'],
            'code_pin' => ['nullable', 'digits:4'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $pont = $this->findPontById($this->fetchPonts(), (int) $validated['id_pont']);
        if (!$pont) {
            return back()->withInput()->withErrors(['id_pont' => 'Pont invalide ou indisponible.']);
        }

        $commi->id_pont = (int) $validated['id_pont'];
        $commi->nom_pont = $pont['nom_pont'] ?? '';
        $commi->code_pont = $pont['code_pont'] ?? '';
        $commi->gerant = $pont['gerant'] ?? null;
        $commi->nom = $validated['nom'];
        $commi->prenom = $validated['prenom'];
        $commi->contact = $validated['contact'] ?? null;

        if (!empty($validated['code_pin'])) {
            $commi->code_pin = $validated['code_pin'];
        }

        if (!empty($validated['password'])) {
            $commi->password = $validated['password'];
        }

        if ($request->hasFile('avatar')) {
            $commi->avatar = $request->file('avatar')->store('avatars/commis', 'public');
        }

        $commi->save();

        return redirect()->route('commis.index')->with('success', 'Commis modifié avec succès.');
    }

    public function destroy(Commis $commi)
    {
        $commi->delete();

        return redirect()->route('commis.index')->with('success', 'Commis supprimé avec succès.');
    }
}
