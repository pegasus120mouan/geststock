@extends('layout.main')

@section('title', 'Commandes')

@section('content')
@php
  $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
  $openSection = $openSection ?? session('section', 'en_gros');
  if (! in_array($openSection, ['en_gros', 'detail', 'cocktail'], true)) {
    $openSection = 'en_gros';
  }
  $editId = $editId ?? old('_edit_id');
  $openCreate = $openCreate ?? false;
  $produitsById = $produits->keyBy('id');
  $createLignesNormales = old('lignes', [[
    'categorie' => $openSection === 'en_gros' ? 'en_gros' : 'detail',
    'produit_id' => '',
    'flacon_id' => '',
    'quantite' => 1,
  ]]);
  $createGroupesCocktail = old('groupes_cocktail', [[
    'categorie' => $openSection === 'en_gros' ? 'en_gros' : 'detail',
    'flacon_id' => '',
    'quantite' => 1,
    'cocktail_id' => '',
    'parfums' => [],
  ]]);
@endphp

<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">Liste des commandes</h4>
        <p class="mb-0 text-muted">Une commande peut regrouper des parfums (gros/détail) et des cocktails (gros/détail)</p>
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

    @if (session('error'))
      <div class="alert alert-danger alert-dismissible fade show">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    @if ($errors->has('statut'))
      <div class="alert alert-danger alert-dismissible fade show">
        {{ $errors->first('statut') }}
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
            <input type="hidden" name="section" value="{{ $openSection }}" />
            <button class="btn btn-outline-primary w-100" type="submit">Filtrer</button>
          </div>
        </div>
      </div>
    </form>

    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <button
          type="button"
          class="card w-100 text-start border-0 shadow-none commande-section-card"
          data-section="en_gros"
          style="{{ $openSection === 'en_gros' ? 'background: linear-gradient(135deg, #03c3ec, #0aa2c0);' : 'background: #e7f8fc;' }}">
          <div class="card-body d-flex justify-content-between align-items-center py-4">
            <div>
              <div class="text-uppercase small fw-semibold mb-1 {{ $openSection === 'en_gros' ? 'text-white' : 'text-info' }}" style="opacity: .9;">Contient du</div>
              <h4 class="mb-0 {{ $openSection === 'en_gros' ? 'text-white' : 'text-heading' }}">Gros</h4>
            </div>
            <div class="{{ $openSection === 'en_gros' ? 'text-white' : 'text-info' }}" style="font-size: 2rem; font-weight: 700; line-height: 1;">
              {{ $commandesEnGros->count() }}
            </div>
          </div>
        </button>
      </div>
      <div class="col-md-4">
        <button
          type="button"
          class="card w-100 text-start border-0 shadow-none commande-section-card"
          data-section="detail"
          style="{{ $openSection === 'detail' ? 'background: linear-gradient(135deg, #696cff, #5a5fe0);' : 'background: #efefff;' }}">
          <div class="card-body d-flex justify-content-between align-items-center py-4">
            <div>
              <div class="text-uppercase small fw-semibold mb-1 {{ $openSection === 'detail' ? 'text-white' : 'text-primary' }}" style="opacity: .9;">Contient du</div>
              <h4 class="mb-0 {{ $openSection === 'detail' ? 'text-white' : 'text-heading' }}">Détail</h4>
            </div>
            <div class="{{ $openSection === 'detail' ? 'text-white' : 'text-primary' }}" style="font-size: 2rem; font-weight: 700; line-height: 1;">
              {{ $commandesDetail->count() }}
            </div>
          </div>
        </button>
      </div>
      <div class="col-md-4">
        <button
          type="button"
          class="card w-100 text-start border-0 shadow-none commande-section-card"
          data-section="cocktail"
          style="{{ $openSection === 'cocktail' ? 'background: linear-gradient(135deg, #ff9f43, #ee8133);' : 'background: #fff4e6;' }}">
          <div class="card-body d-flex justify-content-between align-items-center py-4">
            <div>
              <div class="text-uppercase small fw-semibold mb-1 {{ $openSection === 'cocktail' ? 'text-white' : 'text-warning' }}" style="opacity: .9;">Contient du</div>
              <h4 class="mb-0 {{ $openSection === 'cocktail' ? 'text-white' : 'text-heading' }}">Cocktail</h4>
            </div>
            <div class="{{ $openSection === 'cocktail' ? 'text-white' : 'text-warning' }}" style="font-size: 2rem; font-weight: 700; line-height: 1;">
              {{ $commandesCocktail->count() }}
            </div>
          </div>
        </button>
      </div>
    </div>

    <div class="card" id="panelCommandesEnGros" @if ($openSection !== 'en_gros') style="display: none;" @endif>
      <div class="card-header">
        <h5 class="mb-0">Commandes avec lignes en gros</h5>
      </div>
      @include('commandes._table', [
        'commandes' => $commandesEnGros,
        'fmt' => $fmt,
        'section' => 'en_gros',
        'emptyMessage' => 'Aucune commande avec des lignes en gros.',
      ])
    </div>

    <div class="card" id="panelCommandesDetail" @if ($openSection !== 'detail') style="display: none;" @endif>
      <div class="card-header">
        <h5 class="mb-0">Commandes avec lignes en détail</h5>
      </div>
      @include('commandes._table', [
        'commandes' => $commandesDetail,
        'fmt' => $fmt,
        'section' => 'detail',
        'emptyMessage' => 'Aucune commande avec des lignes en détail.',
      ])
    </div>

    <div class="card" id="panelCommandesCocktail" @if ($openSection !== 'cocktail') style="display: none;" @endif>
      <div class="card-header">
        <h5 class="mb-0">Commandes avec cocktail</h5>
      </div>
      @include('commandes._table', [
        'commandes' => $commandesCocktail,
        'fmt' => $fmt,
        'section' => 'cocktail',
        'emptyMessage' => 'Aucune commande cocktail.',
      ])
    </div>

    <div class="modal fade" id="modalNouvelleCommande" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered" role="document">
        <form method="POST" action="{{ route('commandes.store') }}" class="modal-content">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">Ajouter une commande</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
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

              <div class="row g-3 mb-4">
                <div class="col-md-3">
                  <label class="form-label">Date commande <span class="text-danger">*</span></label>
                  <input
                    type="date"
                    name="date_commande"
                    class="form-control @error('date_commande') is-invalid @enderror"
                    value="{{ old('date_commande') }}"
                    required />
                </div>
                <div class="col-md-3">
                  <label class="form-label">Statut</label>
                  <select name="statut" class="form-select" required>
                    <option value="en_attente" @selected(old('statut', 'en_attente') === 'en_attente')>En attente</option>
                    <option value="confirmee" @selected(old('statut') === 'confirmee')>Confirmée</option>
                    <option value="livree" @selected(old('statut') === 'livree')>Livrée</option>
                    <option value="annulee" @selected(old('statut') === 'annulee')>Annulée</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Téléphone <span class="text-danger">*</span></label>
                  <input
                    type="text"
                    name="client_telephone"
                    class="form-control"
                    placeholder="Ex: 07 00 00 00 00"
                    value="{{ old('client_telephone') }}"
                    required />
                </div>
                <div class="col-md-3">
                  <label class="form-label">Nom du client</label>
                  <input
                    type="text"
                    name="client_nom"
                    class="form-control"
                    placeholder="Optionnel"
                    value="{{ old('client_nom') }}" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Commune <span class="text-danger">*</span></label>
                  <select name="commune_id" class="form-select commune-select" required>
                    <option value="" data-frais="0">Sélectionner une commune</option>
                    @foreach ($communes as $commune)
                      <option
                        value="{{ $commune->id }}"
                        data-frais="{{ (float) ($commune->coutLivraison?->montant ?? 0) }}"
                        @selected((string) old('commune_id') === (string) $commune->id)>
                        {{ $commune->nom }}
                      </option>
                    @endforeach
                  </select>
                  @error('commune_id')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label">Coût de livraison</label>
                  <div class="form-control-plaintext fw-semibold text-primary frais-livraison-display">—</div>
                </div>
                <div class="col-12">
                  <label class="form-label">Notes</label>
                  <input
                    type="text"
                    name="notes"
                    class="form-control"
                    placeholder="Optionnel"
                    value="{{ old('notes') }}" />
                </div>
              </div>

              @include('commandes._form_articles', [
                'formKey' => 'create',
                'tableNormaleId' => 'tableLignesCommande',
                'tableCocktailId' => 'tableLignesCocktailCreate',
                'lignesNormales' => $createLignesNormales,
                'groupesCocktail' => $createGroupesCocktail,
              ])
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
          </div>
        </form>
      </div>
    </div>

    @foreach ($allCommandes as $commande)
      @php
        $isThisEdit = (string) $editId === (string) $commande->id;
        $lignesNormalesCommande = $commande->lignes->filter(fn ($l) => $l->quantite_ml === null);
        $groupesDepuisCommande = $commande->lignes
          ->filter(fn ($l) => $l->quantite_ml !== null)
          ->groupBy(fn ($l) => $l->flacon_id.'|'.$l->categorie.'|'.$l->quantite)
          ->map(function ($lignes) use ($commande) {
            $first = $lignes->first();
            return [
              'categorie' => $first->categorie === 'cocktail' ? 'detail' : $first->categorie,
              'flacon_id' => $first->flacon_id,
              'quantite' => $first->quantite,
              'cocktail_id' => $commande->cocktail_id,
              'parfums' => $lignes->map(fn ($l) => [
                'produit_id' => $l->produit_id,
                'quantite_ml' => $l->quantite_ml,
              ])->values()->all(),
            ];
          })
          ->values()
          ->all();
        $editLignesNormales = $isThisEdit && is_array(old('lignes'))
          ? old('lignes')
          : ($lignesNormalesCommande->isNotEmpty()
            ? $lignesNormalesCommande->map(fn ($l) => [
              'categorie' => $l->categorie,
              'produit_id' => $l->produit_id,
              'flacon_id' => $l->flacon_id,
              'quantite' => $l->quantite,
            ])->values()->all()
            : [['categorie' => 'detail', 'produit_id' => '', 'flacon_id' => '', 'quantite' => 1]]);
        $editGroupesCocktail = $isThisEdit && is_array(old('groupes_cocktail'))
          ? old('groupes_cocktail')
          : ($groupesDepuisCommande !== []
            ? $groupesDepuisCommande
            : [[
              'categorie' => 'detail',
              'flacon_id' => '',
              'quantite' => 1,
              'cocktail_id' => '',
              'parfums' => [],
            ]]);
      @endphp

      <div class="modal fade" id="modalEditCommande{{ $commande->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered" role="document">
          <form method="POST" action="{{ route('commandes.update', $commande) }}" class="modal-content">
              @csrf
              @method('PUT')
              <input type="hidden" name="_edit_id" value="{{ $commande->id }}" />
              <input type="hidden" name="section" value="{{ $openSection }}" />
              <div class="modal-header">
                <h5 class="modal-title">Modifier {{ $commande->reference }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
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

                <div class="row g-3 mb-4">
                  <div class="col-md-3">
                    <label class="form-label">Date commande <span class="text-danger">*</span></label>
                    <input
                      type="date"
                      name="date_commande"
                      class="form-control"
                      value="{{ $isThisEdit ? old('date_commande', optional($commande->date_commande)->format('Y-m-d')) : optional($commande->date_commande)->format('Y-m-d') }}"
                      required />
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Statut</label>
                    <select name="statut" class="form-select" required>
                      @php $statutVal = $isThisEdit ? old('statut', $commande->statut) : $commande->statut; @endphp
                      <option value="en_attente" @selected($statutVal === 'en_attente')>En attente</option>
                      <option value="confirmee" @selected($statutVal === 'confirmee')>Confirmée</option>
                      <option value="livree" @selected($statutVal === 'livree')>Livrée</option>
                      <option value="annulee" @selected($statutVal === 'annulee')>Annulée</option>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Téléphone <span class="text-danger">*</span></label>
                    <input
                      type="text"
                      name="client_telephone"
                      class="form-control"
                      value="{{ $isThisEdit ? old('client_telephone', $commande->client_telephone) : $commande->client_telephone }}"
                      required />
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Nom du client</label>
                    <input
                      type="text"
                      name="client_nom"
                      class="form-control"
                      value="{{ $isThisEdit ? old('client_nom', $commande->client_nom) : $commande->client_nom }}" />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Commune <span class="text-danger">*</span></label>
                    @php $communeVal = $isThisEdit ? old('commune_id', $commande->commune_id) : $commande->commune_id; @endphp
                    <select name="commune_id" class="form-select commune-select" required>
                      <option value="" data-frais="0">Sélectionner une commune</option>
                      @foreach ($communes as $commune)
                        <option
                          value="{{ $commune->id }}"
                          data-frais="{{ (float) ($commune->coutLivraison?->montant ?? 0) }}"
                          @selected((string) $communeVal === (string) $commune->id)>
                          {{ $commune->nom }}
                        </option>
                      @endforeach
                      @if ($commande->commune && ! $communes->contains('id', $commande->commune_id))
                        <option
                          value="{{ $commande->commune->id }}"
                          data-frais="{{ (float) $commande->frais_livraison }}"
                          selected>
                          {{ $commande->commune->nom }}
                        </option>
                      @endif
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Coût de livraison</label>
                    <div class="form-control-plaintext fw-semibold text-primary frais-livraison-display">
                      {{ number_format((float) $commande->frais_livraison, 0, ',', ' ') }} FCFA
                    </div>
                  </div>
                  <div class="col-12">
                    <label class="form-label">Notes</label>
                    <input
                      type="text"
                      name="notes"
                      class="form-control"
                      value="{{ $isThisEdit ? old('notes', $commande->notes) : $commande->notes }}" />
                  </div>
                </div>

                @include('commandes._form_articles', [
                  'formKey' => $commande->id,
                  'tableNormaleId' => 'editLignesCommande'.$commande->id,
                  'tableCocktailId' => 'editLignesCocktail'.$commande->id,
                  'lignesNormales' => $editLignesNormales,
                  'groupesCocktail' => $editGroupesCocktail,
                ])
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary">Mettre à jour</button>
              </div>
          </form>
        </div>
      </div>
    @endforeach

    <template id="tplLigneCommande">
      <tr class="ligne-commande">
        <td>
          <select name="lignes[__INDEX__][categorie]" class="form-select" required>
            <option value="en_gros">En gros</option>
            <option value="detail">Détail</option>
          </select>
        </td>
        <td>
          <div class="produit-autocomplete position-relative">
            <input type="hidden" name="lignes[__INDEX__][produit_id]" class="produit-id-input" value="" required>
            <input
              type="text"
              class="form-control produit-search-input"
              value=""
              placeholder="Taper le nom du parfum..."
              autocomplete="off"
              required />
            <div class="produit-suggestions list-group position-absolute start-0 end-0 shadow-sm d-none" style="z-index: 1080; max-height: 220px; overflow-y: auto;"></div>
          </div>
        </td>
        <td>
          <select name="lignes[__INDEX__][flacon_id]" class="form-select" required>
            <option value="">Sélectionner</option>
            @foreach ($flacons as $flacon)
              <option value="{{ $flacon->id }}" data-ml="{{ $flacon->contenance_ml }}">{{ $flacon->label() }}</option>
            @endforeach
          </select>
        </td>
        <td>
          <input type="number" name="lignes[__INDEX__][quantite]" class="form-control" min="1" step="1" value="1" required />
        </td>
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-outline-danger btn-remove-ligne-commande" title="Retirer">
            <i class="bx bx-trash"></i>
          </button>
        </td>
      </tr>
    </template>

    <template id="tplLigneCocktailCommande">
      <tr class="ligne-cocktail-commande">
        <td>
          <div class="produit-autocomplete position-relative">
            <input type="hidden" name="groupes_cocktail[__G__][parfums][__INDEX__][produit_id]" class="produit-id-input" value="">
            <input
              type="text"
              class="form-control produit-search-input"
              value=""
              placeholder="Taper le nom du parfum..."
              autocomplete="off" />
            <div class="produit-suggestions list-group position-absolute start-0 end-0 shadow-sm d-none" style="z-index: 1080; max-height: 220px; overflow-y: auto;"></div>
          </div>
        </td>
        <td>
          <input type="number" name="groupes_cocktail[__G__][parfums][__INDEX__][quantite_ml]" class="form-control cocktail-ml-input" min="0.01" step="0.01" placeholder="Ex: 5" />
        </td>
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-outline-danger btn-remove-ligne-cocktail" title="Retirer">
            <i class="bx bx-trash"></i>
          </button>
        </td>
      </tr>
    </template>

    <template id="tplLigneCocktailCreateModal">
      <tr class="ligne-cocktail-create">
        <td>
          <div class="produit-autocomplete position-relative">
            <input type="hidden" name="lignes[__INDEX__][produit_id]" class="produit-id-input" value="">
            <input
              type="text"
              class="form-control produit-search-input"
              value=""
              placeholder="Taper le nom du parfum..."
              autocomplete="off" />
            <div class="produit-suggestions list-group position-absolute start-0 end-0 shadow-sm d-none" style="z-index: 1120; max-height: 220px; overflow-y: auto;"></div>
          </div>
        </td>
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-outline-danger btn-remove-ligne-create-cocktail" title="Retirer">
            <i class="bx bx-trash"></i>
          </button>
        </td>
      </tr>
    </template>

    <div class="modal fade" id="modalCreerCocktailCommande" tabindex="-1" aria-hidden="true" data-store-url="{{ route('commandes.cocktails.store') }}">
      <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Nouveau cocktail</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div id="creerCocktailCommandeErrors" class="alert alert-danger d-none"></div>
            <div class="mb-3">
              <label class="form-label">Nom du cocktail <span class="text-danger">*</span></label>
              <input type="text" id="creerCocktailNom" class="form-control" placeholder="Ex: Mix été" />
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0">Parfums qui le composent</h6>
              <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddLigneCreateCocktail">
                <i class="bx bx-plus me-1"></i>Ajouter un parfum
              </button>
            </div>
            <div class="table-responsive">
              <table class="table" id="tableCreerCocktailCommande">
                <thead>
                  <tr>
                    <th>Parfum <span class="text-danger">*</span></th>
                    <th style="width: 60px;"></th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="button" class="btn btn-primary" id="btnSaveCreerCocktailCommande">Enregistrer</button>
          </div>
        </div>
      </div>
    </div>

    <style>
      .groupe-cocktail.cocktail-locked .btn-add-ligne-cocktail,
      .groupe-cocktail.cocktail-locked .btn-remove-ligne-cocktail {
        display: none;
      }
      .groupe-cocktail.cocktail-locked .produit-search-input {
        background-color: #f5f5f9;
        pointer-events: none;
      }
      .cocktail-nom-field {
        position: relative;
        z-index: 20;
      }
      .cocktail-autocomplete {
        z-index: 21;
      }
      .cocktail-suggestions {
        display: none;
        z-index: 30;
        top: 100%;
        margin-top: 2px;
        max-height: 240px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid rgba(67, 89, 113, .2);
        border-radius: .375rem;
      }
      .cocktail-suggestions:not(.d-none) {
        display: block;
      }
      .cocktail-suggestions .list-group-item {
        background: #fff;
      }
      .cocktail-create-item {
        font-weight: 600;
      }
      #modalCreerCocktailCommande {
        z-index: 1100;
      }
    </style>

    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var produitsCatalog = @json($produits->map(fn ($p) => ['id' => $p->id, 'nom' => $p->nom])->values());

        function formatFr(n) {
          var v = Math.round(Number(n) || 0);
          return String(v).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        }

        function normalizeText(value) {
          return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
        }

        function hideSuggestions(box) {
          if (!box) return;
          box.classList.add('d-none');
          box.innerHTML = '';
        }

        function selectProduit(wrap, produit) {
          var idInput = wrap.querySelector('.produit-id-input');
          var searchInput = wrap.querySelector('.produit-search-input');
          var box = wrap.querySelector('.produit-suggestions');
          if (idInput) idInput.value = String(produit.id);
          if (searchInput) searchInput.value = produit.nom;
          hideSuggestions(box);
        }

        function escapeHtml(value) {
          return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
        }

        function renderSuggestions(wrap, query) {
          var box = wrap.querySelector('.produit-suggestions');
          if (!box) return;

          var q = normalizeText(query);
          if (q.length < 1) {
            hideSuggestions(box);
            return;
          }

          var matches = produitsCatalog
            .filter(function (p) { return normalizeText(p.nom).indexOf(q) !== -1; })
            .slice(0, 12);

          if (!matches.length) {
            box.innerHTML = '<div class="list-group-item text-muted small">Aucun parfum trouvé</div>';
            box.classList.remove('d-none');
            return;
          }

          box.innerHTML = matches.map(function (p) {
            return '<button type="button" class="list-group-item list-group-item-action produit-suggestion-item" data-id="' + escapeHtml(p.id) + '" data-nom="' + escapeHtml(p.nom) + '">' + escapeHtml(p.nom) + '</button>';
          }).join('');
          box.classList.remove('d-none');
        }

        function bindProduitAutocomplete(root) {
          (root || document).querySelectorAll('.produit-autocomplete').forEach(function (wrap) {
            if (wrap.dataset.bound === '1') return;
            wrap.dataset.bound = '1';

            var searchInput = wrap.querySelector('.produit-search-input');
            var idInput = wrap.querySelector('.produit-id-input');
            var box = wrap.querySelector('.produit-suggestions');
            if (!searchInput || !idInput || !box) return;

            searchInput.addEventListener('input', function () {
              idInput.value = '';
              renderSuggestions(wrap, searchInput.value);
            });

            searchInput.addEventListener('focus', function () {
              if (searchInput.value.trim() !== '') {
                renderSuggestions(wrap, searchInput.value);
              }
            });

            searchInput.addEventListener('keydown', function (e) {
              if (e.key === 'Escape') hideSuggestions(box);
            });

            box.addEventListener('mousedown', function (e) {
              var item = e.target.closest('.produit-suggestion-item');
              if (!item) return;
              e.preventDefault();
              selectProduit(wrap, {
                id: item.getAttribute('data-id'),
                nom: item.getAttribute('data-nom')
              });
            });

            searchInput.addEventListener('blur', function () {
              setTimeout(function () {
                hideSuggestions(box);
                if (!idInput.value && searchInput.value.trim() !== '') {
                  var q = normalizeText(searchInput.value);
                  var exact = produitsCatalog.find(function (p) {
                    return normalizeText(p.nom) === q;
                  });
                  if (exact) {
                    selectProduit(wrap, exact);
                  }
                }
              }, 150);
            });
          });
        }

        bindProduitAutocomplete(document);

        var cocktailsCatalog = @json($cocktailsCatalog ?? []);

        var tplNormale = document.getElementById('tplLigneCommande').innerHTML;
        var tplCocktail = document.getElementById('tplLigneCocktailCommande').innerHTML;
        var tplCocktailCreate = document.getElementById('tplLigneCocktailCreateModal').innerHTML;
        var modalCreerEl = document.getElementById('modalCreerCocktailCommande');
        var modalCreer = modalCreerEl && window.bootstrap ? new bootstrap.Modal(modalCreerEl) : null;
        var cocktailGroupeCible = null;

        function formatMl(value) {
          var n = Number(value);
          if (!isFinite(n)) return '0';
          return String(Math.round(n * 100) / 100).replace('.', ',');
        }

        function cocktailEstRempli(groupe) {
          var idInput = groupe.querySelector('.cocktail-id-input');
          if (idInput && String(idInput.value || '').trim() !== '') return true;
          return Array.from(groupe.querySelectorAll('.cocktail-composition .produit-id-input')).some(function (input) {
            return String(input.value || '').trim() !== '';
          });
        }

        function showCocktailComposition(groupe, show) {
          var empty = groupe.querySelector('.cocktail-composition-empty');
          var composition = groupe.querySelector('.cocktail-composition');
          if (empty) empty.classList.toggle('d-none', !!show);
          if (composition) composition.classList.toggle('d-none', !show);
        }

        function syncCocktailVolume(groupe) {
          var recap = groupe.querySelector('.cocktail-volume-recap');
          if (!recap) return;
          var flaconSelect = groupe.querySelector('.cocktail-flacon-select');
          var opt = flaconSelect ? flaconSelect.options[flaconSelect.selectedIndex] : null;
          var max = opt ? Number(opt.getAttribute('data-ml') || 0) : 0;
          var used = 0;
          groupe.querySelectorAll('.cocktail-ml-input').forEach(function (input) {
            used += Number(input.value || 0);
          });
          used = Math.round(used * 100) / 100;
          var usedEl = recap.querySelector('.cocktail-volume-used');
          var maxEl = recap.querySelector('.cocktail-volume-max');
          var restEl = recap.querySelector('.cocktail-volume-restant');
          if (usedEl) usedEl.textContent = formatMl(used);
          if (maxEl) maxEl.textContent = max > 0 ? formatMl(max) : '—';
          recap.classList.remove('alert-info', 'alert-danger', 'alert-success');
          if (!cocktailEstRempli(groupe)) {
            recap.classList.add('alert-info');
            if (restEl) restEl.textContent = 'Optionnel si la commande n’a que des articles normaux.';
            recap.dataset.over = '0';
            return;
          }
          if (!(max > 0)) {
            recap.classList.add('alert-info');
            if (restEl) restEl.textContent = 'Choisissez une contenance.';
            recap.dataset.over = '0';
            return;
          }
          var diff = Math.round((used - max) * 100) / 100;
          if (Math.abs(diff) < 0.01) {
            recap.classList.add('alert-success');
            if (restEl) restEl.textContent = 'Volume exact.';
            recap.dataset.over = '0';
          } else if (diff > 0) {
            recap.classList.add('alert-danger');
            if (restEl) restEl.textContent = 'Dépassement de ' + formatMl(diff) + ' ml. Le volume doit être exactement ' + formatMl(max) + ' ml.';
            recap.dataset.over = '1';
          } else {
            recap.classList.add('alert-danger');
            if (restEl) restEl.textContent = 'Il manque ' + formatMl(-diff) + ' ml. Le volume doit être exactement ' + formatMl(max) + ' ml.';
            recap.dataset.over = '1';
          }
        }

        function upsertCocktailCatalog(cocktail) {
          var idx = cocktailsCatalog.findIndex(function (c) { return String(c.id) === String(cocktail.id); });
          if (idx >= 0) cocktailsCatalog[idx] = cocktail;
          else cocktailsCatalog.push(cocktail);
        }

        function findCocktailByNom(nom) {
          var q = normalizeText(nom);
          if (!q) return null;
          return cocktailsCatalog.find(function (c) { return normalizeText(c.nom) === q; }) || null;
        }

        function applyCocktailToGroupe(groupe, cocktail) {
          if (!groupe || !cocktail) return;
          var idInput = groupe.querySelector('.cocktail-id-input');
          var searchInput = groupe.querySelector('.cocktail-search-input');
          var tbody = groupe.querySelector('.cocktail-composition tbody');
          if (idInput) idInput.value = String(cocktail.id);
          if (searchInput) {
            searchInput.value = cocktail.nom;
            searchInput.classList.remove('is-invalid');
          }
          if (!tbody) return;
          var gIndex = groupe.getAttribute('data-index') || '0';
          tbody.innerHTML = '';
          (cocktail.lignes || []).forEach(function (ligne, index) {
            tbody.insertAdjacentHTML('beforeend', tplCocktail.replaceAll('__G__', gIndex).replaceAll('__INDEX__', String(index)));
            var row = tbody.querySelectorAll('.ligne-cocktail-commande')[index];
            if (!row) return;
            var pid = row.querySelector('.produit-id-input');
            var psearch = row.querySelector('.produit-search-input');
            var mlInput = row.querySelector('.cocktail-ml-input');
            if (pid) pid.value = String(ligne.produit_id);
            if (psearch) {
              psearch.value = ligne.nom || '';
              psearch.readOnly = true;
            }
            if (mlInput) mlInput.value = '';
          });
          groupe.classList.add('cocktail-locked');
          showCocktailComposition(groupe, true);
          bindProduitAutocomplete(tbody);
          syncCocktailVolume(groupe);
        }

        function clearCocktailComposition(groupe) {
          var tbody = groupe.querySelector('.cocktail-composition tbody');
          if (tbody) tbody.innerHTML = '';
          groupe.classList.remove('cocktail-locked');
          showCocktailComposition(groupe, false);
          syncCocktailVolume(groupe);
        }

        function resetCreateCocktailModal(nom) {
          var errors = document.getElementById('creerCocktailCommandeErrors');
          var nomInput = document.getElementById('creerCocktailNom');
          var tbody = document.querySelector('#tableCreerCocktailCommande tbody');
          if (errors) {
            errors.classList.add('d-none');
            errors.innerHTML = '';
          }
          if (nomInput) nomInput.value = nom || '';
          if (!tbody) return;
          tbody.innerHTML = '';
          tbody.insertAdjacentHTML('beforeend', tplCocktailCreate.replaceAll('__INDEX__', '0'));
          tbody.insertAdjacentHTML('beforeend', tplCocktailCreate.replaceAll('__INDEX__', '1'));
          tbody.querySelectorAll('.produit-autocomplete').forEach(function (wrap) {
            delete wrap.dataset.bound;
          });
          bindProduitAutocomplete(tbody);
        }

        function openCreateCocktailModal(groupe, nom) {
          cocktailGroupeCible = groupe;
          resetCreateCocktailModal(nom);
          if (modalCreerEl) {
            modalCreerEl.addEventListener('shown.bs.modal', function onShown() {
              var backdrops = document.querySelectorAll('.modal-backdrop');
              if (backdrops.length) backdrops[backdrops.length - 1].style.zIndex = '1095';
              var nomInput = document.getElementById('creerCocktailNom');
              if (nomInput) nomInput.focus();
              modalCreerEl.removeEventListener('shown.bs.modal', onShown);
            });
          }
          if (modalCreer) modalCreer.show();
        }

        function collectCreateCocktailLignes() {
          var lignes = [];
          document.querySelectorAll('#tableCreerCocktailCommande .ligne-cocktail-create').forEach(function (row) {
            var idInput = row.querySelector('.produit-id-input');
            if (!idInput || !idInput.value) return;
            lignes.push({ produit_id: Number(idInput.value) });
          });
          return lignes;
        }

        function bindCocktailAutocomplete(root) {
          (root || document).querySelectorAll('.cocktail-autocomplete').forEach(function (wrap) {
            if (wrap.dataset.bound === '1') return;
            wrap.dataset.bound = '1';

            var searchInput = wrap.querySelector('.cocktail-search-input');
            var idInput = wrap.querySelector('.cocktail-id-input');
            var box = wrap.querySelector('.cocktail-suggestions');
            if (!searchInput || !idInput || !box) return;

            function renderCocktailSuggestions(query) {
              var q = normalizeText(query);
              if (q.length < 1) {
                hideSuggestions(box);
                return;
              }
              var matches = cocktailsCatalog
                .filter(function (c) { return normalizeText(c.nom).indexOf(q) !== -1; })
                .slice(0, 12);
              var exact = matches.some(function (c) { return normalizeText(c.nom) === q; });
              var html = matches.map(function (c) {
                return '<button type="button" class="list-group-item list-group-item-action cocktail-suggestion-item" data-id="' + escapeHtml(c.id) + '">' + escapeHtml(c.nom) + '</button>';
              }).join('');
              if (!exact) {
                html += '<button type="button" class="list-group-item list-group-item-action cocktail-create-item text-primary" data-nom="' + escapeHtml(query.trim()) + '"><i class="bx bx-plus me-1"></i>Créer le cocktail « ' + escapeHtml(query.trim()) + ' »</button>';
              }
              if (!html) {
                html = '<div class="list-group-item text-muted small">Aucun cocktail trouvé</div>';
              }
              box.innerHTML = html;
              box.classList.remove('d-none');
            }

            searchInput.addEventListener('input', function () {
              idInput.value = '';
              var groupe = wrap.closest('.groupe-cocktail');
              if (groupe) {
                groupe.classList.remove('cocktail-locked');
                clearCocktailComposition(groupe);
              }
              renderCocktailSuggestions(searchInput.value);
            });

            searchInput.addEventListener('focus', function () {
              if (searchInput.value.trim() !== '') {
                renderCocktailSuggestions(searchInput.value);
              }
            });

            searchInput.addEventListener('keydown', function (e) {
              if (e.key === 'Escape') {
                hideSuggestions(box);
                return;
              }
              if (e.key !== 'Enter') return;
              e.preventDefault();
              var nom = searchInput.value.trim();
              if (!nom) return;
              var exact = findCocktailByNom(nom);
              var groupe = wrap.closest('.groupe-cocktail');
              hideSuggestions(box);
              if (exact) applyCocktailToGroupe(groupe, exact);
              else openCreateCocktailModal(groupe, nom);
            });

            box.addEventListener('mousedown', function (e) {
              var createItem = e.target.closest('.cocktail-create-item');
              if (createItem) {
                e.preventDefault();
                hideSuggestions(box);
                openCreateCocktailModal(wrap.closest('.groupe-cocktail'), createItem.getAttribute('data-nom') || searchInput.value.trim());
                return;
              }
              var item = e.target.closest('.cocktail-suggestion-item');
              if (!item) return;
              e.preventDefault();
              var cocktail = cocktailsCatalog.find(function (c) { return String(c.id) === String(item.getAttribute('data-id')); });
              hideSuggestions(box);
              if (cocktail) applyCocktailToGroupe(wrap.closest('.groupe-cocktail'), cocktail);
            });

            searchInput.addEventListener('blur', function () {
              setTimeout(function () {
                hideSuggestions(box);
                if (idInput.value || searchInput.value.trim() === '') return;
                var exact = findCocktailByNom(searchInput.value);
                if (exact) applyCocktailToGroupe(wrap.closest('.groupe-cocktail'), exact);
              }, 150);
            });
          });
        }

        bindCocktailAutocomplete(document);

        if (modalCreerEl) {
          modalCreerEl.addEventListener('hidden.bs.modal', function () {
            if (document.querySelector('.modal.show')) {
              document.body.classList.add('modal-open');
            }
          });
        }

        function nextGroupeIndex(container) {
          var max = -1;
          container.querySelectorAll('.groupe-cocktail').forEach(function (groupe) {
            var i = parseInt(groupe.getAttribute('data-index') || '0', 10);
            if (!isNaN(i) && i > max) max = i;
          });
          return max + 1;
        }

        function reindexGroupeCocktail(groupe, gIndex) {
          groupe.setAttribute('data-index', String(gIndex));
          groupe.querySelectorAll('[name]').forEach(function (el) {
            el.name = el.name.replace(/groupes_cocktail\[\d+\]/, 'groupes_cocktail[' + gIndex + ']');
          });
          var table = groupe.querySelector('.cocktail-composition table');
          var addBtn = groupe.querySelector('.btn-add-ligne-cocktail');
          if (table) {
            var newId = 'tableLignesCocktailDyn' + Date.now() + '_' + gIndex;
            table.id = newId;
            if (addBtn) addBtn.setAttribute('data-target', newId);
          }
          if (addBtn) addBtn.setAttribute('data-groupe', String(gIndex));
        }

        function resetGroupeCocktail(groupe) {
          groupe.querySelectorAll('select').forEach(function (select) {
            if (select.options.length) select.selectedIndex = 0;
            select.value = select.options.length ? select.options[0].value : '';
          });
          groupe.querySelectorAll('input[type="number"]').forEach(function (input) {
            if ((input.name || '').indexOf('[quantite]') !== -1 && (input.name || '').indexOf('[parfums]') === -1) {
              input.value = '1';
            } else {
              input.value = '';
            }
          });
          var idInput = groupe.querySelector('.cocktail-id-input');
          var searchInput = groupe.querySelector('.cocktail-search-input');
          if (idInput) idInput.value = '';
          if (searchInput) {
            searchInput.value = '';
            searchInput.classList.remove('is-invalid');
          }
          clearCocktailComposition(groupe);
        }

        document.querySelectorAll('form.modal-content').forEach(function (form) {
          form.querySelectorAll('.groupe-cocktail').forEach(function (groupe) {
            syncCocktailVolume(groupe);
          });
          form.addEventListener('input', function (e) {
            if (e.target && e.target.classList.contains('cocktail-ml-input')) {
              var groupe = e.target.closest('.groupe-cocktail');
              if (groupe) syncCocktailVolume(groupe);
            }
          });
          form.addEventListener('change', function (e) {
            if (e.target && e.target.classList.contains('cocktail-flacon-select')) {
              var groupeFlacon = e.target.closest('.groupe-cocktail');
              if (groupeFlacon) syncCocktailVolume(groupeFlacon);
            }
          });

          form.addEventListener('submit', function (e) {
            var hasNormal = false;
            var hasCocktail = false;
            var invalid = false;

            form.querySelectorAll('.commande-mode-normale .ligne-commande').forEach(function (row) {
              var idInput = row.querySelector('.produit-id-input');
              var searchInput = row.querySelector('.produit-search-input');
              var typed = searchInput && searchInput.value.trim() !== '';
              if (!typed && (!idInput || !idInput.value)) return;
              if (idInput && !idInput.value) {
                invalid = true;
                if (searchInput) searchInput.classList.add('is-invalid');
              } else {
                hasNormal = true;
                if (searchInput) searchInput.classList.remove('is-invalid');
              }
            });

            form.querySelectorAll('.groupe-cocktail').forEach(function (groupe) {
              var nomInput = groupe.querySelector('.cocktail-search-input');
              var cocktailIdInput = groupe.querySelector('.cocktail-id-input');
              var nomTape = nomInput && nomInput.value.trim() !== '';
              if (nomTape && (!cocktailIdInput || !cocktailIdInput.value) && !cocktailEstRempli(groupe)) {
                invalid = true;
                openCreateCocktailModal(groupe, nomInput.value.trim());
                return;
              }
              if (!cocktailEstRempli(groupe)) return;
              hasCocktail = true;
              var parfums = 0;
              groupe.querySelectorAll('.ligne-cocktail-commande').forEach(function (row) {
                var idInput = row.querySelector('.produit-id-input');
                var searchInput = row.querySelector('.produit-search-input');
                var typed = searchInput && searchInput.value.trim() !== '';
                if (!typed && (!idInput || !idInput.value)) return;
                if (idInput && !idInput.value) {
                  invalid = true;
                  if (searchInput) searchInput.classList.add('is-invalid');
                } else {
                  parfums += 1;
                  if (searchInput) searchInput.classList.remove('is-invalid');
                }
              });
              var recap = groupe.querySelector('.cocktail-volume-recap');
              var flaconSelect = groupe.querySelector('.cocktail-flacon-select');
              if (!flaconSelect || !flaconSelect.value) {
                invalid = true;
                alert('Sélectionnez la contenance du cocktail.');
              }
              if (parfums < 2) {
                invalid = true;
                alert('Un cocktail doit associer au moins deux parfums.');
              }
              if (recap && recap.dataset.over === '1') {
                invalid = true;
                alert('Le volume total des parfums doit être exactement égal à la contenance du flacon.');
              }
            });

            if (invalid) {
              e.preventDefault();
              return;
            }
            if (!hasNormal && !hasCocktail) {
              e.preventDefault();
              alert('Ajoutez au moins un parfum normal ou un cocktail.');
            }
          });
        });

        function bindCommuneSelect(select) {
          if (!select) return;
          var display = select.closest('.row, form').querySelector('.frais-livraison-display');
          function sync() {
            var opt = select.options[select.selectedIndex];
            var frais = opt ? Number(opt.getAttribute('data-frais') || 0) : 0;
            if (display) {
              display.textContent = select.value ? (formatFr(frais) + ' FCFA') : '—';
            }
          }
          select.addEventListener('change', sync);
          sync();
        }

        document.querySelectorAll('.commune-select').forEach(bindCommuneSelect);

        var sectionInput = document.querySelector('input[name="section"]');
        var panels = {
          en_gros: document.getElementById('panelCommandesEnGros'),
          detail: document.getElementById('panelCommandesDetail'),
          cocktail: document.getElementById('panelCommandesCocktail')
        };
        var cards = document.querySelectorAll('.commande-section-card');
        var sectionStyles = {
          en_gros: { active: 'linear-gradient(135deg, #03c3ec, #0aa2c0)', idle: '#e7f8fc', color: 'text-info' },
          detail: { active: 'linear-gradient(135deg, #696cff, #5a5fe0)', idle: '#efefff', color: 'text-primary' },
          cocktail: { active: 'linear-gradient(135deg, #ff9f43, #ee8133)', idle: '#fff4e6', color: 'text-warning' }
        };

        function setSection(section) {
          if (sectionInput) sectionInput.value = section;
          Object.keys(panels).forEach(function (key) {
            if (panels[key]) panels[key].style.display = key === section ? '' : 'none';
          });

          cards.forEach(function (card) {
            var key = card.dataset.section;
            var style = sectionStyles[key] || sectionStyles.detail;
            var active = key === section;
            card.style.background = active ? style.active : style.idle;

            var label = card.querySelector('.text-uppercase');
            var title = card.querySelector('h4');
            var count = card.querySelector('.card-body > div:last-child');
            if (label) {
              label.className = 'text-uppercase small fw-semibold mb-1 ' + (active ? 'text-white' : style.color);
              label.style.opacity = '.9';
            }
            if (title) title.className = 'mb-0 ' + (active ? 'text-white' : 'text-heading');
            if (count) {
              count.className = active ? 'text-white' : style.color;
              count.style.fontSize = '2rem';
              count.style.fontWeight = '700';
              count.style.lineHeight = '1';
            }
          });
        }

        cards.forEach(function (card) {
          card.addEventListener('click', function () {
            setSection(card.dataset.section);
          });
        });

        document.addEventListener('click', function (e) {
          var btnAddNormale = e.target.closest('.btn-add-ligne-normale');
          if (btnAddNormale) {
            var tableN = document.getElementById(btnAddNormale.getAttribute('data-target'));
            if (!tableN) return;
            var tbodyAddN = tableN.querySelector('tbody');
            var indexN = tbodyAddN.querySelectorAll('.ligne-commande').length;
            tbodyAddN.insertAdjacentHTML('beforeend', tplNormale.replaceAll('__INDEX__', String(indexN)));
            bindProduitAutocomplete(tbodyAddN);
            return;
          }

          var btnAddCocktail = e.target.closest('.btn-add-ligne-cocktail');
          if (btnAddCocktail) {
            var tableC = document.getElementById(btnAddCocktail.getAttribute('data-target'));
            if (!tableC) return;
            var tbodyAddC = tableC.querySelector('tbody');
            var groupeAdd = btnAddCocktail.closest('.groupe-cocktail');
            var gIndexAdd = (groupeAdd && groupeAdd.getAttribute('data-index')) || btnAddCocktail.getAttribute('data-groupe') || '0';
            var indexC = tbodyAddC.querySelectorAll('.ligne-cocktail-commande').length;
            tbodyAddC.insertAdjacentHTML('beforeend', tplCocktail.replaceAll('__G__', gIndexAdd).replaceAll('__INDEX__', String(indexC)));
            bindProduitAutocomplete(tbodyAddC);
            if (groupeAdd) syncCocktailVolume(groupeAdd);
            return;
          }

          var btnAddGroupe = e.target.closest('.btn-add-groupe-cocktail');
          if (btnAddGroupe) {
            var formAdd = btnAddGroupe.closest('form');
            var container = formAdd ? formAdd.querySelector('.groupes-cocktail') : null;
            var source = container ? container.querySelector('.groupe-cocktail') : null;
            if (!container || !source) return;
            var clone = source.cloneNode(true);
            clone.querySelectorAll('.produit-autocomplete, .cocktail-autocomplete').forEach(function (wrap) {
              delete wrap.dataset.bound;
            });
            reindexGroupeCocktail(clone, nextGroupeIndex(container));
            container.appendChild(clone);
            resetGroupeCocktail(clone);
            bindCocktailAutocomplete(clone);
            return;
          }

          var btnNormale = e.target.closest('.btn-remove-ligne-commande');
          if (btnNormale) {
            var tbodyN = btnNormale.closest('tbody');
            if (!tbodyN) return;
            if (tbodyN.querySelectorAll('.ligne-commande').length <= 1) return;
            btnNormale.closest('tr').remove();
            return;
          }

          var btnRemoveGroupe = e.target.closest('.btn-remove-groupe-cocktail');
          if (btnRemoveGroupe) {
            var groupeRm = btnRemoveGroupe.closest('.groupe-cocktail');
            var containerRm = btnRemoveGroupe.closest('.groupes-cocktail');
            if (!groupeRm || !containerRm) return;
            if (containerRm.querySelectorAll('.groupe-cocktail').length <= 1) {
              resetGroupeCocktail(groupeRm);
              return;
            }
            groupeRm.remove();
            return;
          }

          var btnRemoveCreate = e.target.closest('.btn-remove-ligne-create-cocktail');
          if (btnRemoveCreate) {
            var tbodyCreate = btnRemoveCreate.closest('tbody');
            if (!tbodyCreate) return;
            if (tbodyCreate.querySelectorAll('.ligne-cocktail-create').length <= 2) return;
            btnRemoveCreate.closest('tr').remove();
            return;
          }

          var btnCocktail = e.target.closest('.btn-remove-ligne-cocktail');
          if (!btnCocktail) return;
          var tbodyC = btnCocktail.closest('tbody');
          if (!tbodyC) return;
          if (tbodyC.querySelectorAll('.ligne-cocktail-commande').length <= 2) return;
          var groupe = btnCocktail.closest('.groupe-cocktail');
          btnCocktail.closest('tr').remove();
          if (groupe) syncCocktailVolume(groupe);
        });

        var csrfToken = document.querySelector('meta[name="csrf-token"]');
        csrfToken = csrfToken ? csrfToken.getAttribute('content') : '';

        var btnAddCreateRow = document.getElementById('btnAddLigneCreateCocktail');
        if (btnAddCreateRow) {
          btnAddCreateRow.addEventListener('click', function () {
            var tbody = document.querySelector('#tableCreerCocktailCommande tbody');
            if (!tbody) return;
            var index = tbody.querySelectorAll('.ligne-cocktail-create').length;
            tbody.insertAdjacentHTML('beforeend', tplCocktailCreate.replaceAll('__INDEX__', String(index)));
            bindProduitAutocomplete(tbody);
          });
        }

        var btnSaveCreate = document.getElementById('btnSaveCreerCocktailCommande');
        if (btnSaveCreate) {
          btnSaveCreate.addEventListener('click', function () {
            var errorsBox = document.getElementById('creerCocktailCommandeErrors');
            var nomInput = document.getElementById('creerCocktailNom');
            var nom = nomInput ? nomInput.value.trim() : '';
            var lignes = collectCreateCocktailLignes();
            var messages = [];
            if (!nom) messages.push('Indiquez le nom du cocktail.');
            if (lignes.length < 2) messages.push('Ajoutez au moins deux parfums.');
            var unique = {};
            lignes.forEach(function (l) { unique[l.produit_id] = true; });
            if (Object.keys(unique).length < 2) messages.push('Un cocktail doit associer au moins deux parfums différents.');
            if (messages.length) {
              if (errorsBox) {
                errorsBox.innerHTML = messages.filter(function (m, i, arr) { return arr.indexOf(m) === i; }).join('<br>');
                errorsBox.classList.remove('d-none');
              }
              return;
            }

            var storeUrl = modalCreerEl ? modalCreerEl.getAttribute('data-store-url') : '';
            btnSaveCreate.disabled = true;
            fetch(storeUrl, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
              },
              body: JSON.stringify({ nom: nom, lignes: lignes })
            })
              .then(function (res) {
                return res.json().then(function (data) {
                  return { ok: res.ok, status: res.status, data: data };
                });
              })
              .then(function (result) {
                btnSaveCreate.disabled = false;
                if (!result.ok) {
                  var errs = [];
                  if (result.data && result.data.errors) {
                    Object.keys(result.data.errors).forEach(function (key) {
                      errs = errs.concat(result.data.errors[key]);
                    });
                  } else if (result.data && result.data.message) {
                    errs.push(result.data.message);
                  } else {
                    errs.push('Impossible d’enregistrer le cocktail.');
                  }
                  if (errorsBox) {
                    errorsBox.innerHTML = errs.join('<br>');
                    errorsBox.classList.remove('d-none');
                  }
                  return;
                }
                upsertCocktailCatalog(result.data);
                if (cocktailGroupeCible) applyCocktailToGroupe(cocktailGroupeCible, result.data);
                if (modalCreer) modalCreer.hide();
              })
              .catch(function () {
                btnSaveCreate.disabled = false;
                if (errorsBox) {
                  errorsBox.textContent = 'Impossible d’enregistrer le cocktail.';
                  errorsBox.classList.remove('d-none');
                }
              });
          });
        }

        @if (($errors->any() && ! $editId) || $openCreate)
          var createEl = document.getElementById('modalNouvelleCommande');
          if (createEl && window.bootstrap) new bootstrap.Modal(createEl).show();
        @elseif ($editId)
          var editEl = document.getElementById('modalEditCommande{{ $editId }}');
          if (editEl && window.bootstrap) new bootstrap.Modal(editEl).show();
        @endif
      });
    </script>
  </div>
</div>
@endsection
