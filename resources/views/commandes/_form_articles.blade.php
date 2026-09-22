@php
  $formKey = $formKey ?? 'create';
  $tableNormaleId = $tableNormaleId ?? 'tableLignesCommande';
  $tableCocktailId = $tableCocktailId ?? 'tableLignesCocktail';
  $lignesNormales = $lignesNormales ?? [['categorie' => 'detail', 'produit_id' => '', 'flacon_id' => '', 'quantite' => 1]];
  $groupesCocktail = $groupesCocktail ?? [[
    'categorie' => 'detail',
    'flacon_id' => '',
    'quantite' => 1,
    'cocktail_id' => '',
    'parfums' => [],
  ]];
  $cocktails = $cocktails ?? collect();
  $produitsById = $produitsById ?? collect();
@endphp

<div class="alert alert-secondary mb-4">
  Une même commande peut regrouper des <strong>articles normaux</strong> (gros ou détail)
  et des <strong>cocktails</strong> (gros ou détail). Remplissez uniquement les blocs utiles.
</div>

<div class="commande-mode-normale mb-4">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <div>
      <h6 class="mb-0">Articles normaux (gros / détail)</h6>
      <div class="form-text">Parfum vendu seul, au tarif gros ou détail. Laissez vide s’il n’y a que des cocktails.</div>
    </div>
    <button type="button" class="btn btn-sm btn-outline-primary btn-add-ligne-normale" data-target="{{ $tableNormaleId }}">
      <i class="bx bx-plus me-1"></i>Ajouter un parfum
    </button>
  </div>

  <div class="table-responsive">
    <table class="table" id="{{ $tableNormaleId }}">
      <thead>
        <tr>
          <th style="min-width: 140px;">Catégorie</th>
          <th style="min-width: 220px;">Parfum</th>
          <th style="min-width: 140px;">Contenance</th>
          <th style="min-width: 100px;">Qté</th>
          <th style="width: 60px;"></th>
        </tr>
      </thead>
      <tbody>
        @foreach ($lignesNormales as $index => $ligne)
          <tr class="ligne-commande">
            <td>
              <select name="lignes[{{ $index }}][categorie]" class="form-select">
                <option value="en_gros" @selected(($ligne['categorie'] ?? 'detail') === 'en_gros')>En gros</option>
                <option value="detail" @selected(($ligne['categorie'] ?? 'detail') === 'detail')>Détail</option>
              </select>
            </td>
            <td>
              @php
                $pid = $ligne['produit_id'] ?? '';
                $pnom = $pid !== '' ? ($produitsById->get($pid)?->nom ?? '') : '';
              @endphp
              <div class="produit-autocomplete position-relative">
                <input type="hidden" name="lignes[{{ $index }}][produit_id]" class="produit-id-input" value="{{ $pid }}">
                <input
                  type="text"
                  class="form-control produit-search-input"
                  value="{{ $pnom }}"
                  placeholder="Taper le nom du parfum..."
                  autocomplete="off" />
                <div class="produit-suggestions list-group position-absolute start-0 end-0 shadow-sm d-none" style="z-index: 1080; max-height: 220px; overflow-y: auto;"></div>
              </div>
            </td>
            <td>
              <select name="lignes[{{ $index }}][flacon_id]" class="form-select">
                <option value="">Sélectionner</option>
                @foreach ($flacons as $flacon)
                  <option value="{{ $flacon->id }}" data-ml="{{ $flacon->contenance_ml }}" @selected((string) ($ligne['flacon_id'] ?? '') === (string) $flacon->id)>
                    {{ $flacon->label() }}
                  </option>
                @endforeach
              </select>
            </td>
            <td>
              <input
                type="number"
                name="lignes[{{ $index }}][quantite]"
                class="form-control"
                min="1"
                step="1"
                value="{{ $ligne['quantite'] ?? 1 }}" />
            </td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-outline-danger btn-remove-ligne-commande" title="Retirer">
                <i class="bx bx-trash"></i>
              </button>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

