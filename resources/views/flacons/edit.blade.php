@extends('layout.main')

@section('title', 'Modifier flacon')

@section('content')
<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0">Modifier le flacon</h4>
      <a href="{{ route('flacons.index') }}" class="btn btn-outline-secondary">Retour</a>
    </div>

    <div class="card">
      <div class="card-body">
        <form method="POST" action="{{ route('flacons.update', $flacon) }}">
          @csrf
          @method('PUT')

          <div class="mb-3">
            <label class="form-label">Nom</label>
            <input type="text" name="nom" class="form-control" value="{{ old('nom', $flacon->nom) }}" required />
            @error('nom')<div class="text-danger mt-1">{{ $message }}</div>@enderror
          </div>

          <div class="mb-3">
            <label class="form-label">Contenance (ml)</label>
            <input type="number" name="contenance_ml" class="form-control" min="1" step="1" value="{{ old('contenance_ml', $flacon->contenance_ml) }}" required />
            @error('contenance_ml')<div class="text-danger mt-1">{{ $message }}</div>@enderror
          </div>

          <div class="mb-3">
            <label class="form-label">Statut</label>
            <select name="statut" class="form-select" required>
              <option value="actif" @selected(old('statut', $flacon->statut) === 'actif')>Actif</option>
              <option value="inactif" @selected(old('statut', $flacon->statut) === 'inactif')>Inactif</option>
            </select>
            @error('statut')<div class="text-danger mt-1">{{ $message }}</div>@enderror
          </div>

          <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('flacons.index') }}" class="btn btn-outline-secondary">Annuler</a>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
