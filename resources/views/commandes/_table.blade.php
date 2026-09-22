<div class="table-responsive text-nowrap">
  <table class="table mb-0">
    <thead>
      <tr>
        <th>Date commande</th>
        <th>Référence</th>
        <th>Articles</th>
        <th>Catégories</th>
        <th>Commune</th>
        <th>Montant</th>
        <th>Coût livraison</th>
        <th>Client</th>
        <th>Téléphone</th>
        <th>Statut</th>
        <th class="text-end">Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($commandes as $commande)
        <tr>
          <td>{{ $commande->date_commande?->format('d/m/Y') ?? $commande->created_at?->format('d/m/Y') }}</td>
          <td class="fw-medium">
            <button
              type="button"
              class="btn btn-link p-0 text-heading fw-medium text-decoration-none"
              data-bs-toggle="collapse"
              data-bs-target="#lignesCommande{{ $section ?? 'all' }}_{{ $commande->id }}"
              aria-expanded="false">
              {{ $commande->reference }}
            </button>
          </td>
          <td>
            <span class="badge bg-label-secondary">{{ $commande->lignes_count ?? $commande->lignes->count() }}</span>
            <span class="text-muted ms-1">{{ $commande->resumeParfums() }}</span>
          </td>
          <td>
            @if ($commande->isMixte())
              <span class="badge bg-label-secondary">Mixte</span>
            @endif
            @if ($commande->hasLignesCocktail())
              <span class="badge bg-label-warning">Cocktail</span>
            @endif
            @foreach ($commande->categoriesPresentes() as $cat)
              @continue($cat === 'cocktail')
              <span class="badge {{ $cat === 'en_gros' ? 'bg-label-info' : 'bg-label-primary' }}">
                {{ $cat === 'en_gros' ? 'En gros' : 'Détail' }}
              </span>
            @endforeach
          </td>
          <td>{{ $commande->commune?->nom ?? '—' }}</td>
          <td class="fw-semibold text-primary">{{ $fmt($commande->montantArticles()) }} FCFA</td>
          <td class="fw-semibold">{{ $fmt($commande->frais_livraison) }} FCFA</td>
          <td>{{ $commande->client_nom ?: '—' }}</td>
          <td>{{ $commande->client_telephone }}</td>
          <td style="min-width: 160px;">
            <form method="POST" action="{{ route('commandes.statut', $commande) }}" class="m-0">
              @csrf
              @method('PATCH')
              <input type="hidden" name="section" value="{{ $section ?? 'en_gros' }}" />
              <select
                name="statut"
                class="form-select form-select-sm border-0 {{ $commande->statutBadgeClass() }}"
                onchange="this.form.submit()"
                title="Changer le statut">
                <option value="en_attente" @selected($commande->statut === 'en_attente')>En attente</option>
                <option value="confirmee" @selected($commande->statut === 'confirmee')>Confirmée</option>
                <option value="livree" @selected($commande->statut === 'livree')>Livrée</option>
                <option value="annulee" @selected($commande->statut === 'annulee')>Annulée</option>
              </select>
            </form>
          </td>
          <td class="text-end text-nowrap">
            @if ($commande->estEnvoyeeVersOvl())
              <span class="badge bg-label-success me-1" title="Envoyée vers OVL{{ $commande->ovl_commande_id ? ' #'.$commande->ovl_commande_id : '' }}">
                <i class="bx bx-check"></i> OVL
              </span>
            @else
              <form method="POST" action="{{ route('commandes.envoyer-ovl', $commande) }}" class="d-inline">
                @csrf
                <input type="hidden" name="section" value="{{ $section ?? 'en_gros' }}" />
                <button
                  type="submit"
                  class="btn btn-sm btn-outline-success"
                  title="Envoyer vers OVL pour livraison"
                  onclick="return confirm('Envoyer cette commande vers OVL pour livraison ?');">
                  <i class="bx bx-send"></i>
                </button>
              </form>
            @endif
            <button
              type="button"
              class="btn btn-sm btn-outline-primary"
              title="Modifier"
              data-bs-toggle="modal"
              data-bs-target="#modalEditCommande{{ $commande->id }}">
              <i class="bx bx-edit"></i>
            </button>
          </td>
        </tr>
        <tr class="collapse" id="lignesCommande{{ $section ?? 'all' }}_{{ $commande->id }}">
          <td colspan="11" class="bg-label-secondary bg-opacity-10">
            <div class="p-3">
              <div class="table-responsive">
                <table class="table table-sm mb-0">
                  <thead>
                    <tr>
                      <th>Parfum</th>
                      <th>Contenance</th>
                      <th>Catégorie</th>
                      <th>Qté</th>
                      <th>Volume</th>
                      <th>Prix unitaire</th>
                      <th>Montant</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach ($commande->lignes as $ligne)
                      <tr>
                        <td>
                          {{ $ligne->produit?->nom ?? '—' }}
                          @if ($ligne->isCocktail())
                            <div class="small text-muted">{{ rtrim(rtrim(number_format((float) $ligne->quantite_ml, 2, ',', ' '), '0'), ',') }} ml dans le mélange</div>
                          @endif
                        </td>
                        <td>{{ $ligne->flacon ? $ligne->flacon->contenance_ml.' ml' : '—' }}</td>
                        <td>
                          <span class="badge {{ $ligne->isCocktail() ? 'bg-label-warning' : ($ligne->isEnGros() ? 'bg-label-info' : 'bg-label-primary') }}">
                            {{ $ligne->categorieLabel() }}
                          </span>
                        </td>
                        <td>{{ $ligne->quantite }}</td>
                        <td>{{ rtrim(rtrim(number_format($ligne->volumeMl(), 2, ',', ' '), '0'), ',') }} ml</td>
                        <td>{{ $fmt($ligne->prix_unitaire) }} FCFA</td>
                        <td class="fw-semibold">{{ $fmt($ligne->montant()) }} FCFA</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
                <div class="d-flex justify-content-center mt-3">
                  <div style="min-width: 280px">
                    <div class="d-flex justify-content-between gap-4 mb-1">
                      <span class="text-muted">Sous-total parfums</span>
                      <span class="fw-semibold">{{ $fmt($commande->montantArticles()) }} FCFA</span>
                    </div>
                    <div class="d-flex justify-content-between gap-4 mb-1">
                      <span class="text-muted">Coût livraison ({{ $commande->commune?->nom ?? '—' }})</span>
                      <span class="fw-semibold">{{ $fmt($commande->frais_livraison) }} FCFA</span>
                    </div>
                    <div class="d-flex justify-content-between gap-4 pt-1 border-top">
                      <span class="fw-semibold">Total</span>
                      <span class="fw-bold text-primary">{{ $fmt($commande->montant()) }} FCFA</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="11" class="text-center py-5 text-muted">
            {{ $emptyMessage }}
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
