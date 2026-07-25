@extends('layout.main')

@section('title', 'Entrées de stock')

@section('content')
<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">Entrées de stock</h4>
        <p class="mb-0 text-muted">Réceptions de parfums en millilitres (ml)</p>
      </div>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNouvelleEntree">
        <i class="bx bx-plus me-1"></i>Nouvelle entrée
      </button>
    </div>

    @if (session('success'))
      <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    <form method="GET" action="{{ route('stock.entrees') }}" class="card mb-4">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-5">
            <label class="form-label">Recherche</label>
            <input type="text" name="q" class="form-control" placeholder="Nom du parfum…" value="{{ request('q') }}" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Parfum</label>
            <select name="produit_id" class="form-select">
              <option value="">Tous</option>
              @foreach ($produits as $produit)
                <option value="{{ $produit->id }}" @selected((string) request('produit_id') === (string) $produit->id)>{{ $produit->nom }}</option>
              @endforeach
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
              <th>Date</th>
              <th>Parfum</th>
              <th>Quantité (ml)</th>
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
                <td class="fw-medium">{{ $mouvement->produit?->nom }}</td>
                <td class="text-success">+{{ number_format((float) $mouvement->quantite_ml, 2, ',', ' ') }} ml</td>
                <td>{{ number_format((float) $mouvement->stock_avant, 2, ',', ' ') }} ml</td>
                <td>{{ number_format((float) $mouvement->stock_apres, 2, ',', ' ') }} ml</td>
                <td>{{ $mouvement->user?->name ?? '—' }}</td>
                <td>{{ $mouvement->commentaire ?: '—' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center py-4 text-muted">Aucune entrée de stock</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="mt-3">{{ $mouvements->links() }}</div>

    <div class="modal fade" id="modalNouvelleEntree" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Nouvelle entrée de stock</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST" action="{{ route('stock.entrees.store') }}">
            @csrf
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Parfum</label>
                <select name="produit_id" class="form-select" required>
                  <option value="">Sélectionner…</option>
                  @foreach ($produits as $produit)
                    <option value="{{ $produit->id }}" @selected((string) old('produit_id') === (string) $produit->id)>
                      {{ $produit->nom }} ({{ number_format((float) $produit->stock_ml, 2, ',', ' ') }} ml)
                    </option>
                  @endforeach
                </select>
                @error('produit_id')<div class="text-danger mt-1">{{ $message }}</div>@enderror
              </div>

              <div class="mb-3">
                <label class="form-label">Quantité (ml)</label>
                <input
                  type="text"
                  inputmode="decimal"
                  id="entree_quantite_display"
                  class="form-control"
                  value="{{ old('quantite') ? number_format((float) old('quantite'), 0, ',', ' ') : '' }}"
                  placeholder="Ex: 100 000"
                  autocomplete="off"
                  required />
                <input type="hidden" name="quantite" id="entree_quantite" value="{{ old('quantite') }}" />
                @error('quantite')<div class="text-danger mt-1">{{ $message }}</div>@enderror
              </div>

              <div class="mb-3">
                <label class="form-label">Commentaire</label>
                <textarea name="commentaire" class="form-control" rows="2">{{ old('commentaire') }}</textarea>
                @error('commentaire')<div class="text-danger mt-1">{{ $message }}</div>@enderror
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

    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var display = document.getElementById('entree_quantite_display');
        var hidden = document.getElementById('entree_quantite');

        function sanitize(value) {
          return String(value || '')
            .replace(/\s/g, '')
            .replace(/,/g, '.')
            .replace(/[^\d.]/g, '');
        }

        function formatFr(value) {
          var clean = sanitize(value);
          if (clean === '' || clean === '.') return '';

          var parts = clean.split('.');
          var integer = parts[0] || '0';
          var decimal = parts.length > 1 ? parts[1].slice(0, 2) : null;

          integer = integer.replace(/^0+(?=\d)/, '');
          integer = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

          return decimal !== null ? integer + ',' + decimal : integer;
        }

        function syncHidden(value) {
          var clean = sanitize(value);
          hidden.value = clean === '' || clean === '.' ? '' : clean;
        }

        display.addEventListener('input', function () {
          var cursor = display.selectionStart;
          var before = display.value.length;
          var formatted = formatFr(display.value);
          display.value = formatted;
          syncHidden(formatted);

          var after = display.value.length;
          var next = Math.max(0, cursor + (after - before));
          display.setSelectionRange(next, next);
        });

        display.addEventListener('blur', function () {
          display.value = formatFr(display.value);
          syncHidden(display.value);
        });

        syncHidden(display.value);

        @if ($errors->any())
          var el = document.getElementById('modalNouvelleEntree');
          if (el && window.bootstrap) new bootstrap.Modal(el).show();
        @endif
      });
    </script>
  </div>
</div>
@endsection