<div class="commande-mode-cocktail">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <div>
      <h6 class="mb-0">Cocktails (gros / détail)</h6>
      <div class="form-text">Tapez le nom du cocktail : ses parfums s’affichent et vous choisissez les quantités. Le total en ml doit être exactement égal à la contenance du flacon.</div>
    </div>
    <button type="button" class="btn btn-sm btn-outline-warning btn-add-groupe-cocktail">
      <i class="bx bx-plus me-1"></i>Ajouter un cocktail
    </button>
  </div>

  <div class="groupes-cocktail">
  @foreach ($groupesCocktail as $gIndex => $groupe)
    @php
      $categorieCocktail = in_array($groupe['categorie'] ?? '', ['en_gros', 'detail'], true) ? $groupe['categorie'] : 'detail';
      $parfums = $groupe['parfums'] ?? [];
      $tableId = $tableCocktailId.$gIndex;
      $hasComposition = collect($parfums)->contains(fn ($p) => filled($p['produit_id'] ?? null));
      $cocktailNom = $groupe['cocktail_nom'] ?? '';
      if ($cocktailNom === '' && filled($groupe['cocktail_id'] ?? null)) {
        $cocktailNom = $cocktails->firstWhere('id', (int) $groupe['cocktail_id'])?->nom ?? '';
      }
    @endphp
    <div class="card border mb-3 groupe-cocktail {{ filled($groupe['cocktail_id'] ?? null) ? 'cocktail-locked' : '' }}" data-index="{{ $gIndex }}">
      <div class="card-body">
        <div class="d-flex justify-content-end mb-2">
          <button type="button" class="btn btn-sm btn-outline-danger btn-remove-groupe-cocktail" title="Retirer ce cocktail">
            <i class="bx bx-trash"></i>
          </button>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-12 cocktail-nom-field">
            <label class="form-label">Nom du cocktail</label>
            <div class="cocktail-autocomplete position-relative">
              <input type="hidden" name="groupes_cocktail[{{ $gIndex }}][cocktail_id]" class="cocktail-id-input" value="{{ $groupe['cocktail_id'] ?? '' }}">
              <input
                type="text"
                class="form-control cocktail-search-input"
                value="{{ $cocktailNom }}"
                placeholder="Taper le nom du cocktail..."
                autocomplete="off" />
              <div class="cocktail-suggestions list-group position-absolute start-0 end-0 shadow"></div>
            </div>
            <div class="form-text">Si le cocktail n’existe pas, choisissez « Créer ce cocktail » pour l’enregistrer.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Catégorie</label>
            <select name="groupes_cocktail[{{ $gIndex }}][categorie]" class="form-select">
              <option value="en_gros" @selected($categorieCocktail === 'en_gros')>En gros</option>
              <option value="detail" @selected($categorieCocktail === 'detail')>Détail</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Contenance du flacon</label>
            <select name="groupes_cocktail[{{ $gIndex }}][flacon_id]" class="form-select cocktail-flacon-select">
              <option value="" data-ml="0">Sélectionner</option>
              @foreach ($flacons as $flacon)
                <option value="{{ $flacon->id }}" data-ml="{{ $flacon->contenance_ml }}" @selected((string) ($groupe['flacon_id'] ?? '') === (string) $flacon->id)>
                  {{ $flacon->label() }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Nombre de flacons</label>
            <input
              type="number"
              name="groupes_cocktail[{{ $gIndex }}][quantite]"
              class="form-control"
              min="1"
              step="1"
              value="{{ $groupe['quantite'] ?? 1 }}" />
          </div>
        </div>

        <div class="cocktail-composition-empty text-muted mb-0 {{ $hasComposition ? 'd-none' : '' }}">
          Tapez le nom d’un cocktail pour afficher les parfums qui le composent.
        </div>

        <div class="cocktail-composition {{ $hasComposition ? '' : 'd-none' }}">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div>
            <h6 class="mb-0">Composition</h6>
            <div class="form-text">Indiquez la quantité de chaque parfum pour cette commande.</div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-primary btn-add-ligne-cocktail" data-target="{{ $tableId }}" data-groupe="{{ $gIndex }}">
            <i class="bx bx-plus me-1"></i>Ajouter un parfum
          </button>
        </div>

        <div class="table-responsive">
          <table class="table" id="{{ $tableId }}">
            <thead>
              <tr>
                <th style="min-width: 220px;">Parfum</th>
                <th style="min-width: 140px;">Quantité (ml)</th>
                <th style="width: 60px;"></th>
              </tr>
            </thead>
            <tbody>
              @foreach ($parfums as $index => $ligne)
                <tr class="ligne-cocktail-commande">
                  <td>
                    @php
                      $pid = $ligne['produit_id'] ?? '';
                      $pnom = $pid !== '' ? ($produitsById->get($pid)?->nom ?? '') : '';
                    @endphp
                    <div class="produit-autocomplete position-relative">
                      <input type="hidden" name="groupes_cocktail[{{ $gIndex }}][parfums][{{ $index }}][produit_id]" class="produit-id-input" value="{{ $pid }}">
                      <input
                        type="text"
                        class="form-control produit-search-input"
                        value="{{ $pnom }}"
                        placeholder="Taper le nom du parfum..."
                        autocomplete="off"
                        @if (filled($groupe['cocktail_id'] ?? null)) readonly @endif />
                      <div class="produit-suggestions list-group position-absolute start-0 end-0 shadow-sm d-none" style="z-index: 1080; max-height: 220px; overflow-y: auto;"></div>
                    </div>
                  </td>
                  <td>
                    <input
                      type="number"
                      name="groupes_cocktail[{{ $gIndex }}][parfums][{{ $index }}][quantite_ml]"
                      class="form-control cocktail-ml-input"
                      min="0.01"
                      step="0.01"
                      placeholder="Ex: 5"
                      value="{{ $ligne['quantite_ml'] ?? '' }}" />
                  </td>
                  <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-ligne-cocktail" title="Retirer">
                      <i class="bx bx-trash"></i>
                    </button>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="cocktail-volume-recap alert mb-0">
          Volume du cocktail : <strong class="cocktail-volume-used">0</strong> ml /
          <strong class="cocktail-volume-max">—</strong> ml
          <span class="cocktail-volume-restant ms-2"></span>
        </div>
        </div>
      </div>
    </div>
  @endforeach
  </div>
</div>
