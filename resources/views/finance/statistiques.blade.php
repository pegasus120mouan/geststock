@extends('layout.main')

@section('title', 'Statistiques')

@section('content')
@php
  $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
@endphp

<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <h4 class="mb-1">Statistiques</h4>
        <p class="mb-0 text-muted">Vue annuelle des ventes et performances</p>
      </div>
    </div>

    <form method="GET" action="{{ route('finance.statistiques') }}" class="card mb-4">
      <div class="card-body">
        <div class="row g-3 align-items-end">
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
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-body">
            <span class="d-block text-muted mb-1">CA {{ $annee }}</span>
            <h3 class="mb-0 text-primary">{{ $fmt($caAnnuel) }} FCFA</h3>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-body">
            <span class="d-block text-muted mb-1">Commandes {{ $annee }}</span>
            <h3 class="mb-0">{{ $fmt($nbCommandesAnnee) }}</h3>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-body">
            <span class="d-block text-muted mb-1">Parfums actifs</span>
            <h3 class="mb-0">{{ $fmt($nbParfumsActifs) }}</h3>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card h-100">
          <div class="card-header">
            <h5 class="mb-0">Évolution mensuelle {{ $annee }}</h5>
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th>Mois</th>
                  <th>Commandes</th>
                  <th>Livrées</th>
                  <th>CA</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($statsMensuelles as $ligne)
                  <tr>
                    <td class="fw-medium">{{ $ligne->label }}</td>
                    <td>{{ $fmt($ligne->nb_commandes) }}</td>
                    <td>{{ $fmt($ligne->nb_livrees) }}</td>
                    <td class="text-primary fw-semibold">{{ $fmt($ligne->ca) }} FCFA</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="mb-0">Top parfums {{ $annee }}</h5>
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th>Parfum</th>
                  <th>Qté</th>
                  <th>CA</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($topParfums as $ligne)
                  <tr>
                    <td class="fw-medium">{{ $ligne->produit?->nom ?? '—' }}</td>
                    <td>{{ $fmt($ligne->qte) }}</td>
                    <td class="text-primary fw-semibold">{{ $fmt($ligne->ca) }} FCFA</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="3" class="text-center text-muted py-4">Aucune donnée.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h5 class="mb-0">Répartition par statut</h5>
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th>Statut</th>
                  <th>Nb</th>
                  <th>Montant</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($parStatut as $ligne)
                  @php
                    $tmp = new \App\Models\Commande(['statut' => $ligne->statut]);
                  @endphp
                  <tr>
                    <td><span class="badge {{ $tmp->statutBadgeClass() }}">{{ $tmp->statutLabel() }}</span></td>
                    <td>{{ $fmt($ligne->nb) }}</td>
                    <td>{{ $fmt($ligne->ca) }} FCFA</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="3" class="text-center text-muted py-4">Aucune donnée.</td>
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
