<?php

namespace App\Services;

use App\Models\Chauffeur;
use App\Models\ChauffeurGroupe;
use App\Models\ChauffeurSalaireAvance;
use App\Models\ChauffeurSalairePaiement;
use App\Models\ChauffeurSalairePaiementPeriode;
use App\Models\ChauffeurSalairePeriode;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChauffeurSalaireService
{
    public const GROUPE_PGF = 'Chauffeurs PGF';

    public function groupePgf(): ?ChauffeurGroupe
    {
        return ChauffeurGroupe::where('nom_groupe', self::GROUPE_PGF)->first();
    }

    public function chauffeursPgf(): Collection
    {
        $groupe = $this->groupePgf();
        if (!$groupe) {
            return collect();
        }

        return Chauffeur::where('chauffeur_groupe_id', $groupe->id)
            ->orderBy('nom')
            ->orderBy('prenoms')
            ->get();
    }

    public function assertChauffeurPgf(Chauffeur $chauffeur): void
    {
        $groupe = $this->groupePgf();
        if (!$groupe || (int) $chauffeur->chauffeur_groupe_id !== (int) $groupe->id) {
            abort(404);
        }
    }

    public function libelleMois(int $mois, int $annee): string
    {
        return Carbon::createFromDate($annee, $mois, 1)->locale('fr')->translatedFormat('F Y');
    }

    public function ensurePeriode(Chauffeur $chauffeur, int $annee, int $mois): ChauffeurSalairePeriode
    {
        $periode = ChauffeurSalairePeriode::firstOrCreate(
            [
                'chauffeur_id' => $chauffeur->id,
                'annee' => $annee,
                'mois' => $mois,
            ],
            [
                'montant_salaire' => $chauffeur->salaire ?? 0,
            ]
        );

        if ((float) $periode->montant_salaire <= 0 && (float) $chauffeur->salaire > 0) {
            $periode->update(['montant_salaire' => $chauffeur->salaire]);
        }

        return $periode->fresh();
    }

    public function ensurePeriodesMois(int $annee, int $mois): void
    {
        foreach ($this->chauffeursPgf() as $chauffeur) {
            $this->ensurePeriode($chauffeur, $annee, $mois);
        }
    }

    /**
     * Soldes bruts d'une période (sans report des avances excédentaires).
     *
     * @return array{du: float, avances: float, paye: float, reste: float}
     */
    public function calculPeriodeBrut(ChauffeurSalairePeriode $periode): array
    {
        $du = (float) $periode->montant_salaire;
        $avances = (float) ChauffeurSalaireAvance::where('chauffeur_salaire_periode_id', $periode->id)->sum('montant');
        $paye = (float) ChauffeurSalairePaiementPeriode::where('chauffeur_salaire_periode_id', $periode->id)->sum('montant');
        $reste = max(0, $du - $avances - $paye);

        return compact('du', 'avances', 'paye', 'reste');
    }

    /**
     * Soldes mois par mois : une avance supérieure au salaire du mois est reportée sur le(s) mois suivant(s).
     *
     * @return Collection<int, array{
     *   periode: ChauffeurSalairePeriode,
     *   du: float,
     *   avances: float,
     *   avances_reportees: float,
     *   paye: float,
     *   reste: float,
     *   libelle: string
     * }>
     */
    public function soldesChronologiques(Chauffeur $chauffeur): Collection
    {
        $excedentReport = 0.0;

        return ChauffeurSalairePeriode::where('chauffeur_id', $chauffeur->id)
            ->orderBy('annee')
            ->orderBy('mois')
            ->get()
            ->map(function (ChauffeurSalairePeriode $periode) use (&$excedentReport) {
                $brut = $this->calculPeriodeBrut($periode);
                $avancesReportees = $excedentReport;
                $totalImpute = $avancesReportees + $brut['avances'] + $brut['paye'];
                $reste = max(0, $brut['du'] - $totalImpute);
                $excedentReport = max(0, $totalImpute - $brut['du']);

                return [
                    'periode' => $periode,
                    'du' => $brut['du'],
                    'avances' => $brut['avances'],
                    'avances_reportees' => $avancesReportees,
                    'paye' => $brut['paye'],
                    'reste' => $reste,
                    'libelle' => $this->libelleMois((int) $periode->mois, (int) $periode->annee),
                ];
            });
    }

    /**
     * @return array{du: float, avances: float, avances_reportees: float, paye: float, reste: float, libelle: string, periode: ChauffeurSalairePeriode}|null
     */
    public function soldesPourPeriode(Chauffeur $chauffeur, ChauffeurSalairePeriode $periode): ?array
    {
        return $this->soldesChronologiques($chauffeur)
            ->first(fn (array $row) => (int) $row['periode']->id === (int) $periode->id);
    }

    /**
     * @return array{du: float, avances: float, avances_reportees: float, paye: float, reste: float}
     */
    public function calculPeriode(Chauffeur $chauffeur, ChauffeurSalairePeriode $periode): array
    {
        $soldes = $this->soldesPourPeriode($chauffeur, $periode);

        if (!$soldes) {
            return array_merge($this->calculPeriodeBrut($periode), ['avances_reportees' => 0.0]);
        }

        return [
            'du' => $soldes['du'],
            'avances' => $soldes['avances'],
            'avances_reportees' => $soldes['avances_reportees'],
            'paye' => $soldes['paye'],
            'reste' => $soldes['reste'],
        ];
    }

    /**
     * @return Collection<int, array{periode: ChauffeurSalairePeriode, du: float, avances: float, avances_reportees: float, paye: float, reste: float, libelle: string}>
     */
    public function periodesAvecSoldes(Chauffeur $chauffeur): Collection
    {
        return $this->soldesChronologiques($chauffeur);
    }

    /**
     * @return Collection<int, array{periode: ChauffeurSalairePeriode, reste: float, libelle: string}>
     */
    public function periodesImpayees(Chauffeur $chauffeur): Collection
    {
        return $this->periodesAvecSoldes($chauffeur)
            ->filter(fn (array $row) => $row['reste'] > 0)
            ->values();
    }

    public function enregistrerAvance(
        Chauffeur $chauffeur,
        int $annee,
        int $mois,
        float $montant,
        string $dateAvance,
        ?string $libelle = null
    ): ChauffeurSalaireAvance {
        $periode = $this->ensurePeriode($chauffeur, $annee, $mois);

        return ChauffeurSalaireAvance::create([
            'chauffeur_id' => $chauffeur->id,
            'chauffeur_salaire_periode_id' => $periode->id,
            'date_avance' => $dateAvance,
            'montant' => $montant,
            'libelle' => $libelle ?: 'Avance sur salaire',
        ]);
    }

    /**
     * @param  array<int>  $periodeIds
     */
    public function enregistrerPaiement(
        Chauffeur $chauffeur,
        array $periodeIds,
        string $datePaiement,
        ?string $libelle = null,
        ?string $commentaire = null
    ): ChauffeurSalairePaiement {
        $periodeIds = array_values(array_unique(array_map('intval', $periodeIds)));

        return DB::transaction(function () use ($chauffeur, $periodeIds, $datePaiement, $libelle, $commentaire) {
            $lignes = [];
            $montantTotal = 0.0;

            $soldesParPeriode = $this->soldesChronologiques($chauffeur)->keyBy(
                fn (array $row) => $row['periode']->id
            );

            foreach ($periodeIds as $periodeId) {
                $periode = ChauffeurSalairePeriode::where('chauffeur_id', $chauffeur->id)
                    ->where('id', $periodeId)
                    ->firstOrFail();

                $reste = (float) ($soldesParPeriode->get($periode->id)['reste'] ?? 0);
                if ($reste <= 0) {
                    continue;
                }

                $lignes[] = ['periode' => $periode, 'montant' => $reste];
                $montantTotal += $reste;
            }

            if ($montantTotal <= 0 || empty($lignes)) {
                throw new \InvalidArgumentException('Aucun mois sélectionné avec un reste à payer.');
            }

            if (!$libelle) {
                $moisLabels = collect($lignes)->map(
                    fn ($l) => $this->libelleMois((int) $l['periode']->mois, (int) $l['periode']->annee)
                )->implode(' + ');
                $libelle = 'Salaire ' . $moisLabels;
            }

            $paiement = ChauffeurSalairePaiement::create([
                'chauffeur_id' => $chauffeur->id,
                'date_paiement' => $datePaiement,
                'montant_total' => $montantTotal,
                'libelle' => $libelle,
                'commentaire' => $commentaire,
            ]);

            foreach ($lignes as $ligne) {
                ChauffeurSalairePaiementPeriode::create([
                    'chauffeur_salaire_paiement_id' => $paiement->id,
                    'chauffeur_salaire_periode_id' => $ligne['periode']->id,
                    'montant' => $ligne['montant'],
                ]);
            }

            return $paiement->load('periodes.periode');
        });
    }
}
