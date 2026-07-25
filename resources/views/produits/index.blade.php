@extends('layout.main')

@section('title', 'Parfums')

@section('content')
<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">Parfums</h4>
        <p class="mb-0 text-muted">Liste des parfums</p>
      </div>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNouveauProduit">
        <i class="bx bx-plus me-1"></i>Ajouter un parfum
      </button>
    </div>

    @if (session('success'))
      <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    <form method="GET" action="{{ route('produits.index') }}" class="card mb-4">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-6">
            <label class="form-label">Recherche</label>
            <input type="text" name="q" class="form-control" placeholder="Nom du parfum…" value="{{ request('q') }}" />
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
              <th>Image</th>
              <th>Nom</th>
              <th>Stock (ml)</th>
              <th>Statut</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody class="table-border-bottom-0">
            @forelse ($produits as $produit)
              <tr>
                <td>
                  <div class="avatar">
                    <img src="{{ $produit->image_url }}" alt="{{ $produit->nom }}" class="rounded" style="width:40px;height:40px;object-fit:cover;" />
                  </div>
                </td>
                <td class="fw-medium">{{ $produit->nom }}</td>
                <td>{{ number_format((float) $produit->stock_ml, 2, ',', ' ') }}</td>
                <td>
                  <span class="badge {{ $produit->isActif() ? 'bg-label-success' : 'bg-label-secondary' }}">
                    {{ ucfirst($produit->statut) }}
                  </span>
                </td>
                <td class="text-end">
                  <a href="{{ route('produits.edit', $produit) }}" class="btn btn-sm btn-outline-primary" title="Modifier">
                    <i class="bx bx-edit"></i>
                  </a>
                  <button type="button" class="btn btn-sm btn-outline-danger" title="Supprimer" data-bs-toggle="modal" data-bs-target="#modalDeleteProduit{{ $produit->id }}">
                    <i class="bx bx-trash"></i>
                  </button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center py-4 text-muted">Aucun parfum enregistré</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="mt-3">{{ $produits->links() }}</div>

    <div class="modal fade" id="modalNouveauProduit" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Nouveau parfum</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="POST" action="{{ route('produits.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Nom</label>
                <input type="text" name="nom" class="form-control" value="{{ old('nom') }}" required />
                @error('nom')<div class="text-danger mt-1">{{ $message }}</div>@enderror
              </div>
              <div class="mb-3">
                <label class="form-label">Image</label>
                <input type="file" name="image" class="form-control" accept="image/*" />
                @error('image')<div class="text-danger mt-1">{{ $message }}</div>@enderror
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

    @foreach ($produits as $produit)
      <div class="modal fade" id="modalDeleteProduit{{ $produit->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-danger">
              <h5 class="modal-title text-white">Confirmer la suppression</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
              <p class="mb-2">Supprimer le parfum <strong>{{ $produit->nom }}</strong> ?</p>
              <p class="text-danger mb-0">Cette action est irréversible.</p>
            </div>
            <div class="modal-footer justify-content-center">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
              <form method="POST" action="{{ route('produits.destroy', $produit) }}">
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
          var el = document.getElementById('modalNouveauProduit');
          if (el && window.bootstrap) new bootstrap.Modal(el).show();
        });
      </script>
    @endif
  </div>
</div>
@endsection
