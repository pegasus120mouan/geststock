@extends('layout.main')

@section('title', 'Prix unitaire')

@section('content')
@php
  $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
@endphp

<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">Prix unitaire</h4>
        <p class="mb-0 text-muted">Tarifs selon le parfum et la contenance du flacon</p>
      </div>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNouveauPrix">
        <i class="bx bx-plus me-1"></i>Ajouter un prix
      </button>
    </div>

    @if (session('success'))
      <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    <form method="GET" action="{{ route('prix-unitaires.index') }}" class="card mb-4">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Recherche</label>
            <input
              type="text"
              name="q"
              class="form-control"
              placeholder="Parfum ou flacon…"
              value="{{ request('q') }}" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Parfum</label>
            <select name="produit_id" class="form-select">
              <option value="">Tous</option>
              @foreach ($produits as $produit)
                <option value="{{ $produit->id }}" @selected((string) request('produit_id') === (string) $produit->id)>
                  {{ $produit->nom }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Contenance</label>
            <select name="flacon_id" class="form-select">
              <option value="">Toutes</option>
              @foreach ($flacons as $flacon)
                <option value="{{ $flacon->id }}" @selected((string) request('flacon_id') === (string) $flacon->id)>
                  {{ $flacon->label() }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
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
              <th>Parfum</th>
              <th>Contenance</th>
              <th>Prix unitaire</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($prixUnitaires as $prix)
              <tr>
                <td class="fw-medium">{{ $prix->produit?->nom ?? '—' }}</td>
                <td>{{ $prix->flacon ? $prix->flacon->contenance_ml.' ml' : '—' }}</td>
                <td class="fw-semibold text-primary">{{ $fmt($prix->prix) }} FCFA</td>
                <td class="text-end">
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-primary"
                    title="Modifier"
                    data-bs-toggle="modal"
                    data-bs-target="#modalEditPrix{{ $prix->id }}">
                    <i class="bx bx-edit"></i>
                  </button>
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-danger"
                    title="Supprimer"
                    data-bs-toggle="modal"
                    data-bs-target="#modalDeletePrix{{ $prix->id }}">
                    <i class="bx bx-trash"></i>
                  </button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="text-center py-5 text-muted">
                  Aucun prix unitaire enregistré.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($prixUnitaires->hasPages())
        <div class="card-footer">
          {{ $prixUnitaires->links() }}
        </div>
      @endif
    </div>

    <div class="modal fade" id="modalNouveauPrix" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Ajouter un prix unitaire</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST" action="{{ route('prix-unitaires.store') }}">
            @csrf
            <div class="modal-body">
              <div class="mb-3">
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
              <div class="mb-3">
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
              <div class="mb-3">
                <label class="form-label">Prix (FCFA) <span class="text-danger">*</span></label>
                <input
                  type="text"
                  inputmode="numeric"
                  class="form-control prix-fr-display @error('prix') is-invalid @enderror"
                  data-target="prix_create"
                  placeholder="Ex: 5 000"
                  value="{{ old('prix') !== null ? number_format((float) old('prix'), 0, ',', ' ') : '' }}"
                  autocomplete="off"
                  required />
                <input type="hidden" name="prix" id="prix_create" value="{{ old('prix') }}" />
                @error('prix')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
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

    @foreach ($prixUnitaires as $prix)
      <div class="modal fade" id="modalEditPrix{{ $prix->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Modifier le prix unitaire</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('prix-unitaires.update', $prix) }}">
              @csrf
              @method('PUT')
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label">Parfum <span class="text-danger">*</span></label>
                  <select name="produit_id" class="form-select" required>
                    @foreach ($produits as $produit)
                      <option value="{{ $produit->id }}" @selected($prix->produit_id === $produit->id)>
                        {{ $produit->nom }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">Contenance <span class="text-danger">*</span></label>
                  <select name="flacon_id" class="form-select" required>
                    @foreach ($flacons as $flacon)
                      <option value="{{ $flacon->id }}" @selected($prix->flacon_id === $flacon->id)>
                        {{ $flacon->label() }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">Prix (FCFA) <span class="text-danger">*</span></label>
                  <input
                    type="text"
                    inputmode="numeric"
                    class="form-control prix-fr-display"
                    data-target="prix_edit_{{ $prix->id }}"
                    placeholder="Ex: 5 000"
                    value="{{ number_format((float) $prix->prix, 0, ',', ' ') }}"
                    autocomplete="off"
                    required />
                  <input type="hidden" name="prix" id="prix_edit_{{ $prix->id }}" value="{{ (int) $prix->prix }}" />
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary">Mettre à jour</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="modal fade" id="modalDeletePrix{{ $prix->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-danger">
              <h5 class="modal-title text-white">Confirmer la suppression</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
              <p class="mb-0">
                Supprimer le prix de
                <strong>{{ $prix->produit?->nom }}</strong>
                ({{ $prix->flacon?->contenance_ml }} ml) ?
              </p>
            </div>
            <div class="modal-footer justify-content-center">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
              <form method="POST" action="{{ route('prix-unitaires.destroy', $prix) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">Supprimer</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    @endforeach

    <script>
      document.addEventListener('DOMContentLoaded', function () {
        function sanitize(value) {
          return String(value || '').replace(/\s/g, '').replace(/[^\d]/g, '');
        }

        function formatFr(value) {
          var clean = sanitize(value);
          if (clean === '') return '';
          clean = clean.replace(/^0+(?=\d)/, '');
          return clean.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        }

        document.querySelectorAll('.prix-fr-display').forEach(function (display) {
          var hidden = document.getElementById(display.dataset.target);
          if (!hidden) return;

          function sync() {
            var formatted = formatFr(display.value);
            display.value = formatted;
            hidden.value = sanitize(formatted);
          }

          display.addEventListener('input', function () {
            var cursor = display.selectionStart;
            var before = display.value.length;
            sync();
            var after = display.value.length;
            var next = Math.max(0, cursor + (after - before));
            display.setSelectionRange(next, next);
          });

          display.addEventListener('blur', sync);
          sync();
        });

        @if ($errors->any() || request()->boolean('create'))
          var el = document.getElementById('modalNouveauPrix');
          if (el && window.bootstrap) new bootstrap.Modal(el).show();
        @endif
      });
    </script>
  </div>
</div>
@endsection
