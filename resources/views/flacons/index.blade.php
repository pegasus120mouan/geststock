@extends('layout.main')

@section('title', 'Flacons')

@section('content')
<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">Flacons</h4>
        <p class="mb-0 text-muted">Types de flacons à remplir avec les parfums</p>
      </div>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNouveauFlacon">
        <i class="bx bx-plus me-1"></i>Nouveau flacon
      </button>
    </div>

    @if (session('success'))
      <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    <form method="GET" action="{{ route('flacons.index') }}" class="card mb-4">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-6">
            <label class="form-label">Recherche</label>
            <input type="text" name="q" class="form-control" placeholder="Nom du flacon…" value="{{ request('q') }}" />
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
              <th>Contenance</th>
              <th>Statut</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($flacons as $flacon)
              <tr>
                <td class="fw-medium">{{ $flacon->nom }}</td>
                <td>{{ $flacon->contenance_ml }} ml</td>
                <td>
                  <span class="badge {{ $flacon->isActif() ? 'bg-label-success' : 'bg-label-secondary' }}">
                    {{ ucfirst($flacon->statut) }}
                  </span>
                </td>
                <td class="text-end">
                  <a href="{{ route('flacons.edit', $flacon) }}" class="btn btn-sm btn-outline-primary" title="Modifier">
                    <i class="bx bx-edit"></i>
                  </a>
                  <button type="button" class="btn btn-sm btn-outline-danger" title="Supprimer" data-bs-toggle="modal" data-bs-target="#modalDeleteFlacon{{ $flacon->id }}">
                    <i class="bx bx-trash"></i>
                  </button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="text-center py-4 text-muted">Aucun flacon enregistré</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="mt-3">{{ $flacons->links() }}</div>

    <div class="modal fade" id="modalNouveauFlacon" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Nouveau flacon</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST" action="{{ route('flacons.store') }}">
            @csrf
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Nom</label>
                <input type="text" name="nom" class="form-control" placeholder="Ex: 8 ml" value="{{ old('nom') }}" required />
                @error('nom')<div class="text-danger mt-1">{{ $message }}</div>@enderror
              </div>
              <div class="mb-3">
                <label class="form-label">Contenance (ml)</label>
                <input type="number" name="contenance_ml" class="form-control" min="1" step="1" value="{{ old('contenance_ml') }}" required />
                @error('contenance_ml')<div class="text-danger mt-1">{{ $message }}</div>@enderror
              </div>
              <div class="mb-3">
                <label class="form-label">Statut</label>
                <select name="statut" class="form-select" required>
                  <option value="actif" @selected(old('statut', 'actif') === 'actif')>Actif</option>
                  <option value="inactif" @selected(old('statut') === 'inactif')>Inactif</option>
                </select>
                @error('statut')<div class="text-danger mt-1">{{ $message }}</div>@enderror
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

    @foreach ($flacons as $flacon)
      <div class="modal fade" id="modalDeleteFlacon{{ $flacon->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-danger">
              <h5 class="modal-title text-white">Confirmer la suppression</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
              <p class="mb-2">Supprimer le flacon <strong>{{ $flacon->label() }}</strong> ?</p>
              <p class="text-danger mb-0">Cette action est irréversible.</p>
            </div>
            <div class="modal-footer justify-content-center">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
              <form method="POST" action="{{ route('flacons.destroy', $flacon) }}">
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
          var el = document.getElementById('modalNouveauFlacon');
          if (el && window.bootstrap) new bootstrap.Modal(el).show();
        });
      </script>
    @endif
  </div>
</div>
@endsection
