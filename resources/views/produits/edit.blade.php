@extends('layout.main')

@section('title', 'Modifier parfum')

@section('content')
<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0">Modifier le parfum</h4>
      <a href="{{ route('produits.index') }}" class="btn btn-outline-secondary">Retour</a>
    </div>

    <div class="card">
      <div class="card-body">
        <form method="POST" action="{{ route('produits.update', $produit) }}" enctype="multipart/form-data">
          @csrf
          @method('PUT')

          <div class="mb-3">
            <label class="form-label">Nom</label>
            <input type="text" name="nom" class="form-control" value="{{ old('nom', $produit->nom) }}" required />
            @error('nom')<div class="text-danger mt-1">{{ $message }}</div>@enderror
          </div>

          <div class="mb-3">
            <label class="form-label">Image</label>
            @if ($produit->image)
              <div class="mb-2">
                <img src="{{ $produit->image_url }}" alt="{{ $produit->nom }}" class="rounded" style="width:64px;height:64px;object-fit:cover;" />
              </div>
            @endif
            <input type="file" name="image" class="form-control" accept="image/*" />
            @error('image')<div class="text-danger mt-1">{{ $message }}</div>@enderror
          </div>

          <div class="mb-3">
            <label class="form-label">Statut</label>
            <select name="statut" class="form-select" required>
              <option value="actif" @selected(old('statut', $produit->statut) === 'actif')>Actif</option>
              <option value="inactif" @selected(old('statut', $produit->statut) === 'inactif')>Inactif</option>
            </select>
            @error('statut')<div class="text-danger mt-1">{{ $message }}</div>@enderror
          </div>

          <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('produits.index') }}" class="btn btn-outline-secondary">Annuler</a>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
