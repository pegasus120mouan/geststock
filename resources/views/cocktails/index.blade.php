@extends('layout.main')

@section('title', 'Cocktails')

@section('content')
@php
  $fmt = fn ($n) => number_format((float) $n, 2, ',', ' ');
  $oldLignes = old('lignes');
  $createRows = $oldLignes ?: [['produit_id' => '', 'quantite_ml' => '']];
  $editId = old('_edit_id', request('edit'));
@endphp

<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">Cocktails</h4>
        <p class="mb-0 text-muted">Associations de parfums avec leurs quantités</p>
      </div>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNouveauCocktail">
        <i class="bx bx-plus me-1"></i>Nouveau cocktail
      </button>
    </div>

    @if (session('success'))
      <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    <form method="GET" action="{{ route('cocktails.index') }}" class="card mb-4">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-6">
            <label class="form-label">Recherche</label>
            <input type="text" name="q" class="form-control" placeholder="Nom du cocktail…" value="{{ request('q') }}" />
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
              <th>Parfums</th>
              <th>Volume total</th>
              <th>Statut</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($cocktails as $cocktail)
              <tr>
                <td>
                  <a href="{{ route('cocktails.show', $cocktail) }}" class="fw-medium text-heading text-decoration-none">
                    {{ $cocktail->nom }}
                  </a>
                </td>
                <td>
                  <span class="badge bg-label-primary">{{ $cocktail->lignes_count }}</span>
                </td>
                <td>{{ $fmt($cocktail->volumeTotalMl()) }} ml</td>
                <td>
                  <span class="badge {{ $cocktail->isActif() ? 'bg-label-success' : 'bg-label-secondary' }}">
                    {{ ucfirst($cocktail->statut) }}
                  </span>
                </td>
                <td class="text-end">
                  <a href="{{ route('cocktails.show', $cocktail) }}" class="btn btn-sm btn-outline-secondary" title="Voir">
                    <i class="bx bx-show"></i>
                  </a>
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-primary"
                    title="Modifier"
                    data-bs-toggle="modal"
                    data-bs-target="#modalEditCocktail{{ $cocktail->id }}">
                    <i class="bx bx-edit"></i>
                  </button>
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-danger"
                    title="Supprimer"
                    data-bs-toggle="modal"
                    data-bs-target="#modalDeleteCocktail{{ $cocktail->id }}">
                    <i class="bx bx-trash"></i>
                  </button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center py-5 text-muted">Aucun cocktail enregistré.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($cocktails->hasPages())
        <div class="card-footer">{{ $cocktails->links() }}</div>
      @endif
    </div>

    {{-- Modal création --}}
    <div class="modal fade" id="modalNouveauCocktail" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Nouveau cocktail</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST" action="{{ route('cocktails.store') }}">
            @csrf
            <div class="modal-body">
              @if ($errors->any() && ! $editId)
                <div class="alert alert-danger">
                  <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                      <li>{{ $error }}</li>
                    @endforeach
                  </ul>
                </div>
              @endif

              <div class="row g-3 mb-3">
                <div class="col-md-8">
                  <label class="form-label">Nom du cocktail <span class="text-danger">*</span></label>
                  <input
                    type="text"
                    name="nom"
                    class="form-control @error('nom') is-invalid @enderror"
                    value="{{ old('nom') }}"
                    required />
                  @error('nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                  <label class="form-label">Statut</label>
                  <select name="statut" class="form-select" required>
                    <option value="actif" @selected(old('statut', 'actif') === 'actif')>Actif</option>
                    <option value="inactif" @selected(old('statut') === 'inactif')>Inactif</option>
                  </select>
                </div>
              </div>

              <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Composition</h6>
                <button type="button" class="btn btn-sm btn-outline-primary btn-add-ligne" data-target="createLignes">
                  <i class="bx bx-plus me-1"></i>Ajouter un parfum
                </button>
              </div>

              <div class="table-responsive">
                <table class="table" id="createLignes">
                  <thead>
                    <tr>
                      <th>Parfum <span class="text-danger">*</span></th>
                      <th style="width: 160px;">Quantité (ml) <span class="text-danger">*</span></th>
                      <th style="width: 60px;"></th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach ($createRows as $index => $ligne)
                      <tr class="ligne-cocktail">
                        <td>
                          <select name="lignes[{{ $index }}][produit_id]" class="form-select" required>
                            <option value="">Sélectionner un parfum</option>
                            @foreach ($produits as $produit)
                              <option value="{{ $produit->id }}" @selected((string) ($ligne['produit_id'] ?? '') === (string) $produit->id)>
                                {{ $produit->nom }}
                              </option>
                            @endforeach
                          </select>
                        </td>
                        <td>
                          <input
                            type="number"
                            name="lignes[{{ $index }}][quantite_ml]"
                            class="form-control"
                            min="0.01"
                            step="0.01"
                            placeholder="Ex: 10"
                            value="{{ $ligne['quantite_ml'] ?? '' }}"
                            required />
                        </td>
                        <td class="text-end">
                          <button type="button" class="btn btn-sm btn-outline-danger btn-remove-ligne" title="Retirer">
                            <i class="bx bx-trash"></i>
                          </button>
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
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

    @foreach ($cocktails as $cocktail)
      @php
        $isThisEdit = (string) $editId === (string) $cocktail->id;
        $editRows = $isThisEdit && is_array($oldLignes)
          ? $oldLignes
          : ($cocktail->lignes->isNotEmpty()
            ? $cocktail->lignes->map(fn ($l) => ['produit_id' => $l->produit_id, 'quantite_ml' => $l->quantite_ml])->all()
            : [['produit_id' => '', 'quantite_ml' => '']]);
      @endphp

      <div class="modal fade" id="modalEditCocktail{{ $cocktail->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Modifier le cocktail</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('cocktails.update', $cocktail) }}">
              @csrf
              @method('PUT')
              <input type="hidden" name="_edit_id" value="{{ $cocktail->id }}" />
              <div class="modal-body">
                @if ($errors->any() && $isThisEdit)
                  <div class="alert alert-danger">
                    <ul class="mb-0">
                      @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                      @endforeach
                    </ul>
                  </div>
                @endif

                <div class="row g-3 mb-3">
                  <div class="col-md-8">
                    <label class="form-label">Nom du cocktail <span class="text-danger">*</span></label>
                    <input
                      type="text"
                      name="nom"
                      class="form-control"
                      value="{{ $isThisEdit ? old('nom', $cocktail->nom) : $cocktail->nom }}"
                      required />
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Statut</label>
                    <select name="statut" class="form-select" required>
                      <option value="actif" @selected(($isThisEdit ? old('statut', $cocktail->statut) : $cocktail->statut) === 'actif')>Actif</option>
                      <option value="inactif" @selected(($isThisEdit ? old('statut', $cocktail->statut) : $cocktail->statut) === 'inactif')>Inactif</option>
                    </select>
                  </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h6 class="mb-0">Composition</h6>
                  <button type="button" class="btn btn-sm btn-outline-primary btn-add-ligne" data-target="editLignes{{ $cocktail->id }}">
                    <i class="bx bx-plus me-1"></i>Ajouter un parfum
                  </button>
                </div>

                <div class="table-responsive">
                  <table class="table" id="editLignes{{ $cocktail->id }}">
                    <thead>
                      <tr>
                        <th>Parfum <span class="text-danger">*</span></th>
                        <th style="width: 160px;">Quantité (ml) <span class="text-danger">*</span></th>
                        <th style="width: 60px;"></th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($editRows as $index => $ligne)
                        <tr class="ligne-cocktail">
                          <td>
                            <select name="lignes[{{ $index }}][produit_id]" class="form-select" required>
                              <option value="">Sélectionner un parfum</option>
                              @foreach ($produits as $produit)
                                <option value="{{ $produit->id }}" @selected((string) ($ligne['produit_id'] ?? '') === (string) $produit->id)>
                                  {{ $produit->nom }}
                                </option>
                              @endforeach
                            </select>
                          </td>
                          <td>
                            <input
                              type="number"
                              name="lignes[{{ $index }}][quantite_ml]"
                              class="form-control"
                              min="0.01"
                              step="0.01"
                              placeholder="Ex: 10"
                              value="{{ $ligne['quantite_ml'] ?? '' }}"
                              required />
                          </td>
                          <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-ligne" title="Retirer">
                              <i class="bx bx-trash"></i>
                            </button>
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
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

      <div class="modal fade" id="modalDeleteCocktail{{ $cocktail->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-danger">
              <h5 class="modal-title text-white">Confirmer la suppression</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
              <p class="mb-0">Supprimer le cocktail <strong>{{ $cocktail->nom }}</strong> ?</p>
            </div>
            <div class="modal-footer justify-content-center">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
              <form method="POST" action="{{ route('cocktails.destroy', $cocktail) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">Supprimer</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    @endforeach

    <template id="tplLigneCocktail">
      <tr class="ligne-cocktail">
        <td>
          <select name="lignes[__INDEX__][produit_id]" class="form-select" required>
            <option value="">Sélectionner un parfum</option>
            @foreach ($produits as $produit)
              <option value="{{ $produit->id }}">{{ $produit->nom }}</option>
            @endforeach
          </select>
        </td>
        <td>
          <input
            type="number"
            name="lignes[__INDEX__][quantite_ml]"
            class="form-control"
            min="0.01"
            step="0.01"
            placeholder="Ex: 10"
            required />
        </td>
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-outline-danger btn-remove-ligne" title="Retirer">
            <i class="bx bx-trash"></i>
          </button>
        </td>
      </tr>
    </template>

    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var tpl = document.getElementById('tplLigneCocktail').innerHTML;

        document.querySelectorAll('.btn-add-ligne').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var table = document.getElementById(btn.getAttribute('data-target'));
            if (!table) return;
            var tbody = table.querySelector('tbody');
            var index = tbody.querySelectorAll('.ligne-cocktail').length;
            tbody.insertAdjacentHTML('beforeend', tpl.replaceAll('__INDEX__', String(index)));
          });
        });

        document.addEventListener('click', function (e) {
          var btn = e.target.closest('.btn-remove-ligne');
          if (!btn) return;
          var tbody = btn.closest('tbody');
          if (!tbody) return;
          var rows = tbody.querySelectorAll('.ligne-cocktail');
          if (rows.length <= 1) return;
          btn.closest('tr').remove();
        });

        @if (($errors->any() && ! $editId) || request()->boolean('create'))
          var createEl = document.getElementById('modalNouveauCocktail');
          if (createEl && window.bootstrap) new bootstrap.Modal(createEl).show();
        @elseif ($editId)
          var editEl = document.getElementById('modalEditCocktail{{ $editId }}');
          if (editEl && window.bootstrap) new bootstrap.Modal(editEl).show();
        @endif
      });
    </script>
  </div>
</div>
@endsection
