<?php

namespace App\Http\Controllers;

use App\Models\BordereauPgf;
use App\Models\CamionEtat;
use App\Models\Camion;
use App\Models\FicheSortie;
use App\Models\Groupe;
use App\Models\GroupeVehicule;
use App\Models\PaiementPgf;
use App\Models\Ticket;
use App\Models\TransporteurVehicule;
use App\Models\Usine;
use App\Models\User;
use App\Services\BordereauPgfService;
use App\Services\CaisseService;
use App\Services\ChefEquipeSession;
use App\Services\MesTicketsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class CamionController extends Controller
{
    public function __construct(
        private BordereauPgfService $bordereauPgf,
        private CaisseService $caisseService,
    ) {}

    public function index(Request $request)
    {
        $timeout = 10;

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout($timeout)
                ->get('https://api.objetombrepegasus.online/api/camions/mes_camions.php');
        } catch (\Throwable $e) {
            return view('camions.index', [
                'camions' => new LengthAwarePaginator([], 0, 20),
                'chauffeurs' => collect(),
                'external_camions' => [],
                'etats_par_vehicule' => [],
                'vehicules_en_cours' => [],
                'external_error' => "Impossible de joindre le service camions.",
            ]);
        }

        if (! $response->successful()) {
            $message = (string) ($response->json('error') ?? 'Erreur API.');

            return view('camions.index', [
                'camions' => new LengthAwarePaginator([], 0, 20),
                'chauffeurs' => collect(),
                'external_camions' => [],
                'etats_par_vehicule' => [],
                'vehicules_en_cours' => [],
                'external_error' => $message,
            ]);
        }

        $vehicules = $response->json('vehicules');
        if (! is_array($vehicules)) {
            $vehicules = [];
        }

        // Filtrer par recherche si un terme est fourni
        $search = $request->get('q');
        if ($search) {
            $search = strtolower(trim($search));
            $vehicules = array_filter($vehicules, function ($v) use ($search) {
                $matricule = strtolower($v['matricule_vehicule'] ?? '');
                return str_contains($matricule, $search);
            });
            $vehicules = array_values($vehicules); // Réindexer le tableau
        }

        $vehiculeIds = array_map(static fn ($v) => (int) ($v['vehicules_id'] ?? 0), $vehicules);
        $vehiculeIds = array_values(array_filter($vehiculeIds));
        $etatsParVehicule = [];
        foreach (array_chunk($vehiculeIds, 500) as $vehiculeIdsChunk) {
            $etatsParVehicule += CamionEtat::query()
                ->whereIn('vehicule_id', $vehiculeIdsChunk)
                ->pluck('etat', 'vehicule_id')
                ->toArray();
        }
        foreach ($etatsParVehicule as $vehiculeId => $etat) {
            if ($etat === 'inactif') {
                $etatsParVehicule[$vehiculeId] = 'en_panne';
            }
        }

        $vehiculesEnCours = [];
        foreach (array_chunk($vehiculeIds, 500) as $vehiculeIdsChunk) {
            $enCoursChunk = FicheSortie::query()
                ->whereIn('vehicule_id', $vehiculeIdsChunk)
                ->whereNull('date_dechargement')
                ->pluck('vehicule_id')
                ->map(static fn ($id) => (int) $id)
                ->toArray();

            foreach ($enCoursChunk as $vehiculeIdEnCours) {
                $vehiculesEnCours[$vehiculeIdEnCours] = true;
            }
        }

        // Filtrer par état (actif / en_panne / en_cours)
        $etatFiltre = (string) $request->query('etat', '');
        if (in_array($etatFiltre, ['actif', 'en_panne', 'en_cours'], true)) {
            $vehicules = array_filter($vehicules, function ($v) use ($etatFiltre, $vehiculesEnCours, $etatsParVehicule) {
                $vehiculeId = (int) ($v['vehicules_id'] ?? 0);
                $estEnCours = !empty($vehiculesEnCours[$vehiculeId]);
                $etat = $estEnCours ? 'en_cours' : ($etatsParVehicule[$vehiculeId] ?? 'actif');

                if ($etatFiltre === 'en_cours') {
                    return $etat === 'en_cours';
                }
                if ($etatFiltre === 'en_panne') {
                    return $etat === 'en_panne' || $etat === 'inactif';
                }

                // actif
                return $etat === 'actif';
            });
            $vehicules = array_values($vehicules);
        }

        return view('camions.index', [
            'camions' => new LengthAwarePaginator([], 0, 20),
            'chauffeurs' => collect(),
            'external_camions' => $vehicules,
            'etats_par_vehicule' => $etatsParVehicule,
            'vehicules_en_cours' => $vehiculesEnCours,
            'external_error' => null,
        ]);
    }

    public function updateVehiculeEtat(Request $request, int $vehiculeId)
    {
        $estEnCours = FicheSortie::query()
            ->where('vehicule_id', $vehiculeId)
            ->whereNull('date_dechargement')
            ->exists();

        if ($estEnCours) {
            return back()->with('error', "Etat verrouille : camion en cours d'utilisation.");
        }

        $validated = $request->validate([
            'etat' => ['required', 'in:actif,en_panne'],
            'matricule' => ['nullable', 'string', 'max:100'],
        ]);

        CamionEtat::updateOrCreate(
            ['vehicule_id' => $vehiculeId],
            [
                'matricule' => $validated['matricule'] ?? null,
                'etat' => $validated['etat'],
            ]
        );

        return back()->with('success', 'Etat du camion mis a jour.');
    }

    public function show(Request $request, Camion $camion)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'data' => $camion->load(['chauffeur']),
            ]);
        }

        return view('camions.profile', [
            'camion' => $camion->load(['chauffeur']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'immatriculation' => ['required', 'string', 'max:255', 'unique:camions,immatriculation'],
            'marque' => ['nullable', 'string', 'max:255'],
            'modele' => ['nullable', 'string', 'max:255'],
            'annee' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'chauffeur_id' => ['nullable', 'integer', 'exists:users,id'],
            'actif' => ['nullable', 'boolean'],
            'image_face' => ['nullable', 'image', 'max:5120'],
            'image_profil_gauche' => ['nullable', 'image', 'max:5120'],
            'image_profil_droit' => ['nullable', 'image', 'max:5120'],
            'image_arriere' => ['nullable', 'image', 'max:5120'],
        ]);

        foreach (['image_face', 'image_profil_gauche', 'image_profil_droit', 'image_arriere'] as $k) {
            unset($validated[$k]);
        }

        $validated['actif'] = (bool) ($validated['actif'] ?? false);

        $prefix = 'CAM-';
        $stamp = Carbon::now()->format('YmdHis');

        do {
            $candidate = $prefix . $stamp . '-' . random_int(100, 999);
        } while (Camion::query()->where('reference', $candidate)->exists());

        $validated['reference'] = $candidate;

        $camion = Camion::create($validated);

        $disk = Storage::disk('s3');

        $updates = [];

        if (empty($camion->image_face) && !$request->hasFile('image_face')) {
            $updates['image_face'] = 'camions/camions.png';
        }

        if ($request->hasFile('image_face')) {
            $file = $request->file('image_face');
            $path = $disk->putFileAs("camions/{$camion->id}", $file, 'face.' . $file->getClientOriginalExtension());
            $updates['image_face'] = $path;
        }

        if ($request->hasFile('image_profil_gauche')) {
            $file = $request->file('image_profil_gauche');
            $path = $disk->putFileAs("camions/{$camion->id}", $file, 'profil_gauche.' . $file->getClientOriginalExtension());
            $updates['image_profil_gauche'] = $path;
        }

        if ($request->hasFile('image_profil_droit')) {
            $file = $request->file('image_profil_droit');
            $path = $disk->putFileAs("camions/{$camion->id}", $file, 'profil_droit.' . $file->getClientOriginalExtension());
            $updates['image_profil_droit'] = $path;
        }

        if ($request->hasFile('image_arriere')) {
            $file = $request->file('image_arriere');
            $path = $disk->putFileAs("camions/{$camion->id}", $file, 'arriere.' . $file->getClientOriginalExtension());
            $updates['image_arriere'] = $path;
        }

        if (!empty($updates)) {
            $camion->update($updates);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $camion->load(['chauffeur']),
            ], 201);
        }

        return redirect()->back();
    }

    public function edit(Request $request, Camion $camion)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'data' => $camion->load(['chauffeur']),
            ]);
        }

        $chauffeurs = User::query()->where('role', 'driver')->orderBy('name')->get();

        return view('camions.edit', [
            'camion' => $camion,
            'chauffeurs' => $chauffeurs,
        ]);
    }

    public function update(Request $request, Camion $camion)
    {
        $validated = $request->validate([
            'immatriculation' => ['sometimes', 'required', 'string', 'max:255', 'unique:camions,immatriculation,' . $camion->id],
            'marque' => ['sometimes', 'nullable', 'string', 'max:255'],
            'modele' => ['sometimes', 'nullable', 'string', 'max:255'],
            'annee' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:2100'],
            'chauffeur_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'actif' => ['nullable', 'boolean'],
            'image_face' => ['nullable', 'image', 'max:5120'],
            'image_profil_gauche' => ['nullable', 'image', 'max:5120'],
            'image_profil_droit' => ['nullable', 'image', 'max:5120'],
            'image_arriere' => ['nullable', 'image', 'max:5120'],
        ]);

        foreach (['image_face', 'image_profil_gauche', 'image_profil_droit', 'image_arriere'] as $k) {
            unset($validated[$k]);
        }

        $validated['actif'] = (bool) ($request->boolean('actif'));

        if (empty($camion->reference)) {
            $prefix = 'CAM-';
            $stamp = Carbon::now()->format('YmdHis');

            do {
                $candidate = $prefix . $stamp . '-' . random_int(100, 999);
            } while (Camion::query()->where('reference', $candidate)->exists());

            $validated['reference'] = $candidate;
        }

        $disk = Storage::disk('s3');

        if ($request->hasFile('image_face')) {
            if (!empty($camion->image_face)) {
                $disk->delete($camion->image_face);
            }
            $file = $request->file('image_face');
            $validated['image_face'] = $disk->putFileAs("camions/{$camion->id}", $file, 'face.' . $file->getClientOriginalExtension());
        }

        if ($request->hasFile('image_profil_gauche')) {
            if (!empty($camion->image_profil_gauche)) {
                $disk->delete($camion->image_profil_gauche);
            }
            $file = $request->file('image_profil_gauche');
            $validated['image_profil_gauche'] = $disk->putFileAs("camions/{$camion->id}", $file, 'profil_gauche.' . $file->getClientOriginalExtension());
        }

        if ($request->hasFile('image_profil_droit')) {
            if (!empty($camion->image_profil_droit)) {
                $disk->delete($camion->image_profil_droit);
            }
            $file = $request->file('image_profil_droit');
            $validated['image_profil_droit'] = $disk->putFileAs("camions/{$camion->id}", $file, 'profil_droit.' . $file->getClientOriginalExtension());
        }

        if ($request->hasFile('image_arriere')) {
            if (!empty($camion->image_arriere)) {
                $disk->delete($camion->image_arriere);
            }
            $file = $request->file('image_arriere');
            $validated['image_arriere'] = $disk->putFileAs("camions/{$camion->id}", $file, 'arriere.' . $file->getClientOriginalExtension());
        }

        $camion->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $camion->refresh()->load(['chauffeur']),
            ]);
        }

        return redirect()->back();
    }

    public function destroy(Camion $camion)
    {
        $camion->delete();

        if (request()->wantsJson()) {
            return response()->json(null, 204);
        }

        return redirect()->back();
    }

    public function camionsPgf(Request $request)
    {
        $vehicules = $this->fetchVehiculesApi();

        // Récupérer le groupe PGF (ou le créer s'il n'existe pas)
        $groupePgf = Groupe::firstOrCreate(['nom_groupe' => 'PGF']);

        // Récupérer les véhicules assignés au groupe PGF
        $vehiculesGroupePgf = GroupeVehicule::where('groupe_id', $groupePgf->id)->pluck('vehicule_id')->toArray();

        // Filtrer les véhicules qui appartiennent au groupe PGF
        $camionsPgf = array_filter($vehicules, function ($v) use ($vehiculesGroupePgf) {
            return in_array($v['vehicules_id'] ?? 0, $vehiculesGroupePgf);
        });
        $camionsPgf = array_values($camionsPgf);

        // Filtrer par recherche si présente
        if ($request->filled('q')) {
            $q = strtolower($request->string('q')->toString());
            $camionsPgf = array_filter($camionsPgf, function ($v) use ($q) {
                $matricule = strtolower($v['matricule_vehicule'] ?? '');
                $type = strtolower($v['type_vehicule'] ?? '');
                return str_contains($matricule, $q) || str_contains($type, $q);
            });
            $camionsPgf = array_values($camionsPgf);
        }

        $vehiculeIds = array_map(static fn ($v) => (int) ($v['vehicules_id'] ?? 0), $camionsPgf);
        $vehiculeIds = array_values(array_filter($vehiculeIds));

        $etatsParVehicule = [];
        foreach (array_chunk($vehiculeIds, 500) as $vehiculeIdsChunk) {
            $etatsParVehicule += CamionEtat::query()
                ->whereIn('vehicule_id', $vehiculeIdsChunk)
                ->pluck('etat', 'vehicule_id')
                ->toArray();
        }
        foreach ($etatsParVehicule as $vehiculeId => $etat) {
            if ($etat === 'inactif') {
                $etatsParVehicule[$vehiculeId] = 'en_panne';
            }
        }

        $vehiculesEnCours = [];
        foreach (array_chunk($vehiculeIds, 500) as $vehiculeIdsChunk) {
            $enCoursChunk = FicheSortie::query()
                ->whereIn('vehicule_id', $vehiculeIdsChunk)
                ->whereNull('date_dechargement')
                ->pluck('vehicule_id')
                ->map(static fn ($id) => (int) $id)
                ->toArray();

            foreach ($enCoursChunk as $vehiculeIdEnCours) {
                $vehiculesEnCours[$vehiculeIdEnCours] = true;
            }
        }

        return view('camions.camions_pgf', [
            'camions_pgf' => $camionsPgf,
            'groupe_pgf' => $groupePgf,
            'etats_par_vehicule' => $etatsParVehicule,
            'vehicules_en_cours' => $vehiculesEnCours,
        ]);
    }

    public function ajouterCamionsPgf(Request $request)
    {
        $vehicules = $this->fetchVehiculesApi();
        $groupePgf = Groupe::firstOrCreate(['nom_groupe' => 'PGF']);
        $vehiculesGroupePgf = GroupeVehicule::where('groupe_id', $groupePgf->id)
            ->pluck('vehicule_id')
            ->map(static fn ($id) => (int) $id)
            ->toArray();

        $vehiculesDisponibles = array_values(array_filter($vehicules, function ($v) use ($vehiculesGroupePgf) {
            return !in_array((int) ($v['vehicules_id'] ?? 0), $vehiculesGroupePgf, true);
        }));

        if ($request->filled('q')) {
            $q = strtolower($request->string('q')->toString());
            $vehiculesDisponibles = array_values(array_filter($vehiculesDisponibles, function ($v) use ($q) {
                $matricule = strtolower($v['matricule_vehicule'] ?? '');
                $type = strtolower($v['type_vehicule'] ?? '');

                return str_contains($matricule, $q) || str_contains($type, $q);
            }));
        }

        return view('camions.camions_pgf_ajouter', [
            'vehicules_disponibles' => $vehiculesDisponibles,
            'groupe_pgf' => $groupePgf,
            'total_disponibles' => count($vehiculesDisponibles),
        ]);
    }

    public function assignerGroupeBulk(Request $request)
    {
        $validated = $request->validate([
            'vehicule_ids' => ['required', 'array', 'min:1'],
            'vehicule_ids.*' => ['integer'],
            'matricules' => ['required', 'array'],
            'groupe_id' => ['required', 'integer', 'exists:groupes,id'],
        ], [
            'vehicule_ids.required' => 'Sélectionnez au moins un camion.',
            'vehicule_ids.min' => 'Sélectionnez au moins un camion.',
        ]);

        $count = 0;
        foreach ($validated['vehicule_ids'] as $vehiculeId) {
            $vehiculeId = (int) $vehiculeId;
            $matricule = trim((string) ($validated['matricules'][$vehiculeId] ?? ''));
            if ($vehiculeId <= 0 || $matricule === '') {
                continue;
            }

            GroupeVehicule::updateOrCreate(
                [
                    'vehicule_id' => $vehiculeId,
                    'groupe_id' => (int) $validated['groupe_id'],
                ],
                [
                    'matricule_vehicule' => $matricule,
                ]
            );

            TransporteurVehicule::query()->where('vehicule_id', $vehiculeId)->delete();
            $count++;
        }

        if ($count === 0) {
            return back()->with('error', 'Aucun camion valide n\'a pu être ajouté.');
        }

        $message = $count === 1
            ? '1 camion ajouté au groupe PGF avec succès.'
            : "{$count} camions ajoutés au groupe PGF avec succès.";

        return redirect()->route('camions.camions_pgf')->with('success', $message);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchVehiculesApi(): array
    {
        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout(10)
                ->get('https://api.objetombrepegasus.online/api/camions/mes_camions.php');
            if ($response->successful()) {
                return $response->json('vehicules') ?? [];
            }
        } catch (\Throwable $e) {
        }

        return [];
    }

    public function assignerGroupe(Request $request)
    {
        $validated = $request->validate([
            'vehicule_id' => ['required', 'integer'],
            'matricule_vehicule' => ['required', 'string', 'max:50'],
            'groupe_id' => ['required', 'integer', 'exists:groupes,id'],
        ]);

        GroupeVehicule::updateOrCreate(
            [
                'vehicule_id' => $validated['vehicule_id'],
                'groupe_id' => $validated['groupe_id'],
            ],
            [
                'matricule_vehicule' => $validated['matricule_vehicule'],
            ]
        );

        return back()->with('success', 'Véhicule assigné au groupe avec succès.');
    }

    public function retirerGroupe(Request $request, int $vehiculeId)
    {
        $groupeId = $request->input('groupe_id');

        GroupeVehicule::where('vehicule_id', $vehiculeId)
            ->where('groupe_id', $groupeId)
            ->delete();

        return back()->with('success', 'Véhicule retiré du groupe avec succès.');
    }

    /**
     * Revenus des camions du groupe PGF (vue type montant transporteur).
     */
    public function revenuesPgf(Request $request)
    {
        $tickets = $this->ticketsDepuisActivitesPgf($request);
        $bordereaux = BordereauPgf::query()->orderByDesc('id')->get();
        $montants = $this->calculerMontantsDepuisTicketsPgf($tickets, $bordereaux);

        $data = [[
            'nom' => 'PGF',
            'prenoms' => '',
            'code' => 'PGF',
            'camions_count' => $this->vehiculesPgf()->count(),
            'montant_du' => $montants['montant_du'],
            'montant_paye' => $montants['montant_paye'],
            'reste_a_payer' => $montants['reste_a_payer'],
        ]];

        return view('camions.revenues_pgf', [
            'data' => $data,
        ]);
    }

    /**
     * Situation financière détaillée des camions PGF.
     */
    public function revenuesPgfShow(Request $request)
    {
        $filtres = [
            'vehicule' => trim((string) $request->query('vehicule', '')),
            'date_debut' => $request->query('date_debut'),
            'date_fin' => $request->query('date_fin'),
        ];

        $vehiculesPgf = $this->vehiculesPgf();
        $ticketsDetails = collect($this->ticketsDepuisActivitesPgf($request, $filtres));
        $bordereaux = BordereauPgf::query()->orderByDesc('id')->get();
        $montants = $this->calculerMontantsDepuisTicketsPgf($ticketsDetails->all(), $bordereaux);

        return view('camions.revenues_pgf_show', [
            'montantDu' => $montants['montant_du'],
            'montantPaye' => $montants['montant_paye'],
            'resteAPayer' => $montants['reste_a_payer'],
            'camionsCount' => $vehiculesPgf->count(),
            'vehiculesPgf' => $vehiculesPgf,
            'tickets' => $ticketsDetails,
            'filtres' => $filtres,
            'bordereaux' => $bordereaux,
            'exempleNumeroBordereau' => $this->bordereauPgf->exempleNumero(),
            'soldeCaisseLocale' => (int) round($this->caisseService->getSolde()),
        ]);
    }

    public function fichesEligiblesBordereauPgf(Request $request)
    {
        $validated = $request->validate([
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
        ]);

        $tickets = $this->ticketsDepuisActivitesPgf($request, [
            'date_debut' => $validated['date_debut'],
            'date_fin' => $validated['date_fin'],
        ]);
        $fiches = $this->bordereauPgf->lignesEligiblesDepuisTickets($tickets);

        return response()->json([
            'fiches' => $fiches,
            'total_montant' => (int) collect($fiches)->sum('montant'),
            'total_poids' => (float) collect($fiches)->sum('poids'),
        ]);
    }

    public function storeBordereauPgf(Request $request)
    {
        $validated = $request->validate([
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'ticket_ids' => ['required', 'array', 'min:1'],
            'ticket_ids.*' => ['integer'],
        ]);

        $tickets = $this->ticketsDepuisActivitesPgf($request, [
            'date_debut' => $validated['date_debut'],
            'date_fin' => $validated['date_fin'],
        ]);
        $lignesData = $this->bordereauPgf->construireLignesData($validated['ticket_ids'], $tickets);

        if ($lignesData === []) {
            return back()->withErrors(['error' => 'Aucun ticket valide sélectionné (déjà bordereau ou introuvable).']);
        }

        $bordereau = BordereauPgf::create([
            'numero' => $this->bordereauPgf->genererNumero(),
            'libelle' => 'PGF',
            'date_generation' => now()->toDateString(),
            'date_debut' => $validated['date_debut'],
            'date_fin' => $validated['date_fin'],
            'montant_total' => collect($lignesData)->sum('montant'),
            'montant_paye' => 0,
            'poids_total' => collect($lignesData)->sum('poids'),
            'fiches_data' => $lignesData,
        ]);

        $this->bordereauPgf->assignerLignesAuBordereau($bordereau, $lignesData);

        return redirect()
            ->route('camions.revenues.bordereau.show', ['id' => $bordereau->id])
            ->with('success', 'Bordereau '.$bordereau->numero.' généré avec succès.');
    }

    public function showBordereauPgf(int $id)
    {
        $bordereau = BordereauPgf::query()->findOrFail($id);
        $groupesUsine = $this->bordereauPgf->grouperParUsine($bordereau->fiches_data ?? []);

        return view('camions.bordereau_pgf_show', [
            'bordereau' => $bordereau,
            'groupesUsine' => $groupesUsine,
        ]);
    }

    public function exportBordereauPgfPdf(int $id)
    {
        $bordereau = BordereauPgf::query()->findOrFail($id);
        $groupesUsine = $this->bordereauPgf->grouperParUsine($bordereau->fiches_data ?? []);

        $logoPath = public_path('img/logo/logo.png');
        if (! file_exists($logoPath)) {
            $logoPath = null;
        }

        $pdf = Pdf::loadView('camions.bordereau_pgf_pdf', [
            'bordereau' => $bordereau,
            'groupesUsine' => $groupesUsine,
            'logoPath' => $logoPath,
            'dateCreation' => ($bordereau->created_at ?? now())->format('d/m/Y \à H:i'),
        ]);

        $pdf->setPaper('A4', 'portrait');
        $filename = 'bordereau_'.preg_replace('/[^a-zA-Z0-9_-]+/', '_', $bordereau->numero).'.pdf';

        return $pdf->stream($filename);
    }

    public function destroyBordereauPgf(int $id)
    {
        $bordereau = BordereauPgf::query()->findOrFail($id);
        $this->bordereauPgf->libererTicketsDuBordereau($bordereau);
        $bordereau->paiements()->delete();
        $bordereau->delete();

        return redirect()
            ->route('camions.revenues.show')
            ->with('success', 'Bordereau supprimé.');
    }

    public function storePaiementBordereauPgf(Request $request, int $id)
    {
        $bordereau = BordereauPgf::query()->findOrFail($id);

        $request->merge([
            'montant' => preg_replace('/\s+/u', '', (string) $request->input('montant', '')),
        ]);

        $validated = $request->validate([
            'montant' => ['required', 'integer', 'min:1'],
            'date_paiement' => ['required', 'date'],
            'mode_paiement' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ]);

        $montant = (int) $validated['montant'];
        $soldeCaisseLocale = (int) round($this->caisseService->getSolde());
        $resteBordereau = (int) round($bordereau->reste_a_payer);
        $plafond = $resteBordereau > 0
            ? min($resteBordereau, max(0, $soldeCaisseLocale))
            : max(0, $soldeCaisseLocale);

        if ($plafond <= 0) {
            $message = 'Solde de la caisse locale insuffisant pour effectuer ce paiement.';

            return back()
                ->withErrors(['montant' => $message])
                ->with('error', $message)
                ->withInput();
        }

        if ($montant > $plafond) {
            $message = 'Le paiement ne peut pas dépasser le solde de la caisse locale ('
                .number_format($soldeCaisseLocale, 0, ',', ' ')
                .' FCFA). Maximum autorisé : '
                .number_format($plafond, 0, ',', ' ')
                .' FCFA.';

            return back()
                ->withErrors(['montant' => $message])
                ->with('error', $message)
                ->withInput();
        }

        $user = $this->resolveOptionalUser($request);

        try {
            DB::transaction(function () use ($validated, $montant, $bordereau, $user) {
                PaiementPgf::create([
                    'id_bordereau' => $bordereau->id,
                    'montant' => $montant,
                    'date_paiement' => $validated['date_paiement'],
                    'mode_paiement' => $validated['mode_paiement'] ?: 'Caisse locale',
                    'caisse' => 'local',
                    'reference' => $validated['reference'] ?? null,
                    'commentaire' => $validated['commentaire'] ?? null,
                ]);

                $bordereau->update([
                    'montant_paye' => (float) $bordereau->montant_paye + $montant,
                ]);

                $this->caisseService->debiter(
                    $montant,
                    'Paiement bordereau '.$bordereau->numero,
                    $user,
                    'Local',
                );
            });
        } catch (\InvalidArgumentException $e) {
            return back()
                ->withErrors(['montant' => $e->getMessage()])
                ->with('error', $e->getMessage())
                ->withInput();
        }

        return back()->with(
            'success',
            'Paiement de '.number_format($montant, 0, ',', ' ')
            .' FCFA enregistré pour le bordereau '.$bordereau->numero.'. Débité de la caisse locale.'
        );
    }

    private function resolveOptionalUser(Request $request): ?User
    {
        $user = $request->user();
        if ($user instanceof User) {
            return $user;
        }

        try {
            $chef = app(ChefEquipeSession::class)->chef($request);
        } catch (\Throwable) {
            return null;
        }

        if (! $chef) {
            return null;
        }

        $idChef = (int) ($chef['id_chef'] ?? 0);
        $login = trim((string) ($chef['login'] ?? ''));

        $userQuery = User::query();
        if ($idChef > 0) {
            $userQuery->where('id_chef', $idChef);
        }
        if ($login !== '') {
            $userQuery->when(
                $idChef > 0,
                fn ($q) => $q->orWhere('login', $login),
                fn ($q) => $q->where('login', $login),
            );
        }

        return $userQuery->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, GroupeVehicule>
     */
    private function vehiculesPgf()
    {
        $groupeIds = Groupe::query()
            ->where('nom_groupe', 'like', '%PGF%')
            ->pluck('id');

        return GroupeVehicule::query()
            ->whereIn('groupe_id', $groupeIds)
            ->orderBy('matricule_vehicule')
            ->get();
    }

    /**
     * Même source que camions-pgf/activites : tickets API filtrés camions PGF.
     *
     * @param  array{vehicule?: string, date_debut?: string|null, date_fin?: string|null}  $filtres
     * @return list<array<string, mixed>>
     */
    private function ticketsDepuisActivitesPgf(Request $request, array $filtres = []): array
    {
        $mesTickets = app(MesTicketsService::class);
        try {
            $allTickets = $mesTickets->fetchAllTickets([], $request);
        } catch (\Throwable $e) {
            return [];
        }

        $vehiculeFiltre = trim((string) ($filtres['vehicule'] ?? ''));
        $filtered = $mesTickets->filterTickets($allTickets, $vehiculeFiltre, '', '');

        $vehiculesPgf = $this->vehiculesPgf();
        $idsPgf = [];
        $matriculesPgf = [];
        foreach ($vehiculesPgf as $row) {
            $id = (int) $row->vehicule_id;
            if ($id > 0) {
                $idsPgf[$id] = true;
            }
            $matricule = strtoupper(trim((string) $row->matricule_vehicule));
            if ($matricule !== '') {
                $matriculesPgf[$matricule] = true;
            }
        }

        $filtered = array_values(array_filter(
            $filtered,
            function (array $ticket) use ($idsPgf, $matriculesPgf): bool {
                $id = (int) ($ticket['vehicule_id'] ?? 0);
                $matricule = strtoupper(trim((string) ($ticket['matricule_vehicule'] ?? $ticket['matricule'] ?? '')));

                return ($id > 0 && isset($idsPgf[$id]))
                    || ($matricule !== '' && isset($matriculesPgf[$matricule]));
            }
        ));

        $dateDebut = trim((string) ($filtres['date_debut'] ?? ''));
        $dateFin = trim((string) ($filtres['date_fin'] ?? ''));
        if ($dateDebut !== '' || $dateFin !== '') {
            $filtered = array_values(array_filter(
                $filtered,
                function (array $ticket) use ($dateDebut, $dateFin): bool {
                    $raw = (string) ($ticket['date_ticket'] ?? '');
                    if ($raw === '') {
                        return false;
                    }
                    try {
                        $date = Carbon::parse($raw)->startOfDay();
                    } catch (\Throwable $e) {
                        return false;
                    }
                    if ($dateDebut !== '') {
                        try {
                            if ($date->lt(Carbon::parse($dateDebut)->startOfDay())) {
                                return false;
                            }
                        } catch (\Throwable $e) {
                        }
                    }
                    if ($dateFin !== '') {
                        try {
                            if ($date->gt(Carbon::parse($dateFin)->startOfDay())) {
                                return false;
                            }
                        } catch (\Throwable $e) {
                        }
                    }

                    return true;
                }
            ));
        }

        usort($filtered, function (array $a, array $b): int {
            return strcmp((string) ($b['date_ticket'] ?? ''), (string) ($a['date_ticket'] ?? ''));
        });

        $ids = array_values(array_filter(array_map(
            static fn (array $t) => (int) ($t['id_ticket'] ?? 0),
            $filtered
        )));
        $numeros = array_values(array_filter(array_map(
            static fn (array $t) => trim((string) ($t['numero_ticket'] ?? '')),
            $filtered
        )));

        $locaux = collect();
        if ($ids !== [] || $numeros !== []) {
            $locaux = Ticket::query()
                ->where(function ($query) use ($ids, $numeros) {
                    if ($ids !== []) {
                        $query->whereIn('id_ticket', $ids);
                    }
                    if ($numeros !== []) {
                        if ($ids !== []) {
                            $query->orWhereIn('numero_ticket', $numeros);
                        } else {
                            $query->whereIn('numero_ticket', $numeros);
                        }
                    }
                })
                ->get();
        }
        $byId = $locaux->keyBy(fn ($t) => (int) $t->id_ticket);
        $byNumero = $locaux->keyBy(fn ($t) => trim((string) $t->numero_ticket));

        $usinesById = Usine::query()
            ->get(['id_usine', 'nom_usine'])
            ->pluck('nom_usine', 'id_usine')
            ->all();

        $fichesByTicketId = FicheSortie::query()
            ->whereIn('id_ticket', $ids)
            ->get()
            ->keyBy('id_ticket');

        $bordereauIds = $locaux
            ->pluck('bordereau_pgf_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $numerosBordereau = $bordereauIds === []
            ? collect()
            : BordereauPgf::query()
                ->whereIn('id', $bordereauIds)
                ->pluck('numero', 'id');

        $details = [];
        foreach ($filtered as $ticket) {
            $idTicket = (int) ($ticket['id_ticket'] ?? 0);
            $numero = trim((string) ($ticket['numero_ticket'] ?? ''));
            $local = $byId->get($idTicket) ?? ($numero !== '' ? $byNumero->get($numero) : null);
            $fiche = $fichesByTicketId->get($idTicket);

            $poids = (float) ($ticket['poids'] ?? 0);
            $prixManuel = ($local && $local->prix_saisi_manuel)
                ? (float) $local->prix_unitaire
                : null;
            $montantManuel = ($local && $local->prix_saisi_manuel && $local->montant_paie !== null)
                ? (float) $local->montant_paie
                : null;
            if ($montantManuel === null && $prixManuel !== null && $poids > 0) {
                $montantManuel = $prixManuel * $poids;
            }

            $bordereauPgfId = $local?->bordereau_pgf_id ? (int) $local->bordereau_pgf_id : null;
            $numeroBordereau = $bordereauPgfId
                ? (string) ($numerosBordereau[$bordereauPgfId] ?? '')
                : '';

            $nomUsine = trim((string) ($ticket['nom_usine'] ?? ''));
            if ($nomUsine === '' || $nomUsine === '-') {
                $nomUsine = (string) ($usinesById[(int) ($ticket['id_usine'] ?? 0)] ?? '—');
            }

            $dateTicket = null;
            $rawDate = (string) ($ticket['date_ticket'] ?? '');
            if ($rawDate !== '') {
                try {
                    $dateTicket = Carbon::parse($rawDate);
                } catch (\Throwable $e) {
                    $dateTicket = null;
                }
            }

            $details[] = [
                'id_ticket' => $idTicket,
                'date_ticket' => $dateTicket,
                'numero_ticket' => $numero,
                'nom_usine' => $nomUsine !== '' ? $nomUsine : '—',
                'nom_agent' => trim((string) ($ticket['nom_agent'] ?? $fiche?->nom_agent ?? '')) ?: '—',
                'nom_pont' => trim((string) ($ticket['nom_pont'] ?? $fiche?->nom_pont ?? $ticket['origine'] ?? '')) ?: '—',
                'vehicule_id' => (int) ($ticket['vehicule_id'] ?? 0),
                'matricule_vehicule' => trim((string) ($ticket['matricule_vehicule'] ?? '')) ?: '—',
                'poids' => $poids,
                'prix_unitaire' => $prixManuel,
                'montant' => $montantManuel,
                'bordereau_pgf_id' => $bordereauPgfId,
                'numero_bordereau' => $numeroBordereau !== '' ? $numeroBordereau : null,
            ];
        }

        return $details;
    }

    /**
     * Montant dû = tickets avec PU ; montant payé = paiements bordereaux PGF.
     *
     * @param  list<array<string, mixed>>  $tickets
     * @param  \Illuminate\Support\Collection<int, BordereauPgf>|null  $bordereaux
     * @return array{montant_du: int, montant_paye: int, reste_a_payer: int}
     */
    private function calculerMontantsDepuisTicketsPgf(array $tickets, $bordereaux = null): array
    {
        $montantDu = 0.0;

        foreach ($tickets as $ticket) {
            $montant = $ticket['montant'] ?? null;
            if ($montant === null || (float) $montant <= 0) {
                continue;
            }
            $montantDu += (float) $montant;
        }

        $bordereaux = $bordereaux ?? BordereauPgf::query()->get();
        $montantPaye = (float) $bordereaux->sum('montant_paye');

        return [
            'montant_du' => (int) round($montantDu),
            'montant_paye' => (int) round($montantPaye),
            'reste_a_payer' => (int) round($montantDu - $montantPaye),
        ];
    }
}
