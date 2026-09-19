@php
  $parfumsEcarts = $ecarts->groupBy('produit_id');
  $nbParfums = $parfumsEcarts->count();
  $nbInactifs = $ecarts->where('statut', '!=', 'actif')->unique('produit_id')->count();
  $labels = \App\Models\PrixUnitaire::categories();
@endphp

<div class="card border-warning mb-4" id="ecarts-tarifs">
  <div class="card-header bg-label-warning">
    <h5 class="mb-1">Écarts de tarification — {{ $nbParfums }} parfum(s)</h5>
    <p class="mb-0 small">
      Ces parfums ont un prix d’une catégorie mais pas de l’autre pour la même contenance.
      Les parfums <strong>inactifs</strong> restent comptés dans les totaux tant qu’ils sont associés à un tarif.
      @if ($nbInactifs > 0)
        <strong>{{ $nbInactifs }} inactif(s)</strong> expliquent souvent l’écart des compteurs.
      @endif
    </p>
  </div>
  <div class="card-body p-0">
    @foreach ($parfumsEcarts as $produitId => $lignes)
      @php
        $premier = $lignes->first();
        $estActif = $premier->statut === 'actif';
        $categoriePresente = $premier->categorie_presente;
        $categorieManquante = $premier->categorie_manquante;
        $labelPresent = $labels[$categoriePresente] ?? $categoriePresente;
        $labelManquant = $labels[$categorieManquante] ?? $categorieManquante;
      @endphp
      <div class="border-bottom p-3 {{ $loop->last ? 'border-bottom-0' : '' }}">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
          <div>
            <a href="{{ route('produits.show', ['produit' => $produitId, 'onglet' => 'prix']) }}" class="fw-semibold text-heading">
              {{ $premier->produit_nom }}
            </a>
            <span class="badge {{ $estActif ? 'bg-label-success' : 'bg-label-secondary' }} ms-1">
              {{ $estActif ? 'Actif' : 'Inactif' }}
            </span>
            <div class="small text-muted mt-1">
              Prix {{ strtolower($labelManquant) }} manquant sur
              {{ $lignes->pluck('contenance_ml')->sort()->map(fn ($ml) => $ml.' ml')->join(', ') }}.
            </div>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('produits.show', ['produit' => $produitId, 'onglet' => 'prix']) }}" class="btn btn-sm btn-outline-primary">
              {{ $estActif ? 'Ajouter le prix manquant' : 'Voir la fiche' }}
            </a>
            <form
              method="POST"
              action="{{ route('prix-unitaires.produits.retirer-categorie', $produitId) }}"
            onsubmit="return confirm(@json('Retirer '.$premier->produit_nom.' de tous les tarifs '.strtolower($labelPresent).' ?'));">
              @csrf
              @method('DELETE')
              <input type="hidden" name="categorie" value="{{ $categoriePresente }}" />
              <button type="submit" class="btn btn-sm btn-outline-danger">
                Retirer des tarifs {{ strtolower($labelPresent) }}
              </button>
            </form>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead>
              <tr>
                <th>Contenance</th>
                <th>Présent sur</th>
                <th>Manque</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($lignes as $ligne)
                <tr>
                  <td>{{ $ligne->contenance_ml }} ml</td>
                  <td>
                    <a href="{{ route('prix-unitaires.show', $ligne->prix_id) }}">{{ $ligne->reference }}</a>
                    <span class="text-muted">({{ $labels[$ligne->categorie_presente] ?? $ligne->categorie_presente }})</span>
                  </td>
                  <td>
                    <span class="badge bg-label-warning">Prix {{ strtolower($labels[$ligne->categorie_manquante] ?? $ligne->categorie_manquante) }}</span>
                  </td>
                  <td class="text-end">
                    <form
                      method="POST"
                      action="{{ route('prix-unitaires.produits.detach', [$ligne->prix_id, $ligne->produit_id]) }}"
                      class="d-inline"
                      onsubmit="return confirm(@json('Retirer '.$ligne->produit_nom.' de '.$ligne->reference.' ?'));">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-sm btn-outline-danger">Retirer cette contenance</button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endforeach
  </div>
</div>
