<div class="table-responsive text-nowrap">
  <table class="table mb-0">
    <thead>
      <tr>
        <th>Référence</th>
        <th>Contenance</th>
        <th>Prix unitaire</th>
        <th>Parfums</th>
        <th class="text-end">Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($prixUnitaires as $prix)
        <tr>
          <td>
            <a href="{{ route('prix-unitaires.show', $prix) }}" class="fw-semibold text-primary text-decoration-none">
              {{ $prix->reference }}
            </a>
          </td>
          <td>{{ $prix->flacon ? $prix->flacon->contenance_ml.' ml' : '—' }}</td>
          <td class="fw-semibold text-primary">{{ $fmt($prix->prix) }} FCFA</td>
          <td>
            <span class="badge bg-label-secondary">{{ $prix->produits_count }}</span>
          </td>
          <td class="text-end">
            <a href="{{ route('prix-unitaires.show', $prix) }}" class="btn btn-sm btn-outline-primary" title="Voir">
              <i class="bx bx-show"></i>
            </a>
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
          <td colspan="5" class="text-center py-5 text-muted">
            {{ $emptyMessage }}
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
