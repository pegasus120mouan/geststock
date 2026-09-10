@extends('layout.main')

@section('title', $produit->nom)

@section('content')
@php
  $fmt = fn ($n) => $n > 0 ? number_format((float) $n, 0, ',', ' ') : '—';
  $ongletActif = in_array($onglet, ['prix', 'historique', 'seuil'], true) ? $onglet : 'prix';
@endphp

<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div class="d-flex align-items-center gap-3">
        <div class="avatar avatar-lg">
          <img
            src="{{ $produit->image_url }}"
            alt="{{ $produit->nom }}"
            class="rounded"
            style="width:64px;height:64px;object-fit:cover;" />
        </div>
        <div>
          <h4 class="mb-1">{{ $produit->nom }}</h4>
          <p class="mb-0 text-muted">
            Stock actuel :
            <strong>{{ number_format((float) $produit->stock_ml, 2, ',', ' ') }} ml</strong>
            ·
            <span class="badge {{ $produit->isActif() ? 'bg-label-success' : 'bg-label-secondary' }}">
              {{ ucfirst($produit->statut) }}
            </span>
          </p>
        </div>
      </div>
      <div class="d-flex gap-2">
        <a href="{{ route('produits.index') }}" class="btn btn-outline-secondary">
          <i class="bx bx-arrow-back me-1"></i>Retour
        </a>
        <a href="{{ route('produits.edit', $produit) }}" class="btn btn-outline-primary">
          <i class="bx bx-edit me-1"></i>Modifier
        </a>
      </div>
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

    <div class="card">
      <div class="card-header border-bottom pb-0">
        <ul class="nav nav-tabs card-header-tabs produit-onglets" role="tablist">
          <li class="nav-item" role="presentation">
            <button
              class="nav-link {{ $ongletActif === 'prix' ? 'active' : '' }}"
              id="tab-prix"
              data-bs-toggle="tab"
              data-bs-target="#pane-prix"
              type="button"
              role="tab"
              aria-controls="pane-prix"
              aria-selected="{{ $ongletActif === 'prix' ? 'true' : 'false' }}">
              <i class="bx bx-purchase-tag me-1"></i>Prix unitaire
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button
              class="nav-link {{ $ongletActif === 'historique' ? 'active' : '' }}"
              id="tab-historique"
              data-bs-toggle="tab"
              data-bs-target="#pane-historique"
              type="button"
              role="tab"
              aria-controls="pane-historique"
              aria-selected="{{ $ongletActif === 'historique' ? 'true' : 'false' }}">
              <i class="bx bx-history me-1"></i>Historique des sorties et entrées
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button
              class="nav-link {{ $ongletActif === 'seuil' ? 'active' : '' }}"
              id="tab-seuil"
              data-bs-toggle="tab"
              data-bs-target="#pane-seuil"
              type="button"
              role="tab"
              aria-controls="pane-seuil"
              aria-selected="{{ $ongletActif === 'seuil' ? 'true' : 'false' }}">
              <i class="bx bx-bell me-1"></i>Seuil alerte
            </button>
          </li>
        </ul>
      </div>

      <div class="tab-content">
        <div
          class="tab-pane fade {{ $ongletActif === 'prix' ? 'show active' : '' }}"
          id="pane-prix"
          role="tabpanel"
          aria-labelledby="tab-prix">
          <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom">
            <p class="mb-0 text-muted">
              Tarifs par contenance — catégories <strong>Détail</strong> et <strong>En gros</strong>
            </p>
            @if ($lignes->isNotEmpty())
              <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalEntrerPrix">
                <i class="bx bx-plus me-1"></i>Entrer le prix unitaire
              </button>
            @endif
          </div>

          @if ($lignes->isEmpty())
            <div class="card-body text-muted">
              Aucun flacon actif. Ajoutez d’abord des contenances dans le menu Flacons.
            </div>
          @else
            <div class="table-responsive">
              <table class="table mb-0">
                <thead>
                  <tr>
                    <th>Contenance</th>
                    <th>Prix détail (FCFA)</th>
                    <th>Prix en gros (FCFA)</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($lignes as $ligne)
                    <tr>
                      <td class="fw-medium">{{ $ligne->flacon->label() }}</td>
                      <td class="{{ $ligne->prix_detail > 0 ? 'text-primary fw-semibold' : 'text-muted' }}">
                        {{ $fmt($ligne->prix_detail) }}{{ $ligne->prix_detail > 0 ? ' FCFA' : '' }}
                      </td>
                      <td class="{{ $ligne->prix_en_gros > 0 ? 'text-primary fw-semibold' : 'text-muted' }}">
                        {{ $fmt($ligne->prix_en_gros) }}{{ $ligne->prix_en_gros > 0 ? ' FCFA' : '' }}
                      </td>
                      <td class="text-end">
                        <button
                          type="button"
                          class="btn btn-sm btn-outline-primary btn-edit-prix"
                          title="Modifier"
                          data-bs-toggle="modal"
                          data-bs-target="#modalEntrerPrix"
                          data-flacon-id="{{ $ligne->flacon->id }}"
                          data-detail="{{ $ligne->prix_detail > 0 ? (int) $ligne->prix_detail : '' }}"
                          data-en-gros="{{ $ligne->prix_en_gros > 0 ? (int) $ligne->prix_en_gros : '' }}">
                          <i class="bx bx-edit"></i>
                        </button>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>

        <div
          class="tab-pane fade {{ $ongletActif === 'historique' ? 'show active' : '' }}"
          id="pane-historique"
          role="tabpanel"
          aria-labelledby="tab-historique">
          <div class="card-body border-bottom">
            <p class="mb-0 text-muted">
              Mouvements de stock (entrées et sorties) pour <strong>{{ $produit->nom }}</strong>
            </p>
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Type</th>
                  <th>Quantité</th>
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
                    <td>
                      @if ($mouvement->type === 'entree')
                        <span class="badge bg-label-success">Entrée</span>
                      @else
                        <span class="badge bg-label-danger">Sortie</span>
                      @endif
                    </td>
                    <td class="{{ $mouvement->type === 'entree' ? 'text-success' : 'text-danger' }}">
                      {{ $mouvement->type === 'entree' ? '+' : '-' }}{{ number_format((float) $mouvement->quantite_ml, 2, ',', ' ') }} ml
                    </td>
                    <td>{{ number_format((float) $mouvement->stock_avant, 2, ',', ' ') }} ml</td>
                    <td>{{ number_format((float) $mouvement->stock_apres, 2, ',', ' ') }} ml</td>
                    <td>{{ $mouvement->user?->name ?? '—' }}</td>
                    <td>{{ $mouvement->commentaire ?: '—' }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                      Aucun mouvement de stock pour ce parfum.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
          @if ($mouvements->hasPages())
            <div class="card-footer">
              {{ $mouvements->appends(['onglet' => 'historique'])->links() }}
            </div>
          @endif
        </div>

        <div
          class="tab-pane fade {{ $ongletActif === 'seuil' ? 'show active' : '' }}"
          id="pane-seuil"
          role="tabpanel"
          aria-labelledby="tab-seuil">
          <div class="card-body">
            <p class="text-muted mb-4">
              Définissez le volume minimum (ml). Quand le stock restant atteint ou descend sous ce seuil,
              une alerte d’approvisionnement s’affiche pour ce parfum.
            </p>

            @if ($produit->isSousSeuilAlerte())
              <div class="alert alert-warning d-flex align-items-center" role="alert">
                <i class="bx bx-error-circle me-2 fs-4"></i>
                <div>
                  Alerte active : stock actuel
                  <strong>{{ number_format((float) $produit->stock_ml, 2, ',', ' ') }} ml</strong>
                  ≤ seuil de
                  <strong>{{ number_format((float) $produit->seuil_alerte_ml, 0, ',', ' ') }} ml</strong>.
                  Un réapprovisionnement est nécessaire.
                </div>
              </div>
            @elseif ($produit->hasSeuilAlerte())
              <div class="alert alert-success d-flex align-items-center" role="alert">
                <i class="bx bx-check-circle me-2 fs-4"></i>
                <div>
                  Stock au-dessus du seuil
                  ({{ number_format((float) $produit->stock_ml, 2, ',', ' ') }} ml /
                  seuil {{ number_format((float) $produit->seuil_alerte_ml, 0, ',', ' ') }} ml).
                </div>
              </div>
            @endif

            <form method="POST" action="{{ route('produits.seuil.update', $produit) }}" class="row g-3" style="max-width: 420px;">
              @csrf
              @method('PUT')
              <div class="col-12">
                <label class="form-label">Seuil d’alerte (ml)</label>
                <input
                  type="text"
                  inputmode="numeric"
                  class="form-control prix-fr-display @error('seuil_alerte_ml') is-invalid @enderror"
                  data-target="seuil_alerte_ml"
                  placeholder="Ex: 250"
                  value="{{ old('seuil_alerte_ml', $produit->seuil_alerte_ml) !== null && old('seuil_alerte_ml', $produit->seuil_alerte_ml) !== '' ? number_format((float) old('seuil_alerte_ml', $produit->seuil_alerte_ml), 0, ',', ' ') : '' }}"
                  autocomplete="off" />
                <input
                  type="hidden"
                  name="seuil_alerte_ml"
                  id="seuil_alerte_ml"
                  value="{{ old('seuil_alerte_ml', $produit->seuil_alerte_ml) }}" />
                @error('seuil_alerte_ml')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">Laissez vide pour désactiver l’alerte sur ce parfum.</div>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary">
                  <i class="bx bx-save me-1"></i>Enregistrer le seuil
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    @if ($lignes->isNotEmpty())
      <div class="modal fade" id="modalEntrerPrix" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Entrer le prix unitaire</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('produits.prix.store', $produit) }}">
              @csrf
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label">Contenance <span class="text-danger">*</span></label>
                  <select name="flacon_id" id="modal_flacon_id" class="form-select @error('flacon_id') is-invalid @enderror" required>
                    <option value="">Sélectionner une contenance</option>
                    @foreach ($lignes as $ligne)
                      <option value="{{ $ligne->flacon->id }}" @selected((string) old('flacon_id') === (string) $ligne->flacon->id)>
                        {{ $ligne->flacon->label() }}
                      </option>
                    @endforeach
                  </select>
                  @error('flacon_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                  <label class="form-label">Prix détail (FCFA)</label>
                  <input
                    type="text"
                    inputmode="numeric"
                    class="form-control prix-fr-display @error('detail') is-invalid @enderror"
                    data-target="modal_prix_detail"
                    placeholder="Ex: 5 000"
                    value="{{ old('detail') !== null && old('detail') !== '' ? number_format((float) old('detail'), 0, ',', ' ') : '' }}"
                    autocomplete="off" />
                  <input type="hidden" name="detail" id="modal_prix_detail" value="{{ old('detail') }}" />
                  @error('detail')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                  <label class="form-label">Prix en gros (FCFA)</label>
                  <input
                    type="text"
                    inputmode="numeric"
                    class="form-control prix-fr-display @error('en_gros') is-invalid @enderror"
                    data-target="modal_prix_en_gros"
                    placeholder="Ex: 4 500"
                    value="{{ old('en_gros') !== null && old('en_gros') !== '' ? number_format((float) old('en_gros'), 0, ',', ' ') : '' }}"
                    autocomplete="off" />
                  <input type="hidden" name="en_gros" id="modal_prix_en_gros" value="{{ old('en_gros') }}" />
                  @error('en_gros')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
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
    @endif
  </div>
</div>

<style>
  .produit-onglets .nav-link {
    font-weight: 500;
    color: #697a8d;
    padding: 0.9rem 1.15rem;
  }
  .produit-onglets .nav-link.active {
    font-weight: 600;
  }
</style>

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

    function bindPrixInputs(root) {
      (root || document).querySelectorAll('.prix-fr-display').forEach(function (display) {
        if (display.dataset.bound === '1') return;
        display.dataset.bound = '1';

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
    }

    bindPrixInputs(document);

    var modal = document.getElementById('modalEntrerPrix');
    if (modal) {
      modal.addEventListener('show.bs.modal', function (event) {
        var trigger = event.relatedTarget;
        var flaconSelect = document.getElementById('modal_flacon_id');
        var detailDisplay = modal.querySelector('[data-target="modal_prix_detail"]');
        var grosDisplay = modal.querySelector('[data-target="modal_prix_en_gros"]');
        var detailHidden = document.getElementById('modal_prix_detail');
        var grosHidden = document.getElementById('modal_prix_en_gros');

        if (!trigger || !trigger.classList.contains('btn-edit-prix')) {
          if (!@json($errors->any())) {
            flaconSelect.value = '';
            detailDisplay.value = '';
            grosDisplay.value = '';
            detailHidden.value = '';
            grosHidden.value = '';
          }
          return;
        }

        flaconSelect.value = trigger.dataset.flaconId || '';
        detailHidden.value = trigger.dataset.detail || '';
        grosHidden.value = trigger.dataset.enGros || '';
        detailDisplay.value = formatFr(trigger.dataset.detail || '');
        grosDisplay.value = formatFr(trigger.dataset.enGros || '');
      });
    }

    @if ($errors->any() || request()->boolean('create_prix'))
      if (modal && window.bootstrap) {
        new bootstrap.Modal(modal).show();
      }
    @endif
  });
</script>
@endsection
