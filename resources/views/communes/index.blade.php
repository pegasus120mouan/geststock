@extends('layout.main')

@section('title', 'Communes')

@section('content')
<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">Communes</h4>
        <p class="mb-0 text-muted">Zones de livraison</p>
      </div>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNouvelleCommune">
        <i class="bx bx-plus me-1"></i>Nouvelle commune
      </button>
    </div>

    @if (session('success'))
      <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    <form method="GET" action="{{ route('communes.index') }}" class="card mb-4">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-6">
            <label class="form-label">Recherche</label>
            <input type="text" name="q" class="form-control" placeholder="Nom de la commune…" value="{{ request('q') }}" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Statut</label>
            <select name="statut" class="form-select">
              <option value="">Tous</option>
              <option value="actif" @selected(request('statut') === 'actif')>Actif</option>
              <option value="inactif" @selected(request('statut') === 'inactif')>Inactif</option>
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
              <th>Nom</th>
              <th>Coût livraison</th>
              <th>Statut</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($communes as $commune)
              <tr>
                <td class="fw-medium">{{ $commune->nom }}</td>
                <td>
                  @if ($commune->coutLivraison)
                    {{ number_format((float) $commune->coutLivraison->montant, 0, ',', ' ') }} FCFA
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>
                  <span class="badge {{ $commune->isActif() ? 'bg-label-success' : 'bg-label-secondary' }}">
                    {{ ucfirst($commune->statut) }}
                  </span>
                </td>
                <td class="text-end">
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-primary"
                    title="Modifier"
                    data-bs-toggle="modal"
                    data-bs-target="#modalEditCommune{{ $commune->id }}">
                    <i class="bx bx-edit"></i>
                  </button>
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-danger"
                    title="Supprimer"
                    data-bs-toggle="modal"
                    data-bs-target="#modalDeleteCommune{{ $commune->id }}">
                    <i class="bx bx-trash"></i>
                  </button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="text-center py-4 text-muted">Aucune commune enregistrée.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($communes->hasPages())
        <div class="card-footer">{{ $communes->links() }}</div>
      @endif
    </div>

    <div class="modal fade" id="modalNouvelleCommune" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <form method="POST" action="{{ route('communes.store') }}" class="modal-content">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">Nouvelle commune</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Nom <span class="text-danger">*</span></label>
              <input type="text" name="nom" class="form-control" value="{{ old('nom') }}" required />
              @error('nom')<div class="text-danger mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
              <label class="form-label">Statut</label>
              <select name="statut" class="form-select" required>
                <option value="actif" @selected(old('statut', 'actif') === 'actif')>Actif</option>
                <option value="inactif" @selected(old('statut') === 'inactif')>Inactif</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
          </div>
        </form>
      </div>
    </div>

    @foreach ($communes as $commune)
      <div class="modal fade" id="modalEditCommune{{ $commune->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" role="document">
          <form method="POST" action="{{ route('communes.update', $commune) }}" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
              <h5 class="modal-title">Modifier la commune</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Nom <span class="text-danger">*</span></label>
                <input type="text" name="nom" class="form-control" value="{{ $commune->nom }}" required />
              </div>
              <div class="mb-3">
                <label class="form-label">Statut</label>
                <select name="statut" class="form-select" required>
                  <option value="actif" @selected($commune->statut === 'actif')>Actif</option>
                  <option value="inactif" @selected($commune->statut === 'inactif')>Inactif</option>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
              <button type="submit" class="btn btn-primary">Mettre à jour</button>
            </div>
          </form>
        </div>
      </div>

      <div class="modal fade" id="modalDeleteCommune{{ $commune->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-danger">
              <h5 class="modal-title text-white">Confirmer la suppression</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
              <p class="mb-0">Supprimer la commune <strong>{{ $commune->nom }}</strong> ?</p>
            </div>
            <div class="modal-footer justify-content-center">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
              <form method="POST" action="{{ route('communes.destroy', $commune) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">Supprimer</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    @endforeach

    @if ($errors->any() || request()->boolean('create'))
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          var el = document.getElementById('modalNouvelleCommune');
          if (el && window.bootstrap) new bootstrap.Modal(el).show();
        });
      </script>
    @endif
  </div>
</div>
@endsection
