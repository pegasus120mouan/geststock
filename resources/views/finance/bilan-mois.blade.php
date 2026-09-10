@extends('layout.main')

@section('title', 'Bilan du mois')

@section('content')
@php
  $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
@endphp

<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <h4 class="mb-1">Bilan du mois</h4>
        <p class="mb-0 text-muted">
          {{ $moisOptions[$mois] }} {{ $annee }}
          ({{ $debut->format('d/m/Y') }} → {{ $fin->format('d/m/Y') }})
        </p>
      </div>
    </div>

    <form method="GET" action="{{ route('finance.bilan-mois') }}" class="card mb-4">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Mois</label>
            <select name="mois" class="form-select">
              @foreach ($moisOptions as $num => $label)
                <option value="{{ $num }}" @selected($mois === $num)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Année</label>
            <select name="annee" class="form-select">
              @foreach ($annees as $a)
                <option value="{{ $a }}" @selected($annee === $a)>{{ $a }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <button class="btn btn-primary w-100" type="submit">Afficher</button>
          </div>
        </div>
      </div>
    </form>

    <div class="row g-4 mb-4">
      <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
          <div class="card-body">
            <span class="d-block text-muted mb-1">Chiffre d’affaires</span>
            <h4 class="mb-0 text-primary">{{ $fmt($caTotal) }} FCFA</h4>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
          <div class="card-body">
            <span class="d-block text-muted mb-1">CA livré</span>
            <h4 class="mb-0 text-success">{{ $fmt($caLivre) }} FCFA</h4>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
          <div class="card-body">
            <span class="d-block text-muted mb-1">Commandes</span>
            <h4 class="mb-0">{{ $fmt($nbCommandes) }}</h4>
            <small class="text-muted">{{ $nbLivrees }} livrée(s) · {{ $nbEnAttente }} en attente</small>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
          <div class="card-body">
            <span class="d-block text-muted mb-1">Parfums vendus</span>
            <h4 class="mb-0">{{ $fmt($parParfum->count()) }}</h4>
            <small class="text-muted">références distinctes</small>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-lg-5">
        <div class="card h-100">
          <div class="card-header">
            <h5 class="mb-0">Répartition par parfum</h5>
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th>Parfum</th>
                  <th>Qté</th>
                  <th>Montant</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($parParfum as $ligne)
                  <tr>
                    <td class="fw-medium">{{ $ligne->produit }}</td>
                    <td>{{ $fmt($ligne->quantite) }}</td>
                    <td class="text-primary fw-semibold">{{ $fmt($ligne->montant) }} FCFA</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="3" class="text-center text-muted py-4">Aucune vente ce mois-ci.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="card h-100">
          <div class="card-header">
            <h5 class="mb-0">Détail des commandes</h5>
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Réf.</th>
                  <th>Parfum</th>
                  <th>Statut</th>
                  <th>Montant</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($commandes as $commande)
                  <tr>
                    <td>{{ $commande->date_commande?->format('d/m/Y') }}</td>
                    <td class="fw-medium">{{ $commande->reference }}</td>
                    <td>{{ $commande->produit?->nom ?? '—' }}</td>
                    <td><span class="badge {{ $commande->statutBadgeClass() }}">{{ $commande->statutLabel() }}</span></td>
                    <td class="fw-semibold">{{ $fmt($commande->montant()) }} FCFA</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center text-muted py-4">Aucune commande sur cette période.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
