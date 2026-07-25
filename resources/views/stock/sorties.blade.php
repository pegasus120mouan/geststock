@extends('layout.main')

@section('title', 'Historique des sorties')

@section('content')
<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">Historique des sorties</h4>
        <p class="mb-0 text-muted">Mouvements de sortie de stock (parfums en ml)</p>
      </div>
    </div>

    <form method="GET" action="{{ route('stock.sorties') }}" class="card mb-4">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-5">
            <label class="form-label">Recherche</label>
            <input type="text" name="q" class="form-control" placeholder="Nom du parfum…" value="{{ request('q') }}" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Parfum</label>
            <select name="produit_id" class="form-select">
              <option value="">Tous</option>
              @foreach ($produits as $produit)
                <option value="{{ $produit->id }}" @selected((string) request('produit_id') === (string) $produit->id)>{{ $produit->nom }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <button class="btn btn-outline-primary w-100" type="submit">Filtrer</button>
          </div>
        </div>
      </div>
    </form>

    <div class="card">
      <div class="table-responsive text-nowrap">
        <table class="table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Parfum</th>
              <th>Quantité</th>
              <th>Équivalent ml</th>
              <th>Stock avant</th>
              <th>Stock après</th>
              <th>Utilisateur</th>
              <th>Commentaire</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($mouvements as $mouvement)
              <tr>
                <td>{{ $mouvement->created_at?->format('d/m/Y H:i') }}</td>
                <td class="fw-medium">{{ $mouvement->produit?->nom }}</td>
                <td>{{ number_format((float) $mouvement->quantite, 2, ',', ' ') }} {{ $mouvement->unite?->value }}</td>
                <td class="text-danger">-{{ number_format((float) $mouvement->quantite_ml, 2, ',', ' ') }} ml</td>
                <td>{{ number_format((float) $mouvement->stock_avant, 2, ',', ' ') }} ml</td>
                <td>{{ number_format((float) $mouvement->stock_apres, 2, ',', ' ') }} ml</td>
                <td>{{ $mouvement->user?->name ?? '—' }}</td>
                <td>{{ $mouvement->commentaire ?: '—' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="8" class="text-center py-4 text-muted">Aucune sortie de stock pour le moment</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="mt-3">{{ $mouvements->links() }}</div>
  </div>
</div>
@endsection
