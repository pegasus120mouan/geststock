@extends('layout.main')

@section('title', 'Prix '.$prixUnitaire->reference)

@section('content')
@php
  $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
@endphp

<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <h4 class="mb-1">Prix unitaire {{ $prixUnitaire->reference }}</h4>
        <p class="mb-0 text-muted">
          {{ $prixUnitaire->flacon?->contenance_ml }} ml · {{ $prixUnitaire->categorieLabel() }} ·
          <strong class="text-primary">{{ $fmt($prixUnitaire->prix) }} FCFA</strong>
        </p>
      </div>
      <a href="{{ route('prix-unitaires.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back me-1"></i>Retour à la liste
      </a>
    </div>

    @if (session('success'))
      <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    @if ($errors->any())
      <div class="alert alert-danger alert-dismissible fade show">
        {{ $errors->first() }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    <div class="row g-4">
      <div class="col-lg-5">
        <div class="card">
          <div class="card-header">
            <h5 class="mb-0">Modifier le tarif</h5>
          </div>
          <div class="card-body">
            <form method="POST" action="{{ route('prix-unitaires.update', $prixUnitaire) }}">
              @csrf
              @method('PUT')
              <div class="mb-3">
                <label class="form-label">Référence</label>
                <input type="text" name="reference" class="form-control" value="{{ old('reference', $prixUnitaire->reference) }}" required />
              </div>
              <div class="mb-3">
                <label class="form-label">Contenance</label>
                <select name="flacon_id" class="form-select" required>
                  @foreach (\App\Models\Flacon::query()->actif()->orderBy('contenance_ml')->get() as $flacon)
                    <option value="{{ $flacon->id }}" @selected((int) old('flacon_id', $prixUnitaire->flacon_id) === $flacon->id)>
                      {{ $flacon->label() }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Catégorie</label>
                <select name="categorie" class="form-select" required>
                  @foreach (\App\Models\PrixUnitaire::categories() as $value => $label)
                    <option value="{{ $value }}" @selected(old('categorie', $prixUnitaire->categorie) === $value)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Prix (FCFA)</label>
                <input
                  type="text"
                  inputmode="numeric"
                  class="form-control prix-fr-display"
                  data-target="prix_edit"
                  value="{{ number_format((float) old('prix', $prixUnitaire->prix), 0, ',', ' ') }}"
                  required />
                <input type="hidden" name="prix" id="prix_edit" value="{{ (int) old('prix', $prixUnitaire->prix) }}" />
              </div>
              <button type="submit" class="btn btn-primary">Enregistrer</button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
              <h5 class="mb-0">Parfums associés</h5>
              <small class="text-muted">Parfums qui utilisent ce prix unitaire</small>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAssocierParfums">
              <i class="bx bx-link me-1"></i>Associer
            </button>
          </div>

          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th>Parfum</th>
                  <th>Stock</th>
                  <th>Statut</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($prixUnitaire->produits as $produit)
                  <tr>
                    <td>
                      <a href="{{ route('produits.show', $produit) }}" class="fw-medium text-heading text-decoration-none">
                        {{ $produit->nom }}
                      </a>
                    </td>
                    <td>{{ number_format((float) $produit->stock_ml, 2, ',', ' ') }} ml</td>
                    <td>
                      <span class="badge {{ $produit->isActif() ? 'bg-label-success' : 'bg-label-secondary' }}">
                        {{ ucfirst($produit->statut) }}
                      </span>
                    </td>
                    <td class="text-end">
                      <form method="POST" action="{{ route('prix-unitaires.produits.detach', [$prixUnitaire, $produit]) }}" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Retirer">
                          <i class="bx bx-unlink"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="4" class="text-center text-muted py-4">
                      Aucun parfum associé à cette référence pour le moment.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="modalAssocierParfums" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Associer des parfums</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST" action="{{ route('prix-unitaires.produits.attach', $prixUnitaire) }}">
            @csrf
            <div class="modal-body">
              <p class="text-muted mb-3">
                Sélectionnez les parfums à lier à <strong>{{ $prixUnitaire->reference }}</strong>
                ({{ $prixUnitaire->flacon?->contenance_ml }} ml · {{ $prixUnitaire->categorieLabel() }}).
                Les parfums déjà tarifés pour cette contenance et catégorie sont grisés.
              </p>

              <div class="mb-3">
                <input type="search" id="filtreParfumsModal" class="form-control" placeholder="Rechercher un parfum…" />
              </div>

              <div class="list-group list-group-flush border rounded" style="max-height: 360px; overflow: auto;">
                @forelse ($produits as $produit)
                  <label
                    class="list-group-item d-flex align-items-center gap-3 {{ $produit->deja_tarife ? 'bg-light text-muted' : '' }}"
                    data-nom="{{ mb_strtolower($produit->nom) }}"
                    @if ($produit->deja_tarife) style="opacity: 0.55; cursor: not-allowed;" @endif>
                    <input
                      type="checkbox"
                      class="form-check-input flex-shrink-0"
                      name="produit_ids[]"
                      value="{{ $produit->id }}"
                      @disabled(! $produit->selectionnable)
                      @checked($produit->deja_associe) />
                    <div class="flex-grow-1">
                      <div class="fw-medium">{{ $produit->nom }}</div>
                      <small class="{{ $produit->deja_tarife ? 'text-muted' : 'text-body-secondary' }}">
                        @if ($produit->deja_associe)
                          Déjà associé à cette référence
                        @elseif ($produit->deja_tarife)
                          Prix déjà renseigné pour {{ $prixUnitaire->flacon?->contenance_ml }} ml / {{ $prixUnitaire->categorieLabel() }}
                        @else
                          {{ number_format((float) $produit->stock_ml, 2, ',', ' ') }} ml en stock
                        @endif
                      </small>
                    </div>
                  </label>
                @empty
                  <div class="list-group-item text-muted text-center py-4">Aucun parfum actif.</div>
                @endforelse
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
              <button type="submit" class="btn btn-primary">
                <i class="bx bx-link me-1"></i>Associer la sélection
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

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
        display.value = formatFr(display.value);
        hidden.value = sanitize(display.value);
      }
      display.addEventListener('input', sync);
      display.addEventListener('blur', sync);
      sync();
    });

    var filtre = document.getElementById('filtreParfumsModal');
    if (filtre) {
      filtre.addEventListener('input', function () {
        var q = (filtre.value || '').toLowerCase().trim();
        document.querySelectorAll('#modalAssocierParfums [data-nom]').forEach(function (row) {
          var nom = row.getAttribute('data-nom') || '';
          row.style.display = !q || nom.indexOf(q) !== -1 ? '' : 'none';
        });
      });
    }

    @if ($errors->has('produit_ids'))
      var modal = document.getElementById('modalAssocierParfums');
      if (modal && window.bootstrap) new bootstrap.Modal(modal).show();
    @endif
  });
</script>
@endsection
