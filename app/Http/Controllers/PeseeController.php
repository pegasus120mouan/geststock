<?php

namespace App\Http\Controllers;

use App\Models\Camion;
use App\Models\Pesee;
use App\Models\PontPesage;
use App\Models\Produit;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Validation\ValidationException;

class PeseeController extends Controller
{
    public function index(Request $request)
    {
        $query = Pesee::query()->with(['pontPesage', 'camion', 'produit', 'agent', 'chauffeur'])->latest('pese_le');

        if ($request->filled('du')) {
            $query->where('pese_le', '>=', $request->date('du'));
        }

        if ($request->filled('au')) {
            $query->where('pese_le', '<=', $request->date('au'));
        }

        if ($request->filled('pont_pesage_id')) {
            $query->where('pont_pesage_id', $request->integer('pont_pesage_id'));
        }

        if ($request->filled('camion_id')) {
            $query->where('camion_id', $request->integer('camion_id'));
        }

        if ($request->filled('produit_id')) {
            $query->where('produit_id', $request->integer('produit_id'));
        }

        $pesees = $query->paginate(20)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $pesees,
            ]);
        }

        $ponts = PontPesage::query()->orderBy('nom')->get();
        $camions = Camion::query()->orderBy('immatriculation')->get();
        $produits = Produit::query()->orderBy('nom')->get();
        $agents = User::query()->where('role', 'agent')->orderBy('name')->get();
        $chauffeurs = User::query()->where('role', 'driver')->orderBy('name')->get();

        return view('pesees.index', [
            'pesees' => $pesees,
            'ponts' => $ponts,
            'camions' => $camions,
            'produits' => $produits,
            'agents' => $agents,
            'chauffeurs' => $chauffeurs,
        ]);
    }

    public function show(Request $request, Pesee $pesee)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'data' => $pesee->load(['pontPesage', 'camion', 'produit', 'agent', 'chauffeur']),
            ]);
        }

        return view('pesees.show', [
            'pesee' => $pesee->load(['pontPesage', 'camion', 'produit', 'agent', 'chauffeur']),
        ]);
    }

    public function edit(Request $request, Pesee $pesee)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'data' => $pesee->load(['pontPesage', 'camion', 'produit', 'agent', 'chauffeur']),
            ]);
        }

        $ponts = PontPesage::query()->orderBy('nom')->get();
        $camions = Camion::query()->orderBy('immatriculation')->get();
        $produits = Produit::query()->orderBy('nom')->get();
        $agents = User::query()->where('role', 'agent')->orderBy('name')->get();
        $chauffeurs = User::query()->where('role', 'driver')->orderBy('name')->get();

        return view('pesees.edit', [
            'pesee' => $pesee->load(['pontPesage', 'camion', 'produit', 'agent', 'chauffeur']),
            'ponts' => $ponts,
            'camions' => $camions,
            'produits' => $produits,
            'agents' => $agents,
            'chauffeurs' => $chauffeurs,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pont_pesage_id' => ['required', 'integer', 'exists:ponts_pesage,id'],
            'camion_id' => ['required', 'integer', 'exists:camions,id'],
            'produit_id' => ['required', 'integer', 'exists:produits,id'],
            'agent_id' => ['required', 'integer', 'exists:users,id'],
            'chauffeur_id' => ['nullable', 'integer', 'exists:users,id'],
            'poids_brut' => ['required', 'numeric', 'min:0'],
            'poids_vide' => ['nullable', 'numeric', 'min:0'],
            'pese_le' => ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if ($value === null || $value === '') {
                    return;
                }

                try {
                    if (is_string($value) && str_contains($value, 'T')) {
                        Carbon::parse($value);
                    } else {
                        Carbon::createFromFormat('d/m/Y', (string) $value);
                    }
                } catch (\Throwable) {
                    $fail('Format de date invalide. Utilise jj/mm/aaaa.');
                }
            }],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        if (!array_key_exists('status', $validated) || empty($validated['status'])) {
            $validated['status'] = 'validated';
        }

        if (!array_key_exists('poids_vide', $validated) || $validated['poids_vide'] === null || $validated['poids_vide'] === '') {
            $validated['poids_vide'] = 0;
        }

        if (empty($validated['reference'] ?? null)) {
            $prefix = 'PES-';
            $stamp = Carbon::now()->format('YmdHis');

            do {
                $candidate = $prefix . $stamp . '-' . random_int(100, 999);
            } while (Pesee::query()->where('reference', $candidate)->exists());

            $validated['reference'] = $candidate;
        }

        if (!empty($validated['pese_le'])) {
            $now = Carbon::now();

            $validated['pese_le'] = str_contains($validated['pese_le'], 'T')
                ? Carbon::parse($validated['pese_le'])
                : Carbon::createFromFormat('d/m/Y', $validated['pese_le'])->setTime($now->hour, $now->minute, $now->second);
        } else {
            unset($validated['pese_le']);
        }

        $produit = Produit::query()->findOrFail((int) $validated['produit_id']);

        $brut = (float) $validated['poids_brut'];
        $tare = (float) $produit->tare;

        $validated['tare'] = $tare;
        $validated['poids_apres_refraction'] = $brut - $tare;

        $poidsVide = (float) $validated['poids_vide'];

        if ($poidsVide !== null && $poidsVide > (float) $validated['poids_apres_refraction']) {
            throw ValidationException::withMessages([
                'poids_vide' => 'Le poids vide ne peut pas être supérieur au poids après réfraction.',
            ]);
        }

        $validated['poids_net'] = (float) $validated['poids_apres_refraction'] - $poidsVide;

        $pesee = Pesee::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $pesee->load(['pontPesage', 'camion', 'produit', 'agent', 'chauffeur']),
            ], 201);
        }

        return redirect()->route('pesees.index');
    }

    public function validateStatus(Pesee $pesee)
    {
        $pesee->update([
            'status' => 'validated',
            'cancel_reason' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
        ]);

        return redirect()->back();
    }

    public function ticket(Pesee $pesee)
    {
        $pesee->load(['pontPesage', 'camion', 'produit', 'agent', 'chauffeur']);

        $ticketUrl = url('/pesees/' . $pesee->id);

        $qrRefSvg = QrCode::format('svg')->size(140)->margin(1)->generate((string) ($pesee->reference ?? $pesee->id));
        $qrUrlSvg = QrCode::format('svg')->size(140)->margin(1)->generate($ticketUrl);

        $logoPath = public_path('img/logo/logo.png');
        $logoBase64 = is_file($logoPath) ? base64_encode((string) file_get_contents($logoPath)) : null;

        $pdf = Pdf::loadView('pesees.ticket', [
            'pesee' => $pesee,
            'ticketUrl' => $ticketUrl,
            'qrRefBase64' => base64_encode($qrRefSvg),
            'qrUrlBase64' => base64_encode($qrUrlSvg),
            'logoBase64' => $logoBase64,
        ])->setPaper('a4');

        $filename = 'ticket-' . ($pesee->reference ?: $pesee->id) . '.pdf';

        return $pdf->stream($filename);
    }

    public function cancel(Request $request, Pesee $pesee)
    {
        $data = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:255'],
        ]);

        $pesee->update([
            'status' => 'cancelled',
            'cancel_reason' => $data['cancel_reason'],
            'cancelled_at' => Carbon::now(),
            'cancelled_by' => Auth::id(),
        ]);

        return redirect()->back();
    }

    public function update(Request $request, Pesee $pesee)
    {
        $validated = $request->validate([
            'pont_pesage_id' => ['sometimes', 'required', 'integer', 'exists:ponts_pesage,id'],
            'camion_id' => ['sometimes', 'required', 'integer', 'exists:camions,id'],
            'produit_id' => ['sometimes', 'required', 'integer', 'exists:produits,id'],
            'agent_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'chauffeur_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'poids_brut' => ['sometimes', 'required', 'numeric', 'min:0'],
            'poids_vide' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'pese_le' => ['sometimes', 'nullable', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if ($value === null || $value === '') {
                    return;
                }

                try {
                    if (is_string($value) && str_contains($value, 'T')) {
                        Carbon::parse($value);
                    } else {
                        Carbon::createFromFormat('d/m/Y', (string) $value);
                    }
                } catch (\Throwable) {
                    $fail('Format de date invalide. Utilise jj/mm/aaaa.');
                }
            }],
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        if (array_key_exists('pese_le', $validated)) {
            if (!empty($validated['pese_le'])) {
                $now = Carbon::now();

                $validated['pese_le'] = str_contains($validated['pese_le'], 'T')
                    ? Carbon::parse($validated['pese_le'])
                    : Carbon::createFromFormat('d/m/Y', $validated['pese_le'])->setTime($now->hour, $now->minute, $now->second);
            } else {
                $validated['pese_le'] = null;
            }
        }

        $brut = array_key_exists('poids_brut', $validated) ? (float) $validated['poids_brut'] : (float) $pesee->poids_brut;

        $produitId = array_key_exists('produit_id', $validated) ? (int) $validated['produit_id'] : (int) $pesee->produit_id;
        $produit = Produit::query()->findOrFail($produitId);
        $tare = (float) $produit->tare;

        $validated['tare'] = $tare;
        $validated['poids_apres_refraction'] = $brut - $tare;

        $poidsVide = array_key_exists('poids_vide', $validated) ? $validated['poids_vide'] : $pesee->poids_vide;
        $poidsVide = $poidsVide === null ? null : (float) $poidsVide;

        if ($poidsVide !== null && $poidsVide > (float) $validated['poids_apres_refraction']) {
            throw ValidationException::withMessages([
                'poids_vide' => 'Le poids vide ne peut pas être supérieur au poids après réfraction.',
            ]);
        }

        $validated['poids_net'] = $poidsVide === null
            ? null
            : ((float) $validated['poids_apres_refraction'] - $poidsVide);

        $pesee->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $pesee->refresh()->load(['pontPesage', 'camion', 'produit', 'agent', 'chauffeur']),
            ]);
        }

        return redirect()->route('pesees.index');
    }

    public function destroy(Pesee $pesee)
    {
        $pesee->delete();

        if (request()->wantsJson()) {
            return response()->json(null, 204);
        }

        return redirect()->back();
    }
}
