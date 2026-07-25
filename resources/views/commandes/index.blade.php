@extends('layout.main')

@section('title', 'Commandes')

@section('content')
<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">Liste des commandes</h4>
        <p class="mb-0 text-muted">Suivi des commandes clients Uniko Parfums</p>
      </div>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNouvelleCommande">
        <i class="bx bx-plus me-1"></i>Ajouter une commande
      </button>
    </div>

    @if (session('success'))
      <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    <form method="GET" action="{{ route('commandes.index') }}" class="card mb-4">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-6">
            <label class="form-label">Recherche</label>
            <input
              type="text"
              name="q"
              class="form-control"
              placeholder="Référence, client, téléphone, parfum…"
              value="{{ request('q') }}" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Statut</label>
            <select name="statut" class="form-select">
              <option value="">Tous</option>
              <option value="en_attente" @selected(request('statut') === 'en_attente')>En attente</option>
              <option value="confirmee" @selected(request('statut') === 'confirmee')>Confirmée</option>
              <option value="livree" @selected(request('statut') === 'livree')>Livrée</option>
              <option value="annulee" @selected(request('statut') === 'annulee')>Annulée</option>
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
              <th>Référence</th>
              <th>Parfum</th>
              <th>Contenance</th>
              <th>Qté</th>
              <th>Client</th>
              <th>Téléphone</th>
              <th>Statut</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($commandes as $commande)
              <tr>
                <td class="fw-medium">{{ $commande->reference }}</td>
                <td>{{ $commande->produit?->nom ?? '—' }}</td>
                <td>{{ $commande->flacon ? $commande->flacon->contenance_ml.' ml' : '—' }}</td>
                <td>{{ $commande->quantite }}</td>
                <td>{{ $commande->client_nom ?: '—' }}</td>
                <td>{{ $commande->client_telephone }}</td>
                <td>
                  <span class="badge {{ $commande->statutBadgeClass() }}">
                    {{ $commande->statutLabel() }}
                  </span>
                </td>
                <td>{{ $commande->created_at?->format('d/m/Y H:i') }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="8" class="text-center py-5 text-muted">
                  Aucune commande enregistrée pour le moment.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($commandes->hasPages())
        <div class="card-footer">
          {{ $commandes->links() }}
        </div>
      @endif
    </div>

    <div class="modal fade" id="modalNouvelleCommande" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Ajouter une commande</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST" action="{{ route('commandes.store') }}">
            @csrf
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Parfum <span class="text-danger">*</span></label>
                  <select name="produit_id" class="form-select @error('produit_id') is-invalid @enderror" required>
                    <option value="">Sélectionner un parfum</option>
                    @foreach ($produits as $produit)
                      <option value="{{ $produit->id }}" @selected((string) old('produit_id') === (string) $produit->id)>
                        {{ $produit->nom }}
                      </option>
                    @endforeach
                  </select>
                  @error('produit_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label">Contenance <span class="text-danger">*</span></label>
                  <select name="flacon_id" class="form-select @error('flacon_id') is-invalid @enderror" required>
                    <option value="">Sélectionner une contenance</option>
                    @foreach ($flacons as $flacon)
                      <option value="{{ $flacon->id }}" @selected((string) old('flacon_id') === (string) $flacon->id)>
                        {{ $flacon->label() }}
                      </option>
                    @endforeach
                  </select>
                  @error('flacon_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                  <label class="form-label">Quantité <span class="text-danger">*</span></label>
                  <input
                    type="number"
                    name="quantite"
                    class="form-control @error('quantite') is-invalid @enderror"
                    min="1"
                    step="1"
                    value="{{ old('quantite', 1) }}"
                    required />
                  @error('quantite')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                  <label class="form-label">Téléphone <span class="text-danger">*</span></label>
                  <input
                    type="text"
                    name="client_telephone"
                    class="form-control @error('client_telephone') is-invalid @enderror"
                    placeholder="Ex: 07 00 00 00 00"
                    value="{{ old('client_telephone') }}"
                    required />
                  @error('client_telephone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                  <label class="form-label">Nom du client</label>
                  <input
                    type="text"
                    name="client_nom"
                    class="form-control @error('client_nom') is-invalid @enderror"
                    placeholder="Optionnel"
                    value="{{ old('client_nom') }}" />
                  @error('client_nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label">Statut</label>
                  <select name="statut" class="form-select @error('statut') is-invalid @enderror" required>
                    <option value="en_attente" @selected(old('statut', 'en_attente') === 'en_attente')>En attente</option>
                    <option value="confirmee" @selected(old('statut') === 'confirmee')>Confirmée</option>
                    <option value="livree" @selected(old('statut') === 'livree')>Livrée</option>
                    <option value="annulee" @selected(old('statut') === 'annulee')>Annulée</option>
                  </select>
                  @error('statut')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label">Notes</label>
                  <input
                    type="text"
                    name="notes"
                    class="form-control @error('notes') is-invalid @enderror"
                    placeholder="Optionnel"
                    value="{{ old('notes') }}" />
                  @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
              <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    @if ($errors->any() || request()->boolean('create'))
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          var el = document.getElementById('modalNouvelleCommande');
          if (el && window.bootstrap) new bootstrap.Modal(el).show();
        });
      </script>
    @endif
  </div>
</div>
@endsection
