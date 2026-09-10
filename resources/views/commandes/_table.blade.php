<div class="table-responsive text-nowrap">
  <table class="table mb-0">
    <thead>
      <tr>
        <th>Référence</th>
        <th>Parfum</th>
        <th>Contenance</th>
        <th>Qté</th>
        <th>Prix unitaire</th>
        <th>Montant</th>
        <th>Client</th>
        <th>Téléphone</th>
        <th>Statut</th>
        <th>Date commande</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($commandes as $commande)
        <tr>
          <td class="fw-medium">{{ $commande->reference }}</td>
          <td>{{ $commande->produit?->nom ?? '—' }}</td>
          <td>{{ $commande->flacon ? $commande->flacon->contenance_ml.' ml' : '—' }}</td>
          <td>{{ $commande->quantite }}</td>
          <td>{{ $fmt($commande->prixEffectif()) }} FCFA</td>
          <td class="fw-semibold text-primary">{{ $fmt($commande->montant()) }} FCFA</td>
          <td>{{ $commande->client_nom ?: '—' }}</td>
          <td>{{ $commande->client_telephone }}</td>
          <td style="min-width: 160px;">
            <form method="POST" action="{{ route('commandes.statut', $commande) }}" class="m-0">
              @csrf
              @method('PATCH')
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
          <td>{{ $commande->date_commande?->format('d/m/Y') ?? $commande->created_at?->format('d/m/Y') }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="10" class="text-center py-5 text-muted">
            {{ $emptyMessage }}
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
